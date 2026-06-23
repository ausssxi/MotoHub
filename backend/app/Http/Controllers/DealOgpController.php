<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Services\Bike\RegionalBargainService;
use App\Services\Twitter\DealChartService;
use Illuminate\Support\Facades\Storage;

final class DealOgpController extends Controller
{
    // v2: お得率を avg → 全国中央値ベースへ刷新。旧94%等のPNGキャッシュを破棄するためパスをbump。
    private const CACHE_DIR = 'ogp/deals/v2';

    public function __construct(
        private readonly DealChartService $chartService,
    ) {}

    public function show(Listing $listing)
    {
        $cachePath = self::CACHE_DIR."/{$listing->id}.png";

        if (Storage::disk('public')->exists($cachePath)) {
            return $this->imageResponse(Storage::disk('public')->path($cachePath));
        }

        $listing->loadMissing(['bikeModel.manufacturer']);

        // お買い得割引率＝全国中央値ベース（robust20・上限50%）。旧 avg ベース（外れ値で94%が出た）を撤去。
        $bargain = app(RegionalBargainService::class)->forListing(
            $listing->bike_model_id,
            $listing->total_price !== null ? (int) $listing->total_price : null
        );
        $percentOff = $bargain['pct'] ?? 0;

        // 20%以上安ければCombined画像（左テキスト＋右チャート）、それ以外はチャートのみ
        $png = $percentOff >= 20
            ? $this->chartService->generateCombinedImage($listing, $percentOff)
            : $this->chartService->generateChartImage($listing);

        if (! $png) {
            // チャート生成不可（価格データ皆無など稀なケース）でも販売店写真は使わない。
            // サイト共通のOGP画像にフォールバックし、OGPカードが必ず有効になるようにする。
            return $this->imageResponse(public_path('images/about-ogp.png'));
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
