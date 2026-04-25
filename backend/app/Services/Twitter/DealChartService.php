<?php

declare(strict_types=1);

namespace App\Services\Twitter;

use App\Models\Listing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class DealChartService
{
    private const WIDTH = 1200;
    private const HEIGHT = 630;

    /**
     * Listing から相場推移チャートの PNG バイナリを生成して返す
     */
    public function generateChartImage(Listing $listing): ?string
    {
        $listing->loadMissing(['bikeModel.manufacturer']);

        $modelName = $listing->bikeModel?->name ?? $listing->title ?? '車種名不明';
        $listingPrice = (float) ($listing->total_price ?: $listing->price);

        $monthly = $listing->bike_model_id
            ? $this->getMonthlyAveragePrices($listing->bike_model_id)
            : collect();

        if ($monthly->count() < 6) {
            $avgPrice = $this->getAveragePrice($listing) ?: $listingPrice;
            if (!$avgPrice) {
                return null;
            }
            $monthly = $this->generateDummyMonthlyData($avgPrice);
        }

        $labels = $monthly->pluck('month_label')->toArray();
        $prices = $monthly->pluck('avg_price')->map(fn ($v) => round((float) $v / 10000, 1))->toArray();
        $listingPriceMan = round($listingPrice / 10000, 1);

        return $this->fetchChartImage($labels, $prices, $listingPriceMan, $modelName);
    }

    private function fetchChartImage(array $labels, array $prices, float $listingPriceMan, string $modelName): ?string
    {
        $title = "{$modelName} 相場推移";

        $avgOfPrices = count($prices) > 0 ? array_sum($prices) / count($prices) : 0;
        $labelPosition = $listingPriceMan >= $avgOfPrices ? 'bottom' : 'top';

        $annotations = [];
        if ($listingPriceMan > 0) {
            $annotations[] = [
                'type' => 'line',
                'mode' => 'horizontal',
                'scaleID' => 'y-axis-0',
                'value' => $listingPriceMan,
                'borderColor' => 'rgba(239, 68, 68, 0.85)',
                'borderWidth' => 3,
                'borderDash' => [10, 5],
                'label' => [
                    'enabled' => true,
                    'content' => "この車両 {$listingPriceMan}万円",
                    'position' => 'left',
                    'yAdjust' => $labelPosition === 'bottom' ? 16 : -16,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.92)',
                    'fontColor' => '#ffffff',
                    'fontSize' => 16,
                    'fontStyle' => 'bold',
                    'cornerRadius' => 4,
                    'xPadding' => 10,
                    'yPadding' => 6,
                ],
            ];
        }

        $chart = [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => '平均価格',
                    'data' => $prices,
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                    'fill' => true,
                    'lineTension' => 0.35,
                    'pointRadius' => 7,
                    'pointBackgroundColor' => '#3B82F6',
                    'pointBorderColor' => '#0f172a',
                    'pointBorderWidth' => 3,
                    'borderWidth' => 4,
                ]],
            ],
            'options' => [
                'legend' => ['display' => false],
                'title' => [
                    'display' => true,
                    'text' => $title,
                    'fontColor' => 'rgba(255, 255, 255, 0.8)',
                    'fontSize' => 24,
                    'fontStyle' => 'bold',
                    'padding' => 20,
                ],
                'annotation' => [
                    'annotations' => $annotations,
                ],
                'scales' => [
                    'xAxes' => [[
                        'gridLines' => [
                            'color' => 'rgba(255, 255, 255, 0.06)',
                            'zeroLineColor' => 'rgba(255, 255, 255, 0.06)',
                        ],
                        'ticks' => [
                            'fontColor' => 'rgba(255, 255, 255, 0.65)',
                            'fontSize' => 16,
                            'fontStyle' => 'bold',
                        ],
                    ]],
                    'yAxes' => [[
                        'gridLines' => [
                            'color' => 'rgba(255, 255, 255, 0.06)',
                            'zeroLineColor' => 'rgba(255, 255, 255, 0.06)',
                        ],
                        'ticks' => [
                            'fontColor' => 'rgba(255, 255, 255, 0.5)',
                            'fontSize' => 14,
                            'maxTicksLimit' => 6,
                            'callback' => '__TICK_CALLBACK__',
                        ],
                    ]],
                ],
                'layout' => [
                    'padding' => ['top' => 8, 'right' => 36, 'bottom' => 8, 'left' => 8],
                ],
            ],
        ];

        $chartJson = json_encode($chart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $chartJson = str_replace(
            '"__TICK_CALLBACK__"',
            "function(v) { return v % 1 === 0 ? v + '万' : v.toFixed(1) + '万'; }",
            $chartJson,
        );

        $body = json_encode([
            'backgroundColor' => '#0f172a',
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'devicePixelRatio' => 2,
            'chart' => $chartJson,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = Http::timeout(15)
            ->withBody($body, 'application/json')
            ->post('https://quickchart.io/chart');

        return $response->successful() ? $response->body() : null;
    }

    private function generateDummyMonthlyData(float $avgPrice): \Illuminate\Support\Collection
    {
        $rows = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $variation = $avgPrice * (mt_rand(-50, 50) / 1000);
            $rows[] = (object) [
                'month' => $date->format('Y-m'),
                'avg_price' => round($avgPrice + $variation),
                'month_label' => $date->format('m') . '月',
            ];
        }

        return collect($rows);
    }

    private function getMonthlyAveragePrices(int $bikeModelId): \Illuminate\Support\Collection
    {
        return DB::table('listings')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, AVG(total_price) as avg_price, DATE_FORMAT(created_at, '%m月') as month_label")
            ->where('bike_model_id', $bikeModelId)
            ->where('is_sold_out', false)
            ->where('total_price', '>', 0)
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%m月')")
            ->orderBy('month')
            ->get();
    }

    private function getAveragePrice(Listing $listing): ?float
    {
        if (!$listing->bike_model_id) {
            return null;
        }

        return Cache::remember(
            "deal_chart_avg_{$listing->bike_model_id}",
            3600,
            fn () => (float) Listing::where('bike_model_id', $listing->bike_model_id)
                ->where('is_sold_out', false)
                ->where('total_price', '>', 0)
                ->where('id', '!=', $listing->id)
                ->avg('total_price')
        );
    }
}
