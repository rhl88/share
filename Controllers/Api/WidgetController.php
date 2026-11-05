<?php

namespace App\Apps\CmsproShare\Controllers\Api;

use App\Apps\CmsproShare\Services\PosterService;
use App\Apps\CmsproShare\Services\ShareConfig;
use App\Apps\CmsproShare\Services\ShareService;
use App\Apps\CmsproShare\Services\TrackService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        // 手动校验：调用方（JS SDK fetch 分支 / 第三方宿主）不带 Accept: application/json，
        // 若使用 $request->validate()，失败时 ValidationException 会转成 302 重定向 HTML
        // （常见问题第 1 章 / F61），破坏接口 JSON 契约。
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:32',
            'event_type' => 'required|string|in:click,landing',
            'channel_code' => 'nullable|string|max:64',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error(422, $validator->errors()->first());
        }

        $data = $validator->validated();

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
        // 同 track：手动校验保证校验失败时始终返回 422 JSON
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:32',
            'event' => 'required|string|max:64',
            'user_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error(422, $validator->errors()->first());
        }

        $data = $validator->validated();

        $extra = $request->except(['code', 'event']);
        $result = $this->shares->convert((string) $data['code'], (string) $data['event'], $extra);

        return $result['success']
            ? ApiResponse::success($result['data'], '转化已归因')
            : ApiResponse::error(422, $result['message']);
    }
}
