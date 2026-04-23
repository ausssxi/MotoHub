<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Station;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RedirectOldStationSlugs
{
    /**
     * 旧駅slug（st-XXXXXX）から新ローマ字slugへ301リダイレクト
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        // /parking/station/st-XXXXXX パターンをチェック
        if (preg_match('#^parking/station/(st-\d+)$#', $path, $matches)) {
            $oldSlug = $matches[1];
            $station = Station::where('old_slug', $oldSlug)->first();

            if ($station && $station->slug !== $oldSlug) {
                return redirect("/parking/station/{$station->slug}", 301);
            }
        }

        return $next($request);
    }
}
