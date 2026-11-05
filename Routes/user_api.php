<?php

use App\Apps\CmsproShare\Controllers\User\ShareController;
use Illuminate\Support\Facades\Route;

// 用户端接口：/api/user/cmspro/share/*
Route::get('/mine', [ShareController::class, 'apiMine']);
Route::get('/invites', [ShareController::class, 'apiInvites']);
