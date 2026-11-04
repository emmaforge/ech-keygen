<?php

namespace Plugin\EchKeygen;

use App\Services\Plugin\AbstractPlugin;
use Illuminate\Support\Facades\File;
use Plugin\EchKeygen\Middleware\AdminScriptMiddleware;

class Plugin extends AbstractPlugin
{
    public const SCRIPT_PATH = '/plugin/ech-keygen/admin.js';
    public const PAGE_PATH = '/ech-keygen';
    public const MARKER = '<!-- xboard:ech_keygen -->';
    private static bool $injectionEnabled = true;

    public function boot(): void
    {
        self::$injectionEnabled = filter_var(
            $this->getConfig('dashboard_inject', true),
            FILTER_VALIDATE_BOOLEAN
        );

        try {
            app('router')->pushMiddlewareToGroup('web', AdminScriptMiddleware::class);
        } catch (\Throwable) {
            // 控制台启动时 router 可能不可用，忽略。
        }
    }

    public static function adminPath(): string
    {
        return trim((string) admin_setting(
            'secure_path',
            admin_setting('frontend_admin_path', hash('crc32b', config('app.key')))
        ), '/');
    }

    public static function dashboardInjectionEnabled(): bool
    {
        return self::$injectionEnabled;
    }

    public static function currentVersion(): string
    {
        $path = __DIR__ . '/config.json';
        $config = File::exists($path) ? json_decode((string) File::get($path), true) : null;

        return (string) ($config['version'] ?? '0.0.0');
    }
}
