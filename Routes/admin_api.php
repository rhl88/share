<?php

use App\Apps\CmsproShare\Controllers\Admin\ChannelController;
use App\Apps\CmsproShare\Controllers\Admin\LinkController;
use App\Apps\CmsproShare\Controllers\Admin\SceneController;
use App\Apps\CmsproShare\Controllers\Admin\SettingController;
use App\Apps\CmsproShare\Controllers\Admin\StatController;
use Illuminate\Support\Facades\Route;

// 后台管理接口：/api/admin/cmspro/share/*
Route::get('/channels', [ChannelController::class, 'apiList']);
Route::post('/channels/save', [ChannelController::class, 'apiSave']);
Route::post('/channels/seed', [ChannelController::class, 'apiSeed']);

Route::get('/scenes', [SceneController::class, 'apiList']);
Route::post('/scenes/save', [SceneController::class, 'apiSave']);
Route::post('/scenes/sync', [SceneController::class, 'apiSync']);

Route::get('/links', [LinkController::class, 'apiList']);
Route::get('/links/{id}', [LinkController::class, 'apiDetail']);
Route::post('/links/{id}/toggle', [LinkController::class, 'apiToggle']);

Route::get('/stats', [StatController::class, 'apiStats']);

Route::get('/settings', [SettingController::class, 'apiList']);
Route::post('/settings', [SettingController::class, 'apiSave']);
