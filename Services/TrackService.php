<?php

namespace App\Apps\CmsproShare\Services;

use App\Apps\CmsproShare\Models\DailyStat;
use App\Apps\CmsproShare\Models\InviteRelation;
use App\Apps\CmsproShare\Models\ShareEvent;
use App\Apps\CmsproShare\Models\ShareLink;
use App\Services\HookManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 分享跟踪服务
 *
 * 负责事件落库、UV 去重、裂变邀请关系绑定、日聚合与过期数据清理。
 * 所有行为均由后台配置开关控制，关闭跟踪后分享与跳转主流程不受影响。
 */
class TrackService
{
    public function __construct(private ShareConfig $config)
    {
    }

    /**
     * 记录一条分享事件
     *
     * @param string      $linkCode  分享短码
     * @param string      $eventType create / click / landing / convert
     * @param Request|null $request  携带访客信息的请求，服务端内部上报可传 null
     * @param array<string,mixed> $extra 扩展信息（channel_code、utm_*、业务参数等）
     */
    public function record(string $linkCode, string $eventType, ?Request $request = null, array $extra = []): ?ShareEvent
    {
        if (!$this->config->bool('track_enabled', true)) {
            return null;
        }

        $link = ShareLink::where('code', $linkCode)->first();
        if ($link === null) {
            return null;
        }

        $visitorKey = $request !== null ? $this->visitorKey($request) : null;
        $channelCode = (string) ($extra['channel_code'] ?? $link->channel_code ?? '');

        $event = ShareEvent::create([
            'link_id' => $link->id,
            'link_code' => $linkCode,
            'scene_code' => $link->scene_code,
            'target_id' => $link->target_id,
            'channel_code' => $channelCode,
            'event_type' => $eventType,
            'sharer_id' => $link->sharer_id,
            'visitor_key' => $visitorKey,
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? mb_substr((string) $request->userAgent(), 0, 500) : null,
            'device' => $request !== null ? $this->device($request) : null,
            'referer' => $request?->headers->get('referer'),
            'extra' => $extra !== [] ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
        ]);

        $this->incrementCounters($link, $eventType, $visitorKey);

        if ($this->config->bool('aggregate_enabled', true)) {
            $this->aggregateRow(now()->toDateString(), $link->scene_code, $channelCode, $eventType);
        }

        app(HookManager::class)->doAction('share.event.recorded', $event->toArray());

        return $event;
    }

    /**
     * 访客标识：同一天同一 IP + UA 视为同一访客
     */
    public function visitorKey(Request $request): string
    {
        return md5(
            (string) $request->ip() . '|'
            . (string) $request->userAgent() . '|'
            . now()->toDateString()
        );
    }

    /**
     * 设备类型识别（轻量判断，避免引入额外 UA 解析依赖）
     */
    public function device(Request $request): string
    {
        $ua = strtolower((string) $request->userAgent());

        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            return 'tablet';
        }
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * 绑定裂变邀请关系
     *
     * 访客打开分享链接时，把「分享者 → 访客」关系落库；同一访客对同一链接只绑定一次。
     * 层级沿 parent_code 向上回溯，受 invite_max_level 与 invite_window_days 约束。
     */
    public function bindInvite(ShareLink $link, Request $request, ?int $userId = null): ?InviteRelation
    {
        if (!$this->config->bool('invite_enabled', true) || $link->sharer_id === null) {
            return null;
        }

        $window = $this->config->int('invite_window_days', 30);
        if ($window > 0 && $link->create_time !== null && $link->create_time->copy()->addDays($window)->lt(now())) {
            return null;
        }

        if (!Schema::hasTable('app_cmspro_share_invites')) {
            return null;
        }

        $visitorKey = $this->visitorKey($request);

        $attributes = [
            'parent_code' => $link->parent_code,
            'inviter_id' => $link->sharer_id,
            'visitor_id' => $userId,
            'level' => $this->resolveLevel($link),
        ];

        $relation = InviteRelation::where('link_code', $link->code)
            ->where('visitor_key', $visitorKey)
            ->first();

        if ($relation !== null) {
            // 访客后续登录时补充绑定用户ID，不重复创建关系
            if ($userId !== null && $relation->visitor_id === null) {
                $relation->update(['visitor_id' => $userId]);
            }
            return $relation;
        }

        return InviteRelation::create(array_merge($attributes, [
            'link_code' => $link->code,
            'visitor_key' => $visitorKey,
            'status' => 1,
        ]));
    }

