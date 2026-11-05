<?php

namespace App\Apps\CmsproShare\Services;

use App\Apps\CmsproShare\Models\Channel;
use App\Services\HookManager;
use Illuminate\Support\Facades\Schema;

/**
 * 分享渠道注册表
 *
 * 合并顺序：内置定义 → 数据库覆盖（后台启停/排序/自定义） → share.channels 钩子追加。
 * 渠道是否展示由后台配置项控制，业务应用可随时通过钩子注册自有渠道，无需修改本应用。
 */
class ChannelService
{
    public function __construct(private ?ShareConfig $config = null)
    {
        // 延迟解析：纯逻辑调用（defaults/buildUrl）不需要框架容器
    }

    private function config(): ShareConfig
    {
        return $this->config ??= app(ShareConfig::class);
    }

    /**
     * 内置渠道定义
     *
     * 说明：抖音、小红书、钉钉、企业微信等无官方网页分享接口，采用「复制链接 + 引导粘贴」；
     * 微信好友/朋友圈在未启用 JS-SDK 时由前端展示「点右上角分享」引导。
     *
     * @return array<int,array<string,mixed>>
     */
    public function defaults(): array
    {
        return [
            ['code' => 'wechat', 'name' => '微信好友', 'region' => 'domestic', 'mode' => 'wechat_sdk', 'icon' => 'fa fa-weixin', 'sort' => 10, 'template' => ''],
            ['code' => 'moments', 'name' => '朋友圈', 'region' => 'domestic', 'mode' => 'wechat_sdk', 'icon' => 'fa fa-weixin', 'sort' => 11, 'template' => ''],
            ['code' => 'qq', 'name' => 'QQ好友', 'region' => 'domestic', 'mode' => 'link', 'icon' => 'fa fa-qq', 'sort' => 20, 'template' => 'https://connect.qq.com/widget/shareqq/index.html?url={url}&title={title}&summary={summary}&pics={image}'],
            ['code' => 'qzone', 'name' => 'QQ空间', 'region' => 'domestic', 'mode' => 'link', 'icon' => 'fa fa-qq', 'sort' => 21, 'template' => 'https://sns.qzone.qq.com/cgi-bin/qzshare/cgi_qzshare_onekey?url={url}&title={title}&summary={summary}&pics={image}'],
            ['code' => 'weibo', 'name' => '新浪微博', 'region' => 'domestic', 'mode' => 'link', 'icon' => 'fa fa-weibo', 'sort' => 22, 'template' => 'https://service.weibo.com/share/share.php?url={url}&title={title}&pic={image}'],
            ['code' => 'douyin', 'name' => '抖音', 'region' => 'domestic', 'mode' => 'copy', 'icon' => 'fa fa-video-camera', 'sort' => 23, 'template' => ''],
            ['code' => 'xiaohongshu', 'name' => '小红书', 'region' => 'domestic', 'mode' => 'copy', 'icon' => 'fa fa-book', 'sort' => 24, 'template' => ''],
            ['code' => 'dingtalk', 'name' => '钉钉', 'region' => 'domestic', 'mode' => 'copy', 'icon' => 'fa fa-commenting', 'sort' => 25, 'template' => ''],
            ['code' => 'wecom', 'name' => '企业微信', 'region' => 'domestic', 'mode' => 'copy', 'icon' => 'fa fa-weixin', 'sort' => 26, 'template' => ''],

            ['code' => 'facebook', 'name' => 'Facebook', 'region' => 'overseas', 'mode' => 'link', 'icon' => 'fa fa-facebook', 'sort' => 40, 'template' => 'https://www.facebook.com/sharer/sharer.php?u={url}'],
            ['code' => 'x', 'name' => 'X / Twitter', 'region' => 'overseas', 'mode' => 'link', 'icon' => 'fa fa-twitter', 'sort' => 41, 'template' => 'https://twitter.com/intent/tweet?url={url}&text={title}'],
            ['code' => 'linkedin', 'name' => 'LinkedIn', 'region' => 'overseas', 'mode' => 'link', 'icon' => 'fa fa-linkedin', 'sort' => 42, 'template' => 'https://www.linkedin.com/sharing/share-offsite/?url={url}'],
            ['code' => 'whatsapp', 'name' => 'WhatsApp', 'region' => 'overseas', 'mode' => 'link', 'icon' => 'fa fa-whatsapp', 'sort' => 43, 'template' => 'https://api.whatsapp.com/send?text={title}%20{url}'],
            ['code' => 'telegram', 'name' => 'Telegram', 'region' => 'overseas', 'mode' => 'link', 'icon' => 'fa fa-telegram', 'sort' => 44, 'template' => 'https://t.me/share/url?url={url}&text={title}'],
            ['code' => 'pinterest', 'name' => 'Pinterest', 'region' => 'overseas', 'mode' => 'link', 'icon' => 'fa fa-pinterest', 'sort' => 45, 'template' => 'https://www.pinterest.com/pin/create/button/?url={url}&media={image}&description={title}'],
            ['code' => 'reddit', 'name' => 'Reddit', 'region' => 'overseas', 'mode' => 'link', 'icon' => 'fa fa-reddit', 'sort' => 46, 'template' => 'https://www.reddit.com/submit?url={url}&title={title}'],
            ['code' => 'line', 'name' => 'LINE', 'region' => 'overseas', 'mode' => 'link', 'icon' => 'fa fa-comment', 'sort' => 47, 'template' => 'https://social-plugins.line.me/lineit/share?url={url}'],
            ['code' => 'email', 'name' => '邮件', 'region' => 'overseas', 'mode' => 'link', 'icon' => 'fa fa-envelope-o', 'sort' => 48, 'template' => 'mailto:?subject={title}&body={summary}%20{url}'],

            ['code' => 'copy', 'name' => '复制链接', 'region' => 'common', 'mode' => 'copy', 'icon' => 'fa fa-link', 'sort' => 90, 'template' => ''],
            ['code' => 'qrcode', 'name' => '二维码', 'region' => 'common', 'mode' => 'qrcode', 'icon' => 'fa fa-qrcode', 'sort' => 91, 'template' => ''],
            ['code' => 'poster', 'name' => '分享海报', 'region' => 'common', 'mode' => 'poster', 'icon' => 'fa fa-picture-o', 'sort' => 92, 'template' => ''],
            ['code' => 'native', 'name' => '系统分享', 'region' => 'common', 'mode' => 'native', 'icon' => 'fa fa-share-alt', 'sort' => 93, 'template' => ''],
        ];
    }

