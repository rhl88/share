<?php

namespace App\Apps\CmsproShare\Controllers\Admin;

use App\Apps\CmsproShare\Services\SceneService;
use App\Apps\CmsproShare\Services\ShareConfig;
use App\Apps\CmsproShare\Services\ShareService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\HookManager;
use Illuminate\Http\Request;

/**
 * 后台 · 分享预览
 *
 * 预览采用「完整真实生成」：调用 ShareService::build() 生成真实短链，
 * 每次预览都会写入一条分享记录与 create 事件，会计入数据看板。
 * 内置演示场景保证没有任何业务应用注册 share.types 时也能预览。
 */
class PreviewController extends Controller
{
    /** 内置演示场景标识（不落库，仅请求内通过钩子注入） */
    private const DEMO_SCENE = 'cmspro.share.preview_demo';

    public function __construct(
        private ShareService $shares,
        private SceneService $scenes,
        private ShareConfig $config
    ) {
    }

    public function index()
    {
        return view('cmspro.share::Admin.preview.index');
    }

    /**
     * 预览可选场景：演示场景置顶，其余为业务应用注册的启用场景
     */
    public function apiScenes()
    {
        $items = [[
            'code' => self::DEMO_SCENE,
            'name' => '预览演示内容（无需业务场景）',
            'app_id' => 'cmspro.share',
        ]];

        foreach ($this->scenes->all() as $code => $scene) {
            if ((int) ($scene['status'] ?? 1) !== 1) {
                continue;
            }

            $items[] = [
                'code' => (string) $code,
                'name' => (string) ($scene['name'] ?? $code),
                'app_id' => (string) ($scene['app_id'] ?? ''),
            ];
        }

        return ApiResponse::success($items);
    }

    /**
     * 生成一次真实分享，返回与 share_render / 前端 SDK 一致的数据结构
     */
    public function apiBuild(Request $request)
    {
        $data = $request->validate([
            'scene_code' => 'required|string|max:100',
            'target_id' => 'required|string|max:100',
        ]);

        $sceneCode = (string) $data['scene_code'];
        if ($sceneCode === self::DEMO_SCENE) {
            $this->registerDemoScene();
        }

        $result = $this->shares->build($sceneCode, (string) $data['target_id'], [
            'sharer_type' => 'preview',
        ]);

        if (!$result['success']) {
            return ApiResponse::error(422, $result['message']);
        }

        $preview = $result['data'];
        $preview['flags'] = [
            'qrcode_enabled' => $this->config->bool('qrcode_enabled', true),
            'poster_enabled' => $this->config->bool('poster_enabled', false),
        ];

        return ApiResponse::success($preview);
    }

    /**
     * 请求内注册演示场景：不落库、不影响其它请求
     */
    private function registerDemoScene(): void
    {
        app(HookManager::class)->registerFilter(
            'share.types',
            function ($types) {
                if (!is_array($types)) {
                    $types = [];
                }

                $types[self::DEMO_SCENE] = [
                    'code' => self::DEMO_SCENE,
                    'name' => '预览演示内容',
                    'app_id' => 'cmspro.share',
                    'url_template' => '/',
                    'resolver' => function ($targetId): array {
                        return [
                            'title' => '一键分享效果预览 · 示例内容',
                            'summary' => '这是一条用于预览分享面板与渠道效果的示例内容，本次生成的是真实分享链接。',
                            'image' => '',
                            'url' => url('/'),
                        ];
                    },
                ];

                return $types;
            },
            10,
            'cmspro.share'
        );
    }
}
