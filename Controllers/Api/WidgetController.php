<?php

namespace App\Apps\CmsproShare\Controllers\Api;

use App\Apps\CmsproShare\Services\PosterService;
use App\Apps\CmsproShare\Services\ShareConfig;
use App\Apps\CmsproShare\Services\ShareService;
use App\Apps\CmsproShare\Services\TrackService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

/**
 * 前台公开接口 · 供 JS SDK 与业务应用调用
 */
class WidgetController extends Controller
{
    public function __construct(
        private ShareService $shares,
        private ShareConfig $config,
        private TrackService $tracker,
        private PosterService $poster
    ) {
    }

    /**
     * 二维码（SVG）
     */
    public function qrcode(Request $request)
    {
        if (!$this->config->bool('qrcode_enabled', true)) {
            return ApiResponse::error(403, '二维码功能未启用');
        }

        $data = (string) $request->query('data', '');
        if ($data === '') {
            return ApiResponse::error(422, '缺少 data 参数');
        }

        $svg = $this->poster->qrcode($data, (int) $request->query('size', 300));

        return response($svg, 200, ['Content-Type' => 'image/svg+xml; charset=UTF-8']);
    }

    /**
     * 分享海报（SVG）
     */
    public function poster(string $code)
    {
        if (!$this->config->bool('poster_enabled', false)) {
            return ApiResponse::error(403, '分享海报功能未启用，请在应用设置中开启');
        }

        return response($this->poster->poster($code), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
        ]);
    }

    /**
     * 组件数据（供纯 JS SDK 动态渲染）
     */
    public function widget(string $code)
    {
        $data = $this->shares->widget($code);
        if ($data === []) {
            return ApiResponse::error(404, '分享链接不存在或已失效');
        }

        return ApiResponse::success($data);
    }

    /**
     * 前端点击 / 曝光事件上报
     */
    public function track(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:32',
            'event_type' => 'required|string|in:click,landing',
            'channel_code' => 'nullable|string|max:64',
        ]);

        $event = $this->tracker->record(
            (string) $data['code'],
            (string) $data['event_type'],
            $request,
            ['channel_code' => (string) ($data['channel_code'] ?? '')]
        );

        if ($event === null) {
            return ApiResponse::error(404, '分享链接不存在或跟踪未启用');
        }

        return ApiResponse::success(['id' => $event->id], '上报成功');
    }

    /**
     * 业务转化上报
     */
    public function convert(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:32',
            'event' => 'required|string|max:64',
            'user_id' => 'nullable|integer',
        ]);

        $extra = $request->except(['code', 'event']);
        $result = $this->shares->convert((string) $data['code'], (string) $data['event'], $extra);

        return $result['success']
            ? ApiResponse::success($result['data'], '转化已归因')
            : ApiResponse::error(422, $result['message']);
    }
}