    /**
     * 获取当前可用渠道列表
     *
     * @return array<int,array<string,mixed>> 按 sort 升序
     */
    public function all(): array
    {
        $map = [];
        foreach ($this->defaults() as $channel) {
            $channel['status'] = 1;
            $map[$channel['code']] = $channel;
        }

        $builtinCodes = array_keys($map);

        // 数据库覆盖：后台启停、排序、自定义渠道
        if (Schema::hasTable('app_cmspro_share_channels')) {
            foreach (Channel::query()->get() as $row) {
                $item = $row->toArray();
                $code = (string) $item['code'];
                $map[$code] = array_merge($map[$code] ?? ['is_builtin' => 0], $item);
            }
        }

        // 扩展钩子：任意应用可追加或改写渠道
        $map = app(HookManager::class)->applyFilter('share.channels', $map);
        if (!is_array($map)) {
            $map = [];
        }

        $whitelist = $this->channelWhitelist();
        $channels = [];

        foreach ($map as $code => $channel) {
            if (!is_array($channel) || (int) ($channel['status'] ?? 1) !== 1) {
                continue;
            }

            $mode = (string) ($channel['mode'] ?? 'link');
            if (!$this->modeEnabled($mode)) {
                continue;
            }

            // 默认展示渠道白名单只约束内置渠道，钩子注册的自定义渠道始终可展示
            if ($whitelist !== [] && in_array($code, $builtinCodes, true) && !in_array($code, $whitelist, true)) {
                continue;
            }

            $channel['code'] = (string) $code;
            $channel['template'] = (string) ($channel['template'] ?? '');
            $channel['icon'] = (string) ($channel['icon'] ?? '');
            $channel['sort'] = (int) ($channel['sort'] ?? 0);
            $channels[] = $channel;
        }

        usort($channels, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return $channels;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function get(string $code): ?array
    {
        foreach ($this->all() as $channel) {
            if ($channel['code'] === $code) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * 按渠道模板生成分享链接
     *
     * 支持 {url} {title} {summary} {image} 占位符，全部做 URL 编码；
     * 非 link 模式（复制/二维码/海报/原生/微信SDK）返回中转链接本身，由前端接管。
     *
     * @param array<string,mixed> $channel 渠道定义
     * @param array<string,mixed> $context 分享上下文
     */
    public function buildUrl(array $channel, array $context): string
    {
        $template = (string) ($channel['template'] ?? '');
        $url = (string) ($context['url'] ?? '');

        if ($template === '' || (string) ($channel['mode'] ?? 'link') !== 'link') {
            return $url;
        }

        $replacements = [
            '{url}' => rawurlencode($url),
            '{title}' => rawurlencode((string) ($context['title'] ?? '')),
            '{summary}' => rawurlencode((string) ($context['summary'] ?? '')),
            '{image}' => rawurlencode((string) ($context['image'] ?? '')),
        ];

        return strtr($template, $replacements);
    }

    /**
     * 保存后台对单个渠道的覆盖配置
     *
     * @param array<string,mixed> $data 仅允许 status / sort / name / template / icon
     */
    public function saveOverride(string $code, array $data): void
    {
        if (!Schema::hasTable('app_cmspro_share_channels')) {
            return;
        }

        $allowed = ['status', 'sort', 'name', 'template', 'icon'];
        $payload = array_intersect_key($data, array_flip($allowed));
        if ($payload === []) {
            return;
        }

        $definition = [];
        foreach ($this->defaults() as $item) {
            if ($item['code'] === $code) {
                $definition = $item;
                break;
            }
        }

        Channel::updateOrCreate(
            ['code' => $code],
            array_merge([
                'name' => $definition['name'] ?? $code,
                'region' => $definition['region'] ?? 'common',
                'mode' => $definition['mode'] ?? 'link',
                'template' => $definition['template'] ?? '',
                'icon' => $definition['icon'] ?? '',
                'sort' => $definition['sort'] ?? 0,
                'is_builtin' => $definition === [] ? 0 : 1,
            ], $payload)
        );
    }

    /**
     * 初始化内置渠道种子（幂等，不覆盖已有记录）
     */
    public function seedDefaults(): int
    {
        if (!Schema::hasTable('app_cmspro_share_channels')) {
            return 0;
        }

        $count = 0;
        foreach ($this->defaults() as $channel) {
            if (Channel::where('code', $channel['code'])->exists()) {
                continue;
            }

            Channel::create(array_merge($channel, ['status' => 1, 'is_builtin' => 1]));
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int,string> 空数组表示展示全部
     */
    private function channelWhitelist(): array
    {
        $raw = $this->config()->string('default_channels');
        if (trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function modeEnabled(string $mode): bool
    {
        return match ($mode) {
            'qrcode' => $this->config()->bool('qrcode_enabled', true),
            'poster' => $this->config()->bool('poster_enabled', false),
            'native' => $this->config()->bool('native_enabled', true),
            'copy' => $this->config()->bool('copy_enabled', true),
            default => true,
        };
    }
}
