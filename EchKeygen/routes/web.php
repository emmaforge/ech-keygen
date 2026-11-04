<?php

use Illuminate\Support\Facades\Route;

// ECH 生成页面：先用插件设置里的访问密码解锁，再填 public_name 生成。
Route::get('/ech-keygen', 'EchController@page');
Route::post('/ech-keygen', 'EchController@submit');
