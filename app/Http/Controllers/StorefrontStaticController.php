<?php

namespace App\Http\Controllers;

class StorefrontStaticController extends Controller
{
    public function home()
    {
        return $this->serve('index.html');
    }

    public function page(string $page)
    {
        $allowed = ['shop', 'cart', 'checkout', 'track', 'about', 'contact', 'faq'];
        abort_unless(in_array($page, $allowed, true), 404);

        return $this->serve($page.'/index.html');
    }

    public function product(string $slug)
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]+$/', $slug) === 1, 404);

        $requested = 'product/'.$slug.'/index.html';
        // The storefront is a static Next export, but product data is fetched from Laravel at runtime.
        // Reuse one generated product shell for slugs added after the last frontend build.
        if (! is_file(public_path('storefront-pages/'.$requested))) {
            $fallback = 'product/portable-juice-blender/index.html';
            if (! is_file(public_path('storefront-pages/'.$fallback))) {
                $matches = glob(public_path('storefront-pages/product/*/index.html')) ?: [];
                if ($matches) $fallback = 'product/'.basename(dirname($matches[0])).'/index.html';
            }
            $requested = $fallback;
        }
        return $this->serve($requested);
    }

    private function serve(string $relativePath)
    {
        $file = public_path('storefront-pages/'.$relativePath);
        // Next App Router may request the pre-rendered React Server Component payload during client navigation.
        if (request()->header('RSC') === '1') {
            $rscFile = preg_replace('/index\\.html$/', 'index.txt', $file);
            if (is_string($rscFile) && is_file($rscFile)) {
                $file = $rscFile;
            }
        }
        if (! is_file($file)) {
            return response(
                '<!doctype html><html><head><meta charset="utf-8"><title>Storefront build required</title></head><body style="font-family:Arial;padding:40px"><h1>Storefront assets are not built yet</h1><p>Run <code>npm run build:laravel</code> inside <code>resources/storefront-original</code>, then refresh this page.</p></body></html>',
                503,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }

        $headers = ['Cache-Control' => 'no-cache'];
        if (request()->header('RSC') === '1') $headers['Content-Type'] = 'text/x-component';
        return response()->file($file, $headers);
    }
}
