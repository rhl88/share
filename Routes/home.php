<?php

use App\Apps\CmsproShare\Controllers\Home\RedirectController;
use Illuminate\Support\Facades\Route;

// 前台中转跳转：/{access_path}/{code}，默认 /share/{code}
Route::get('/{code}', [RedirectController::class, 'go'])->where('code', '[A-Za-z0-9_-]+');
