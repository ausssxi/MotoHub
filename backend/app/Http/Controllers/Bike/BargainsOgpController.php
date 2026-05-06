<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use App\Models\BikeModel;
use App\Models\Listing;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

final class BargainsOgpController extends Controller
{
    private const CACHE_DIR = 'ogp/bargains';
    private const DISCOUNT_THRESHOLD = 0.8;

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

        $png = $this->generateImage();
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

    private function generateImage(): ?string
    {
        // 「他車種」カテゴリを除外
        $excludedModelIds = BikeModel::where('name', '他車種')->pluck('id');

        // お買い得車両数を取得
        $modelAverages = Listing::where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->whereNotIn('bike_model_id', $excludedModelIds)
            ->select('bike_model_id', DB::raw('AVG(total_price) as avg_price'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('bike_model_id')
            ->having('cnt', '>=', 3)
            ->pluck('avg_price', 'bike_model_id');

        $count = 0;
        $maxOff = 0;

        Listing::where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->whereIn('bike_model_id', $modelAverages->keys())
            ->orderByDesc('created_at')
            ->limit(500)
            ->chunk(100, function ($listings) use ($modelAverages, &$count, &$maxOff) {
                foreach ($listings as $listing) {
                    $avgPrice = (float) $modelAverages[$listing->bike_model_id];
                    if ($avgPrice > 0 && $listing->total_price < ($avgPrice * self::DISCOUNT_THRESHOLD)) {
                        $count++;
                        $off = (int) round((($avgPrice - $listing->total_price) / $avgPrice) * 100);
                        if ($off > $maxOff) {
                            $maxOff = $off;
                        }
                    }
                }
            });

        $chartConfig = [
            'type' => 'bar',
            'data' => [
                'labels' => ['お買い得車両'],
                'datasets' => [[
                    'label' => '台数',
                    'data' => [$count],
                    'backgroundColor' => '#dc2626',
                    'borderRadius' => 8,
                ]],
            ],
            'options' => [
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => "お買い得バイク {$count}台 | 最大{$maxOff}%OFF | MotoHub",
                        'font' => ['size' => 28, 'weight' => 'bold'],
                    ],
                    'subtitle' => [
                        'display' => true,
                        'text' => '相場より20%以上安い掘り出し物を毎日更新',
                        'font' => ['size' => 16],
                    ],
                ],
                'scales' => [
                    'y' => ['display' => false],
                    'x' => ['display' => false],
                ],
            ],
        ];

        try {
            $response = Http::timeout(15)->post('https://quickchart.io/chart', [
                'chart' => json_encode($chartConfig),
                'width' => 1200,
                'height' => 630,
                'backgroundColor' => '#ffffff',
                'format' => 'png',
            ]);

            return $response->successful() ? $response->body() : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
