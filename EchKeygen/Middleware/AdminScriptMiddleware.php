<?php

namespace Plugin\EchKeygen\Middleware;

use Closure;
use Illuminate\Http\Request;
use Plugin\EchKeygen\Plugin;
use Symfony\Component\HttpFoundation\Response;

class AdminScriptMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->injectAdminScript($request, $response);
        } catch (\Throwable) {
            // 注入失败时保证后台页面照常打开。
        }

        return $response;
    }

    private function injectAdminScript(Request $request, Response $response): void
    {
        // 只处理后台入口页本身（路径等于后台安全路径的那份 HTML）。
        if (!$request->isMethod('GET') || trim($request->path(), '/') !== Plugin::adminPath()) {
            return;
        }
        if ($response->getStatusCode() !== 200 || !Plugin::dashboardInjectionEnabled()) {
            return;
        }

        $contentType = (string) $response->headers->get('Content-Type');
        if ($contentType !== '' && !str_contains(strtolower($contentType), 'text/html')) {
            return;
        }
        if (!method_exists($response, 'getContent') || !method_exists($response, 'setContent')) {
            return;
        }

        $html = $response->getContent();
        if (!is_string($html) || !str_contains($html, 'id="root"')
            || !str_contains($html, 'window.settings') || str_contains($html, Plugin::MARKER)) {
            return;
        }

        $position = stripos($html, '<script type="module"');
        if ($position === false) {
            $position = strripos($html, '</body>');
        }
        if ($position === false) {
            return;
        }

        $options = json_encode([
            'pagePath' => Plugin::PAGE_PATH,
            'version' => Plugin::currentVersion(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $tag = Plugin::MARKER . "\n"
            . '<script>window.__XBOARD_ECH_KEYGEN__=' . $options . ';</script>' . "\n"
            . '<script src="' . e(Plugin::SCRIPT_PATH . '?v=' . Plugin::currentVersion()) . '"></script>' . "\n";

        $response->setContent(substr($html, 0, $position) . $tag . substr($html, $position));
        $response->headers->remove('Content-Length');
    }
}
