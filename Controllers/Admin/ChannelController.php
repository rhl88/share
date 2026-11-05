<?php

namespace App\Apps\CmsproShare\Controllers\Admin;

use App\Apps\CmsproShare\Models\Channel;
use App\Apps\CmsproShare\Services\ChannelService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\HookManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * 后台 · 渠道管理
 */
class ChannelController extends Controller
{
    public function __construct(private ChannelService $channels)
    {
    }

    public function index()
    {
        return view('cmspro.share::Admin.channels.index');
    }

    /**
     * 渠道列表（含内置、数据库覆盖、钩子追加的自定义渠道）
     */
    public function apiList()
    {
        if (Schema::hasTable('app_cmspro_share_channels')) {
            $this->channels->seedDefaults();
        }

        $items = [];
        if (Schema::hasTable('app_cmspro_share_channels')) {
            foreach (Channel::orderBy('sort')->get() as $row) {
                $items[$row->code] = [
                    'code' => $row->code,
                    'name' => $row->name,
                    'region' => $row->region,
                    'mode' => $row->mode,
                    'template' => $row->template,
                    'icon' => $row->icon,
                    'sort' => (int) $row->sort,
                    'status' => (int) $row->status,
                    'is_builtin' => (int) $row->is_builtin,
                    'source' => $row->is_builtin ? '内置' : '自定义',
                ];
            }
        }

        // 钩子注册的渠道（无需落库即可在后台查看）
        $hooked = app(HookManager::class)->applyFilter('share.channels', []);
        foreach (is_array($hooked) ? $hooked : [] as $code => $channel) {
            if (is_array($channel) && !isset($items[$code])) {
                $items[$code] = array_merge([
                    'code' => $code, 'name' => $code, 'region' => 'common',
                    'mode' => 'link', 'template' => '', 'icon' => '', 'sort' => 0,
                    'status' => 1, 'is_builtin' => 0, 'source' => '钩子注册',
                ], $channel);
            }
        }

        return ApiResponse::success(array_values($items));
    }

    /**
     * 保存渠道启停 / 排序 / 模板
     */
    public function apiSave(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:64',
            'status' => 'nullable|in:0,1',
            'sort' => 'nullable|integer|min:0|max:9999',
            'name' => 'nullable|string|max:100',
            'template' => 'nullable|string|max:2000',
            'icon' => 'nullable|string|max:100',
        ]);

        $code = (string) $data['code'];
        $this->channels->saveOverride($code, $data);

        return ApiResponse::success(['code' => $code], '保存成功');
    }

    /**
     * 重新初始化内置渠道种子
     */
    public function apiSeed()
    {
        return ApiResponse::success(['created' => $this->channels->seedDefaults()], '内置渠道已同步');
    }
}
