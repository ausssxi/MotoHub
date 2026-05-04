<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use App\Services\Twitter\NewStockChartService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

final class NewArrivalsOgpController extends Controller
{
    private const CACHE_DIR = 'ogp/new-arrivals';

    public function __construct(
        private readonly NewStockChartService $chartService,
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
