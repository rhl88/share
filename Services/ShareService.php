<?php

namespace App\Apps\CmsproShare\Services;

use App\Apps\CmsproShare\Models\ShareLink;
use App\Services\HookManager;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * 一键分享核心服务
 *
 * 对外提供 build / url / render / convert 四个入口；
 * 内部串联场景解析、链接生成、渠道装配、事件跟踪与转化归因。
 * 业务应用可直接 app(ShareService::class) 调用，也可使用 share_render() 等辅助函数。
 */
class ShareService
{
    public function __construct(
        private ShareConfig $config,
        private ChannelService $channels,
        private SceneService $scenes,
        private TrackService $tracker,
        private WechatService $wechat
    ) {
    }

    /**
     * 生成一次分享
     *
     * @param string     $sceneCode 场景标识（业务应用通过 share.types 注册）
     * @param string|int $targetId  分享对象ID
     * @param array<string,mixed> $options sharer_id / sharer_type / parent_code / channel / params
     * @return array{success:bool,message:string,data:array<string,mixed>|null}
     */
    public function build(string $sceneCode, $targetId, array $options = []): array
    {
        $scene = $this->scenes->get($sceneCode);
        if ($scene === null) {
            return $this->fail('分享场景未注册：' . $sceneCode);
        }

        $context = $this->scenes->resolve($sceneCode, $targetId);
        $context['title'] = $context['title'] !== ''
            ? $context['title']
            : ($this->config->string('default_title') ?: config('app.name', ''));
        $context['image'] = $context['image'] !== ''
            ? $context['image']
            : $this->config->string('default_image');

        $code = $this->generateCode();
        $context['code'] = $code;

        $ttl = $this->config->int('link_ttl_days', 0);
        $expireTime = $ttl > 0 ? now()->addDays($ttl)->format('Y-m-d H:i:s') : null;

        $sharerId = isset($options['sharer_id']) ? (int) $options['sharer_id'] : (auth('web')->id() ?: null);
        $sharerType = (string) ($options['sharer_type'] ?? ($sharerId !== null ? 'user' : 'guest'));
        $parentCode = $options['parent_code'] ?? $this->parentFromRequest();
        $channelCode = (string) ($options['channel'] ?? '');

        ShareLink::create([
            'code' => $code,
            'scene_code' => $sceneCode,
            'target_id' => (string) $targetId,
            'target_url' => (string) $context['url'],
            'title' => (string) $context['title'],
            'summary' => (string) $context['summary'],
            'image' => (string) $context['image'],
            'channel_code' => $channelCode !== '' ? $channelCode : null,
            'sharer_id' => $sharerId,
            'sharer_type' => $sharerType,
            'parent_code' => $parentCode,
            'params' => !empty($options['params']) ? json_encode($options['params'], JSON_UNESCAPED_UNICODE) : null,
            'expire_time' => $expireTime,
            'status' => 1,
        ]);

        $baseUrl = $this->buildRedirectUrl($code, $channelCode);
        $url = app(HookManager::class)->applyFilter('share.link.build', $baseUrl, $context);
        $url = is_string($url) && $url !== '' ? $url : $baseUrl;

        $channels = $this->assembleChannels($url, $context);

        $this->tracker->record($code, 'create', null, ['channel_code' => $channelCode]);

        $data = [
            'code' => $code,
            'url' => $url,
            'target_url' => (string) $context['url'],
            'title' => (string) $context['title'],
            'summary' => (string) $context['summary'],
            'image' => (string) $context['image'],
            'scene' => [
                'code' => $sceneCode,
                'name' => (string) ($scene['name'] ?? $sceneCode),
                'app_id' => (string) ($scene['app_id'] ?? ''),
            ],
            'channels' => $channels,
            'sdk' => [
                'wechat' => $this->wechat->signature($url),
                'wechat_guide' => $this->config->string('wechat_guide_text'),
                'qrcode_url' => '/api/cmspro/share/qrcode?data=' . rawurlencode($url),
                'poster_url' => '/api/cmspro/share/poster/' . $code,
                'track_url' => '/api/cmspro/share/track',
                'convert_url' => '/api/cmspro/share/convert',
                'native_enabled' => $this->config->bool('native_enabled', true),
            ],
        ];

        return $this->ok($data);
    }

    /**
     * 仅返回分享中转链接，失败返回空字符串
     */
    public function url(string $sceneCode, $targetId, array $options = []): string
    {
        $result = $this->build($sceneCode, $targetId, $options);
        return $result['success'] ? (string) $result['data']['url'] : '';
    }

    /**
     * 渲染分享面板 HTML（委托给全局辅助函数，保证与模板调用输出一致）
     */
    public function render(string $sceneCode, $targetId, array $options = []): string
    {
        return function_exists('share_render')
            ? share_render($sceneCode, $targetId, $options)
            : '';
    }

    public function findByCode(string $code): ?ShareLink
    {
        $link = ShareLink::where('code', $code)->where('status', 1)->first();
        if ($link === null) {
            return null;
        }

        if ($link->expire_time !== null && $link->expire_time->lt(now())) {
            return null;
        }

        return $link;
    }

