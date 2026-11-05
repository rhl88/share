<?php

use App\Apps\CmsproShare\Controllers\User\ShareController;
use Illuminate\Support\Facades\Route;

// 用户端页面：/user/cmspro/share
Route::get('/', [ShareController::class, 'index']);
