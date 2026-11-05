<?php

use App\Apps\CmsproShare\Controllers\Api\WidgetController;
use Illuminate\Support\Facades\Route;

// 前台公开接口：/api/cmspro/share/*
Route::get('/qrcode', [WidgetController::class, 'qrcode']);
Route::get('/poster/{code}', [WidgetController::class, 'poster']);
Route::get('/widget/{code}', [WidgetController::class, 'widget']);
Route::post('/track', [WidgetController::class, 'track']);
Route::post('/convert', [WidgetController::class, 'convert']);