    /**
     * 执行中转跳转：记录落地事件、绑定邀请关系，返回目标 URL
     *
     * @return string|null 链接失效时返回 null，由控制器渲染提示页
     */
    public function landing(string $code, Request $request): ?string
    {
        $link = $this->findByCode($code);
        if ($link === null) {
            return null;
        }

        $channelCode = (string) $request->query('ch', '');
        $this->tracker->record($code, 'landing', $request, ['channel_code' => $channelCode]);
        $this->tracker->bindInvite($link, $request, auth('web')->id() ?: null);

        return (string) $link->target_url;
    }

    /**
     * 前端 SDK 所需的组件数据
     *
     * @return array<string,mixed>
     */
    public function widget(string $code): array
    {
        $link = $this->findByCode($code);
        if ($link === null) {
            return [];
        }

        $url = $this->buildRedirectUrl($code, (string) ($link->channel_code ?? ''));
        $context = [
            'code' => $code,
            'url' => $url,
            'title' => (string) $link->title,
            'summary' => (string) $link->summary,
            'image' => (string) $link->image,
            'target_url' => (string) $link->target_url,
        ];

        return [
            'code' => $code,
            'url' => $url,
            'title' => $context['title'],
            'summary' => $context['summary'],
            'image' => $context['image'],
            'channels' => $this->assembleChannels($url, $context),
            'sdk' => [
                'wechat' => $this->wechat->signature($url),
                'wechat_guide' => $this->config->string('wechat_guide_text'),
                'qrcode_url' => '/api/cmspro/share/qrcode?data=' . rawurlencode($url),
                'poster_url' => '/api/cmspro/share/poster/' . $code,
                'track_url' => '/api/cmspro/share/track',
                'native_enabled' => $this->config->bool('native_enabled', true),
            ],
        ];
    }

    /**
     * 上报转化并归因
     *
     * 业务应用在注册 / 下单等成功后调用；奖励规则不在本应用内实现，
     * 仅通过 share.converted 事件把归因结果通知积分 / 分销等应用。
     *
     * @param string $code  分享短码
     * @param string $event 业务转化事件名，如 register / order
     * @param array<string,mixed> $extra 业务扩展数据
     * @return array{success:bool,message:string,data:array<string,mixed>|null}
     */
    public function convert(string $code, string $event, array $extra = []): array
    {
        if (!$this->config->bool('convert_enabled', true)) {
            return $this->fail('转化归因未启用');
        }

        $link = $this->findByCode($code);
        if ($link === null) {
            return $this->fail('分享链接不存在或已失效：' . $code);
        }

        $this->tracker->record($code, 'convert', null, array_merge(['event' => $event], $extra));
        $link->increment('convert_count');

        $payload = [
            'link_code' => $code,
            'scene_code' => $link->scene_code,
            'target_id' => $link->target_id,
            'sharer_id' => $link->sharer_id,
            'user_id' => $extra['user_id'] ?? null,
            'event' => $event,
            'extra' => $extra,
        ];

        if ($this->config->bool('reward_event_enabled', true)) {
            app(HookManager::class)->doAction('share.converted', $payload);
        }

        return $this->ok($payload);
    }

    /**
     * 组装各渠道的最终分享链接
     *
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    private function assembleChannels(string $redirectUrl, array $context): array
    {
        $result = [];

        foreach ($this->channels->all() as $channel) {
            $channelTarget = $redirectUrl;
            if (!str_contains($channelTarget, 'ch=')) {
                $separator = str_contains($channelTarget, '?') ? '&' : '?';
                $channelTarget .= $separator . 'ch=' . rawurlencode($channel['code']);
            }

            $channelContext = array_merge($context, ['url' => $channelTarget]);

            $result[] = [
                'code' => $channel['code'],
                'name' => (string) ($channel['name'] ?? $channel['code']),
                'region' => (string) ($channel['region'] ?? 'common'),
                'mode' => (string) ($channel['mode'] ?? 'link'),
                'icon' => (string) ($channel['icon'] ?? ''),
                'sort' => (int) ($channel['sort'] ?? 0),
                'url' => $this->channels->buildUrl($channel, $channelContext),
            ];
        }

        return $result;
    }

    /**
     * 构造中转链接，可按配置附加 UTM 参数
     */
    private function buildRedirectUrl(string $code, string $channelCode = ''): string
    {
        $path = trim($this->config->string('access_path', 'share'), '/');
        $url = url('/' . ($path !== '' ? $path . '/' : '') . $code);

        if (!$this->config->bool('utm_enabled', true)) {
            return $url;
        }

        return $url . '?' . http_build_query([
            'utm_source' => $this->config->string('utm_source', 'share'),
            'utm_medium' => $channelCode !== '' ? $channelCode : 'social',
            'utm_campaign' => $code,
        ]);
    }

    /**
     * 从当前请求中读取上级分享码（裂变链路）
     */
    private function parentFromRequest(): ?string
    {
        $param = $this->config->string('parent_param', 'from');
        $value = request()->query($param);
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function generateCode(): string
    {
        $length = max(6, min(16, $this->config->int('code_length', 8)));

        do {
            $code = Str::random($length);
        } while (ShareLink::where('code', $code)->exists());

        return $code;
    }

    /**
     * @param array<string,mixed> $data
     * @return array{success:bool,message:string,data:array<string,mixed>}
     */
    private function ok(array $data): array
    {
        return ['success' => true, 'message' => 'ok', 'data' => $data];
    }

    /**
     * @return array{success:bool,message:string,data:null}
     */
    private function fail(string $message): array
    {
        return ['success' => false, 'message' => $message, 'data' => null];
    }
}
