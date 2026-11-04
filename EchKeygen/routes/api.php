<?php

use Illuminate\Support\Facades\Route;

// 管理员接口：POST /plugin/ech-keygen/generate，参数 public_name，返回 JSON。
// 挂面板的 admin 中间件（Sanctum 管理员令牌），因为返回值里含 ECH 私钥。
Route::post('/plugin/ech-keygen/generate', 'EchController@generateApi')->middleware('admin');
