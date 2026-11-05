<?php

use App\Apps\CmsproShare\Controllers\Admin\ChannelController;
use App\Apps\CmsproShare\Controllers\Admin\LinkController;
use App\Apps\CmsproShare\Controllers\Admin\PreviewController;
use App\Apps\CmsproShare\Controllers\Admin\SceneController;
use App\Apps\CmsproShare\Controllers\Admin\SettingController;
use App\Apps\CmsproShare\Controllers\Admin\StatController;
use Illuminate\Support\Facades\Route;

// 后台管理页面（ServiceProvider 已加 web + auth:admin 中间件）
Route::get('/', [StatController::class, 'index']);
Route::get('/channels', [ChannelController::class, 'index']);
Route::get('/scenes', [SceneController::class, 'index']);
Route::get('/links', [LinkController::class, 'index']);
Route::get('/stats', [StatController::class, 'index']);
Route::get('/settings', [SettingController::class, 'index']);
Route::get('/preview', [PreviewController::class, 'index']);
