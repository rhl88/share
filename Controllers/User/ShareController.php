<?php

namespace App\Apps\CmsproShare\Controllers\User;

use App\Apps\CmsproShare\Models\InviteRelation;
use App\Apps\CmsproShare\Models\ShareLink;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

/**
 * 用户端 · 我的分享 / 我的邀请
 */
class ShareController extends Controller
{
    public function index()
    {
        return view('cmspro.share::User.shares.index');
    }

    public function apiMine(Request $request)
    {
        $links = ShareLink::where('sharer_id', (int) auth('web')->id())
            ->orderByDesc('id')
            ->paginate((int) $request->input('limit', 20));

        return ApiResponse::paginate($links);
    }

    public function apiInvites(Request $request)
    {
        $invites = InviteRelation::where('inviter_id', (int) auth('web')->id())
            ->orderByDesc('id')
            ->paginate((int) $request->input('limit', 20));

        return ApiResponse::paginate($invites);
    }
}
