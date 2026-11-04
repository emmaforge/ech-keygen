<?php

use Illuminate\Support\Facades\Route;
use Plugin\EchKeygen\Controllers\AssetController;
use Plugin\EchKeygen\Controllers\EchController;
use Plugin\EchKeygen\Plugin;

// 仪表盘注入脚本（由插件自己的控制器输出，中间件只往后台入口页插一行引用）
Route::get(Plugin::SCRIPT_PATH, [AssetController::class, 'script'])
    ->name('plugin.ech_keygen.script');

// ECH 生成页面：先用插件设置里的访问密码解锁，再填 public_name 生成。
Route::get(Plugin::PAGE_PATH, [EchController::class, 'page']);
Route::post(Plugin::PAGE_PATH, [EchController::class, 'submit']);
