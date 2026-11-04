<?php

namespace Plugin\EchKeygen;

use App\Services\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin
{
    public function boot(): void
    {
        // 本插件不注册钩子，功能全部在路由里：
        // - 页面：GET/POST /ech-keygen（routes/web.php）
        // - 接口：POST /plugin/ech-keygen/generate（routes/api.php，管理员鉴权）
    }
}
