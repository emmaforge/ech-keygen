<?php

namespace Plugin\EchKeygen\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Plugin\EchKeygen\Plugin;

class AssetController extends Controller
{
    public function script()
    {
        $path = dirname(__DIR__) . '/resources/js/admin.js';
        if (!File::exists($path)) {
            return response('// ech_keygen: missing script', 404)
                ->header('Content-Type', 'application/javascript; charset=utf-8');
        }

        return response(File::get($path), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
            'X-Plugin-Version' => Plugin::currentVersion(),
        ]);
    }
}
