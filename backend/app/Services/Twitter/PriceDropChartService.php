<?php

declare(strict_types=1);

namespace App\Services\Twitter;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

final class PriceDropChartService
{
    private const WIDTH = 1200;
    private const HEIGHT = 630;
    private const MINI_W = 570;
    private const MINI_H = 295;
    private const BG_COLOR = '#0f172a';

    public function generateDashboardImage(?Carbon $date = null): ?string
    {
        $date = $date ?? Carbon::today();

        $byMaker = $this->getByMaker($date);
        $byAmount = $this->getByAmountBand($date);
        $byModel = $this->getByModel($date);
        $daily = $this->getDailyTrend($date);

        $charts = [
            $this->fetchChart($this->buildMakerConfig($byMaker)),
            $this->fetchChart($this->buildAmountConfig($byAmount)),
            $this->fetchChart($this->buildModelConfig($byModel)),
            $this->fetchChart($this->buildDailyTrendConfig($daily)),
        ];

        if (!array_filter($charts)) {
            return null;
        }

        $manager = new ImageManager(new Driver());
        $canvas = $manager->create(self::WIDTH, self::HEIGHT)->fill(self::BG_COLOR);

        $positions = [
            [10, 10],    // 左上
            [620, 10],   // 右上
            [10, 325],   // 左下
            [620, 325],  // 右下
        ];

        foreach ($charts as $i => $png) {
            if (!$png) {
                continue;
            }
            $mini = $manager->read($png);
            $canvas->place($mini, 'top-left', $positions[$i][0], $positions[$i][1]);
        }

        return (string) $canvas->toPng();
    }

    // =====================================================================
    // チャート設定ビルダー
    // =====================================================================

    private function buildMakerConfig(Collection $data): array
    {
        $config = [
            'type' => 'horizontalBar',
            'data' => [
                'labels' => $data->pluck('name')->toArray(),
                'datasets' => [[
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => 'rgba(251, 146, 60, 0.7)',
                    'borderColor' => 'rgba(251, 146, 60, 1)',
                    'borderWidth' => 1,
                ]],
            ],
            'options' => $this->chartOptions('メーカー別 値下げ件数', '__TICK_CB_1__'),
        ];

        return ['config' => $config, 'callbacks' => [
            '"__TICK_CB_1__"' => "function(v){return Number.isInteger(v)?v+'台':'';}",
        ]];
    }

    private function buildAmountConfig(Collection $data): array
    {
        $config = [
            'type' => 'bar',
            'data' => [
                'labels' => $data->pluck('label')->toArray(),
                'datasets' => [[
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.7)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'borderWidth' => 1,
                ]],
            ],
            'options' => [
                'legend' => ['display' => false],
                'title' => $this->miniTitle('値下げ額別 分布'),
                'scales' => [
                    'xAxes' => [$this->xAxis()],
                    'yAxes' => [[
                        'gridLines' => $this->gridLines(),
                        'ticks' => array_merge($this->yTicks(), [
                            'beginAtZero' => true,
                            'callback' => '__TICK_CB_2__',
                        ]),
                    ]],
                ],
                'layout' => ['padding' => ['top' => 4, 'right' => 16, 'bottom' => 4, 'left' => 4]],
            ],
        ];

