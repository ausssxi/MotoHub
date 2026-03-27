<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlogCacheHeaders
{
    /**
     * 公開ブログページにキャッシュヘッダーを付与
     * - CDN (Cloudflare): 5分キャッシュ
     * - ブラウザ: 1分キャッシュ
     * - ログインユーザーのリクエストはキャッシュしない
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 管理画面やログインユーザーはキャッシュしない
        if ($request->is('admin/*') || $request->user()) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            return $response;
        }

        // 公開ページのみキャッシュヘッダーを付与
        if ($response->isSuccessful()) {
            $response->headers->set('Cache-Control', 'public, max-age=60, s-maxage=300');
            $response->headers->set('Vary', 'Accept-Encoding');
        }

        return $response;
    }
}
