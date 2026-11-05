<?php

namespace App\Apps\CmsproShare\Controllers\Admin;

use App\Apps\CmsproShare\Models\ShareLink;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

/**
 * 后台 · 分享记录
 */
class LinkController extends Controller
{
    public function index()
    {
        return view('cmspro.share::Admin.links.index');
    }

    public function apiList(Request $request)
    {
        $query = ShareLink::query()->orderByDesc('id');

        if (($keyword = trim((string) $request->input('keyword', ''))) !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%")
                    ->orWhere('title', 'like', "%{$keyword}%")
                    ->orWhere('target_id', 'like', "%{$keyword}%");
            });
        }
        if (($scene = trim((string) $request->input('scene_code', ''))) !== '') {
            $query->where('scene_code', $scene);
        }
        if (($channel = trim((string) $request->input('channel_code', ''))) !== '') {
            $query->where('channel_code', $channel);
        }
        if ($request->filled('sharer_id')) {
            $query->where('sharer_id', (int) $request->input('sharer_id'));
        }

        return ApiResponse::paginate($query->paginate((int) $request->input('limit', 20)));
    }

    public function apiDetail(int $id)
    {
        $link = ShareLink::find($id);
        if ($link === null) {
            return ApiResponse::error(404, '分享记录不存在');
        }

        return ApiResponse::success($link->toArray());
    }

    public function apiToggle(int $id)
    {
        $link = ShareLink::find($id);
        if ($link === null) {
            return ApiResponse::error(404, '分享记录不存在');
        }

        $link->update(['status' => $link->status === 1 ? 0 : 1]);

        return ApiResponse::success(['status' => $link->status], '状态已更新');
    }
}