        return ['config' => $config, 'callbacks' => [
            '"__TICK_CB_2__"' => "function(v){return Number.isInteger(v)?v+'台':'';}",
        ]];
    }

    private function buildModelConfig(Collection $data): array
    {
        $config = [
            'type' => 'horizontalBar',
            'data' => [
                'labels' => $data->pluck('name')->toArray(),
                'datasets' => [[
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.7)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 1,
                ]],
            ],
            'options' => $this->chartOptions('車種別 値下げTOP5', '__TICK_CB_3__'),
        ];

        return ['config' => $config, 'callbacks' => [
            '"__TICK_CB_3__"' => "function(v){return Number.isInteger(v)?v+'台':'';}",
        ]];
    }

    private function buildDailyTrendConfig(Collection $data): array
    {
        $config = [
            'type' => 'line',
            'data' => [
                'labels' => $data->pluck('label')->toArray(),
                'datasets' => [[
                    'label' => '値下げ数',
                    'data' => $data->pluck('count')->toArray(),
                    'borderColor' => '#A855F7',
                    'backgroundColor' => 'rgba(168, 85, 247, 0.15)',
                    'fill' => true,
                    'lineTension' => 0.3,
                    'pointRadius' => 5,
                    'pointBackgroundColor' => '#A855F7',
                    'pointBorderColor' => self::BG_COLOR,
                    'pointBorderWidth' => 2,
                    'borderWidth' => 3,
                ]],
            ],
            'options' => [
                'legend' => ['display' => false],
                'title' => $this->miniTitle('値下げ推移（7日間）'),
                'scales' => [
                    'xAxes' => [$this->xAxis()],
                    'yAxes' => [[
                        'gridLines' => $this->gridLines(),
                        'ticks' => array_merge($this->yTicks(), [
                            'beginAtZero' => true,
                            'callback' => '__TICK_CB_4__',
                        ]),
                    ]],
                ],
                'layout' => ['padding' => ['top' => 4, 'right' => 16, 'bottom' => 4, 'left' => 4]],
            ],
        ];

        return ['config' => $config, 'callbacks' => [
            '"__TICK_CB_4__"' => "function(v){return Number.isInteger(v)?v+'台':'';}",
        ]];
    }

    // =====================================================================
    // 共通横棒オプション
    // =====================================================================

    private function chartOptions(string $title, string $tickCallback): array
    {
        return [
            'legend' => ['display' => false],
            'title' => $this->miniTitle($title),
            'scales' => [
                'xAxes' => [[
                    'gridLines' => $this->gridLines(),
                    'ticks' => array_merge($this->yTicks(), [
                        'beginAtZero' => true,
                        'callback' => $tickCallback,
                    ]),
                ]],
                'yAxes' => [[
                    'gridLines' => $this->gridLines(),
                    'ticks' => $this->xTicks(),
                ]],
            ],
            'layout' => ['padding' => ['top' => 4, 'right' => 16, 'bottom' => 4, 'left' => 4]],
        ];
    }

    // =====================================================================
    // 共通スタイル
    // =====================================================================

    private function miniTitle(string $text): array
    {
        return [
            'display' => true,
            'text' => $text,
            'fontColor' => 'rgba(255, 255, 255, 0.8)',
            'fontSize' => 14,
            'fontStyle' => 'bold',
            'padding' => 12,
        ];
    }

    private function gridLines(): array
    {
        return [
            'color' => 'rgba(255, 255, 255, 0.06)',
            'zeroLineColor' => 'rgba(255, 255, 255, 0.06)',
        ];
    }

    private function xAxis(): array
    {
        return [
            'gridLines' => $this->gridLines(),
            'ticks' => $this->xTicks(),
        ];
    }

    private function xTicks(): array
    {
        return [
            'fontColor' => 'rgba(255, 255, 255, 0.6)',
            'fontSize' => 12,
            'fontStyle' => 'bold',
        ];
    }

    private function yTicks(): array
    {
        return [
            'fontColor' => 'rgba(255, 255, 255, 0.5)',
            'fontSize' => 11,
            'maxTicksLimit' => 6,
        ];
    }

    // =====================================================================
    // QuickChart API
    // =====================================================================

    private function fetchChart(array $spec): ?string
    {
        $chartJson = json_encode($spec['config'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($spec['callbacks'] ?? [] as $placeholder => $jsFunc) {
            $chartJson = str_replace($placeholder, $jsFunc, $chartJson);
        }

        $body = json_encode([
            'backgroundColor' => self::BG_COLOR,
            'width' => self::MINI_W,
            'height' => self::MINI_H,
            'devicePixelRatio' => 1,
            'chart' => $chartJson,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = Http::timeout(15)
            ->withBody($body, 'application/json')
            ->post('https://quickchart.io/chart');

        return $response->successful() ? $response->body() : null;
    }

    // =====================================================================
    // データ取得
    // =====================================================================

    private function getByMaker(Carbon $date): Collection
    {
        return DB::table('price_histories')
            ->join('listings', 'price_histories.listing_id', '=', 'listings.id')
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->join('manufacturers', 'bike_models.manufacturer_id', '=', 'manufacturers.id')
            ->selectRaw('manufacturers.name, COUNT(*) as count')
            ->where('listings.is_sold_out', false)
            ->whereDate('price_histories.created_at', $date)
            ->groupBy('manufacturers.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
    }

    private function getByAmountBand(Carbon $date): Collection
    {
        $bands = [
            ['max' => 10000, 'label' => '〜1万'],
            ['min' => 10000, 'max' => 30000, 'label' => '1〜3万'],
            ['min' => 30000, 'max' => 50000, 'label' => '3〜5万'],
            ['min' => 50000, 'max' => 100000, 'label' => '5〜10万'],
            ['min' => 100000, 'label' => '10万〜'],
        ];

        $results = [];
        foreach ($bands as $band) {
            $query = DB::table('price_histories')
                ->join('listings', 'price_histories.listing_id', '=', 'listings.id')
                ->where('listings.is_sold_out', false)
                ->whereDate('price_histories.created_at', $date)
                ->whereRaw('(price_histories.old_price - price_histories.new_price) > 0');

            if (isset($band['min'])) {
                $query->whereRaw('(price_histories.old_price - price_histories.new_price) >= ?', [$band['min']]);
            }
            if (isset($band['max'])) {
                $query->whereRaw('(price_histories.old_price - price_histories.new_price) < ?', [$band['max']]);
            }

            $results[] = (object) [
                'label' => $band['label'],
                'count' => $query->count(),
            ];
        }

        return collect($results);
    }

    private function getByModel(Carbon $date): Collection
    {
        return DB::table('price_histories')
            ->join('listings', 'price_histories.listing_id', '=', 'listings.id')
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->selectRaw('bike_models.name, COUNT(*) as count')
            ->where('listings.is_sold_out', false)
            ->whereDate('price_histories.created_at', $date)
            ->whereNotNull('listings.bike_model_id')
            ->groupBy('bike_models.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
    }

    private function getDailyTrend(Carbon $date): Collection
    {
        return DB::table('price_histories')
            ->join('listings', 'price_histories.listing_id', '=', 'listings.id')
            ->selectRaw("DATE(price_histories.created_at) as day, DATE_FORMAT(price_histories.created_at, '%m/%d') as label, COUNT(*) as count")
            ->where('listings.is_sold_out', false)
            ->where('price_histories.created_at', '>=', $date->copy()->subDays(6)->startOfDay())
            ->where('price_histories.created_at', '<=', $date->copy()->endOfDay())
            ->groupByRaw("DATE(price_histories.created_at), DATE_FORMAT(price_histories.created_at, '%m/%d')")
            ->orderBy('day')
            ->get();
    }
}
