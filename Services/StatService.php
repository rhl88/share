<?php

namespace App\Apps\CmsproShare\Services;

use Illuminate\Support\Facades\DB;

/**
 * 分享数据报表服务
 *
 * 漏斗与明细基于事件表实时聚合（带复合索引，精确）；
 * 趋势图读日聚合表，避免大时间范围扫描原始事件。
 */
class StatService
{
    /**
     * 分享漏斗：生成 → 点击 → 落地 → 转化
     *
     * @return array<string,int|float>
     */
    public function funnel(string $start, string $end, array $filters = []): array
    {
        $query = DB::table('app_cmspro_share_events')
            ->whereBetween('create_time', [$start . ' 00:00:00', $end . ' 23:59:59']);

        if (($filters['scene_code'] ?? '') !== '') {
            $query->where('scene_code', $filters['scene_code']);
        }
        if (($filters['channel_code'] ?? '') !== '') {
            $query->where('channel_code', $filters['channel_code']);
        }

        $counts = $query->select('event_type', DB::raw('COUNT(*) as cnt'))
            ->groupBy('event_type')
            ->pluck('cnt', 'event_type')
            ->toArray();

        $result = [
            'create' => (int) ($counts['create'] ?? 0),
            'click' => (int) ($counts['click'] ?? 0),
            'landing' => (int) ($counts['landing'] ?? 0),
            'convert' => (int) ($counts['convert'] ?? 0),
        ];

        $result['click_rate'] = $result['create'] > 0
            ? round($result['click'] / $result['create'] * 100, 2) : 0.0;
        $result['convert_rate'] = $result['landing'] > 0
            ? round($result['convert'] / $result['landing'] * 100, 2) : 0.0;

        return $result;
    }

    /**
     * 数据看板总览
     *
     * @return array<string,mixed>
     */
    public function overview(string $start, string $end): array
    {
        $funnel = $this->funnel($start, $end);

        $linkCount = DB::table('app_cmspro_share_links')
            ->whereBetween('create_time', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->count();

        $inviteCount = DB::table('app_cmspro_share_invites')
            ->whereBetween('create_time', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->count();

        $uv = DB::table('app_cmspro_share_events')
            ->where('event_type', 'landing')
            ->whereBetween('create_time', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->distinct()->count('visitor_key');

        return array_merge($funnel, [
            'link_count' => $linkCount,
            'invite_count' => $inviteCount,
            'uv' => $uv,
        ]);
    }

    /**
     * 按渠道统计落地与转化
     *
     * @return array<int,array<string,mixed>>
     */
    public function byChannel(string $start, string $end): array
    {
        return $this->groupBy('channel_code', $start, $end);
    }

    /**
     * 按场景统计落地与转化
     *
     * @return array<int,array<string,mixed>>
     */
    public function byScene(string $start, string $end): array
    {
        return $this->groupBy('scene_code', $start, $end);
    }

    /**
     * 趋势数据（读日聚合表）
     *
     * @return array<int,array<string,mixed>>
     */
    public function trend(string $start, string $end): array
    {
        return DB::table('app_cmspro_share_daily_stats')
            ->whereBetween('stat_date', [$start, $end])
            ->select('stat_date', 'event_type', DB::raw('SUM(cnt) as cnt'), DB::raw('SUM(uv) as uv'))
            ->groupBy('stat_date', 'event_type')
            ->orderBy('stat_date')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->stat_date,
                'event_type' => (string) $row->event_type,
                'cnt' => (int) $row->cnt,
                'uv' => (int) $row->uv,
            ])->all();
    }

    /**
     * 热门分享链接
     *
     * @return array<int,array<string,mixed>>
     */
    public function topLinks(string $start, string $end, int $limit = 10): array
    {
        return DB::table('app_cmspro_share_links')
            ->whereBetween('create_time', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->orderByDesc('visit_count')
            ->limit($limit)
            ->get(['code', 'scene_code', 'target_id', 'title', 'channel_code', 'sharer_id', 'click_count', 'visit_count', 'uv_count', 'convert_count', 'create_time'])
            ->map(fn ($row) => (array) $row)->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function groupBy(string $field, string $start, string $end): array
    {
        $rows = DB::table('app_cmspro_share_events')
            ->whereBetween('create_time', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->whereIn('event_type', ['click', 'landing', 'convert'])
            ->select($field, 'event_type', DB::raw('COUNT(*) as cnt'))
            ->groupBy($field, 'event_type')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $key = (string) ($row->{$field} ?? '');
            if ($key === '') {
                $key = '未标注';
            }
            if (!isset($result[$key])) {
                $result[$key] = ['name' => $key, 'click' => 0, 'landing' => 0, 'convert' => 0];
            }
            $result[$key][(string) $row->event_type] = (int) $row->cnt;
        }

        return array_values($result);
    }
}
