<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use App\Services\Twitter\PriceDropChartService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

final class PriceDropsOgpController extends Controller
{
    private const CACHE_DIR = 'ogp/price-drops';

    public function __construct(
        private readonly PriceDropChartService $chartService,
    ) {}

    public function show()
    {
        $date = Carbon::today();
        $cachePath = self::CACHE_DIR . "/{$date->toDateString()}.png";

        if (Storage::disk('public')->exists($cachePath)) {
            return response()->file(Storage::disk('public')->path($cachePath), [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        $png = $this->chartService->generateDashboardImage($date);
        if (!$png) {
            abort(404);
        }

        Storage::disk('public')->makeDirectory(self::CACHE_DIR);
        Storage::disk('public')->put($cachePath, $png);

        return response()->file(Storage::disk('public')->path($cachePath), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