    /**
     * 按日期重算日聚合数据（覆盖式，重复执行结果一致）
     */
    public function aggregate(string $date): void
    {
        if (!Schema::hasTable('app_cmspro_share_daily_stats')) {
            return;
        }

        $rows = DB::table('app_cmspro_share_events')
            ->whereDate('create_time', $date)
            ->select([
                'scene_code',
                'channel_code',
                'event_type',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('COUNT(DISTINCT visitor_key) as uv'),
            ])
            ->groupBy('scene_code', 'channel_code', 'event_type')
            ->get();

        foreach ($rows as $row) {
            $this->aggregateRow(
                $date,
                (string) $row->scene_code,
                (string) ($row->channel_code ?? ''),
                (string) $row->event_type,
                (int) $row->cnt,
                (int) $row->uv
            );
        }
    }

    /**
     * 清理过期原始事件（日聚合数据永久保留）
     */
    public function cleanExpired(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        return ShareEvent::where('create_time', '<', now()->subDays($days)->format('Y-m-d H:i:s'))->delete();
    }

    /**
     * 更新链接计数
     */
    private function incrementCounters(ShareLink $link, string $eventType, ?string $visitorKey): void
    {
        if ($eventType === 'click') {
            $link->increment('click_count');
            return;
        }

        if ($eventType !== 'landing') {
            return;
        }

        $link->increment('visit_count');

        if (!$this->config->bool('uv_dedup_enabled', true)) {
            $link->increment('uv_count');
            return;
        }

        if ($visitorKey === null) {
            return;
        }

        // 当前事件已落库，count 为 1 即表示该访客首次落地
        $landings = ShareEvent::where('link_code', $link->code)
            ->where('event_type', 'landing')
            ->where('visitor_key', $visitorKey)
            ->count();

        if ($landings <= 1) {
            $link->increment('uv_count');
        }
    }

    /**
     * 计算当前链接在裂变链中的层级
     */
    private function resolveLevel(ShareLink $link): int
    {
        $max = max(1, $this->config->int('invite_max_level', 2));
        $level = 1;
        $parentCode = $link->parent_code;

        while ($parentCode && $level < $max) {
            $parent = ShareLink::where('code', $parentCode)->first();
            if ($parent === null) {
                break;
            }
            $level++;
            $parentCode = $parent->parent_code;
        }

        return $level;
    }

    /**
     * 写入 / 覆盖一条日聚合记录
     */
    private function aggregateRow(
        string $date,
        string $sceneCode,
        string $channelCode,
        string $eventType,
        ?int $cnt = null,
        ?int $uv = null
    ): void {
        if (!Schema::hasTable('app_cmspro_share_daily_stats')) {
            return;
        }

        if ($cnt === null) {
            $query = DB::table('app_cmspro_share_events')
                ->whereDate('create_time', $date)
                ->where('scene_code', $sceneCode)
                ->where('event_type', $eventType);
            if ($channelCode !== '') {
                $query->where('channel_code', $channelCode);
            }
            $cnt = (int) $query->count();
            $uv = (int) $query->distinct()->count('visitor_key');
        }

        DailyStat::updateOrCreate(
            [
                'stat_date' => $date,
                'scene_code' => $sceneCode,
                'channel_code' => $channelCode,
                'event_type' => $eventType,
            ],
            ['cnt' => $cnt, 'uv' => $uv ?? 0]
        );
    }
}
