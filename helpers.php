<?php

/**
 * 一键分享应用全局辅助函数
 *
 * 由 ServiceProvider 在 register 阶段 require_once 加载。
 * 所有函数均以 function_exists 包裹，避免与其他应用辅助函数冲突。
 */

use App\Apps\CmsproShare\Services\ShareConfig;
use App\Apps\CmsproShare\Services\ShareService;

if (!function_exists('share_config')) {
    /**
     * 读取一键分享配置项
     *
     * 优先取 config_items 表中的后台设置，未配置时回退 Config/share.php 默认值。
     *
     * @param string $name    配置项 name（不含 app_cmspro_share_ 前缀）
     * @param mixed  $default 兜底值
     * @return mixed
     */
    function share_config(string $name, $default = null)
    {
        return app(ShareConfig::class)->get($name, $default);
    }
}

if (!function_exists('share_is_installed')) {
    /**
     * 判断一键分享应用是否已安装并启用
     *
     * 供其他应用在调用扩展接口前做前置检查，未启用时可自行降级。
     */
    function share_is_installed(): bool
    {
        try {
            $app = \App\Models\AppModel::where('app_id', 'cmspro.share')->first();
            return $app !== null && (int) $app->status === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('share_url')) {
    /**
     * 生成分享中转链接
     *
     * @param string     $sceneCode 场景标识（需业务应用通过 share.types 注册）
     * @param string|int $targetId  分享对象ID
     * @param array      $options   可选：sharer_id / sharer_type / parent_code / channel / params
     * @return string 失败时返回空字符串
     */
    function share_url(string $sceneCode, $targetId, array $options = []): string
    {
        try {
            $result = app(ShareService::class)->build($sceneCode, $targetId, $options);
            return $result['success'] ? (string) $result['data']['url'] : '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('share_asset')) {
    /**
     * 生成应用静态资源地址
     *
     * 资源由系统在安装时把 Assets/ 发布到 public/apps/{appId}/，本应用 app_id 为 cmspro.share。
     * 目录名必须保留点号：写成 apps/cmspro/share/ 会命中 Laravel 回退页，
     * 返回 text/html 导致浏览器拒绝加载样式表与 SDK。
     */
    function share_asset(string $path): string
    {
        return asset('apps/cmspro.share/' . ltrim($path, '/'));
    }
}

if (!function_exists('share_render')) {
    /**
     * 渲染分享面板 HTML
     *
     * 业务应用模板中一行调用即可：
     *   {!! share_render('blog.article', $article->id, ['sharer_id' => $userId]) !!}
     *
     * 链接型渠道服务端直出，复制/二维码/原生分享等交互由 Assets/js/share.js 接管。
     *
     * 展现方式（options['layout']）：
     *   default - 图标+文字按钮（默认）
     *   icon    - 纯图标按钮（文字由 CSS 隐藏，DOM 保留 span 以支持无障碍）
     *   text    - 纯文字按钮（图标由 CSS 隐藏）
     *   popup   - 「分享」触发按钮，点击弹出完整图标+文字面板
     *
     * @param string     $sceneCode 场景标识
     * @param string|int $targetId  分享对象ID
     * @param array      $options   生成选项，另支持 title / button_class / show_label / layout
     * @return string HTML，失败时返回 HTML 注释（不破坏页面布局）
     */
    function share_render(string $sceneCode, $targetId, array $options = []): string
    {
        try {
            $result = app(ShareService::class)->build($sceneCode, $targetId, $options);
        } catch (\Throwable $e) {
            return '<!-- 一键分享组件不可用 -->';
        }

        if (!$result['success']) {
            return '<!-- 一键分享：' . e($result['message']) . ' -->';
        }

        $data = $result['data'];
        $showLabel = (bool) ($options['show_label'] ?? true);
        $buttonClass = (string) ($options['button_class'] ?? '');
        $layout = (string) ($options['layout'] ?? 'default');
        if (!in_array($layout, ['default', 'icon', 'text', 'popup'], true)) {
            $layout = 'default';
        }
        if (!$showLabel && $layout === 'default') {
            $layout = 'icon';
        }

        $html = '<div class="cmspro-share" data-layout="' . e($layout)
            . '" data-share-code="' . e($data['code'])
            . '" data-share-url="' . e($data['url'])
            . '" data-share-title="' . e($data['title']) . '">';

        if ($layout === 'popup') {
            $html .= '<button type="button" class="cmspro-share-trigger ' . e($buttonClass)
                . '"><i class="fa fa-share-alt"></i><span>分享</span></button>'
                . '<div class="cmspro-share-popup-body" hidden>';
        }

        foreach ($data['channels'] as $channel) {
            $mode = e($channel['mode']);
            $href = e($channel['url']);
            $label = e($channel['name']);
            $code = e($channel['code']);
            $icon = e($channel['icon']);

            $html .= '<button type="button" class="cmspro-share-btn ' . e($buttonClass)
                . '" data-channel="' . $code
                . '" data-mode="' . $mode
                . '" data-href="' . $href
                . '" title="' . $label . '">'
                . '<i class="' . $icon . '"></i>'
                . ($showLabel || $layout === 'icon' ? '<span>' . $label . '</span>' : '')
                . '</button>';
        }

        if ($layout === 'popup') {
            $html .= '</div>';
        }

        $html .= '</div>';

        // 单次请求内只注入一次 SDK 与样式
        static $sdkInjected = false;
        if (!$sdkInjected) {
            $sdkInjected = true;
            $html = '<link rel="stylesheet" href="' . e(share_asset('css/share.css')) . '">'
                . '<script src="' . e(share_asset('js/share.js')) . '" defer></script>'
                . $html;
        }

        return $html;
    }
}
