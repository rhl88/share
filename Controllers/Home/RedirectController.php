<?php

namespace App\Apps\CmsproShare\Controllers\Home;

use App\Apps\CmsproShare\Services\ShareService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * 前台 · 分享中转跳转
 *
 * 记录落地事件、绑定裂变邀请关系后跳转到内容原链接；
 * 链接失效时展示友好提示页，而非空白或报错。
 */
class RedirectController extends Controller
{
    public function __construct(private ShareService $shares)
    {
    }

    public function go(string $code, Request $request)
    {
        $target = $this->shares->landing($code, $request);

        if ($target === null || $target === '') {
            return view('cmspro.share::Home.expired');
        }

        return redirect()->away($target);
    }
}
