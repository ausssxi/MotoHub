<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Services\Twitter\DealChartService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class DealOgpController extends Controller
{
    private const CACHE_DIR = 'ogp/deals';

    public function __construct(
        private readonly DealChartService $chartService,
    ) {}

    public function show(Listing $listing)
    {
        $cachePath = self::CACHE_DIR . "/{$listing->id}.png";

        if (Storage::disk('public')->exists($cachePath)) {
            return $this->imageResponse(Storage::disk('public')->path($cachePath));
        }

        $listing->loadMissing(['bikeModel.manufacturer']);

        // お買い得割引率を算出（TweetBargainsと同じロジック）
        $percentOff = 0;
        if ($listing->bike_model_id && $listing->total_price > 0) {
            $averagePrice = (float) Listing::where('bike_model_id', $listing->bike_model_id)
                ->where('is_sold_out', false)
                ->where('id', '!=', $listing->id)
                ->where('total_price', '>', 0)
                ->avg('total_price');

            if ($averagePrice > 0) {
                $percentOff = (int) round((($averagePrice - $listing->total_price) / $averagePrice) * 100);
            }
        }

        // 20%以上安ければCombined画像（左テキスト＋右チャート）、それ以外はチャートのみ
        $png = $percentOff >= 20
            ? $this->chartService->generateCombinedImage($listing, $percentOff)
            : $this->chartService->generateChartImage($listing);

        if (!$png) {
            abort(404);
        }

        Storage::disk('public')->makeDirectory(self::CACHE_DIR);
        Storage::disk('public')->put($cachePath, $png);

        return $this->imageResponse(Storage::disk('public')->path($cachePath));
    }

    private function imageResponse(string $path): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
