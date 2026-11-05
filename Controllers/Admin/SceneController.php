<?php

namespace App\Apps\CmsproShare\Controllers\Admin;

use App\Apps\CmsproShare\Models\Scene;
use App\Apps\CmsproShare\Services\SceneService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * 后台 · 场景（内容类型）管理
 *
 * 场景来源为业务应用的 share.types 钩子；后台只做启用、排序覆盖与快照查看。
 */
class SceneController extends Controller
{
    public function __construct(private SceneService $scenes)
    {
    }

    public function index()
    {
        return view('cmspro.share::Admin.scenes.index');
    }

    public function apiList()
    {
        $items = [];
        foreach ($this->scenes->all() as $code => $scene) {
            $resolver = $scene['resolver'] ?? null;
            $items[] = [
                'code' => (string) $code,
                'name' => (string) ($scene['name'] ?? $code),
                'app_id' => (string) ($scene['app_id'] ?? ''),
                'status' => (int) ($scene['status'] ?? 1),
                'sort' => (int) ($scene['sort'] ?? 0),
                'resolver' => is_string($resolver) ? $resolver : ($resolver instanceof \Closure ? '闭包解析器（运行时）' : '类方法解析器（运行时）'),
                'url_template' => (string) ($scene['url_template'] ?? ''),
            ];
        }

        return ApiResponse::success($items);
    }

    /**
     * 保存场景启用状态与排序（后台覆盖钩子注册值）
     */
    public function apiSave(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:100',
            'status' => 'nullable|in:0,1',
            'sort' => 'nullable|integer|min:0|max:9999',
        ]);

        if (!Schema::hasTable('app_cmspro_share_scenes')) {
            return ApiResponse::error(500, '场景表不存在，请先完成应用安装');
        }

        $code = (string) $data['code'];
        $scene = $this->scenes->all()[$code] ?? [];

        Scene::updateOrCreate(
            ['code' => $code],
            [
                'name' => (string) ($scene['name'] ?? $code),
                'app_id' => (string) ($scene['app_id'] ?? ''),
                'status' => (int) ($data['status'] ?? 1),
                'sort' => (int) ($data['sort'] ?? 0),
            ]
        );

        return ApiResponse::success(['code' => $code], '保存成功');
    }

    /**
     * 把钩子注册的场景快照同步到数据库
     */
    public function apiSync()
    {
        return ApiResponse::success(['created' => $this->scenes->syncFromHooks()], '场景已同步');
    }
}
