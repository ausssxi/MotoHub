<?php

declare(strict_types=1);

namespace App\Services\Twitter;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

final class NewStockChartService
{
    private const WIDTH = 1200;
    private const HEIGHT = 630;
    private const MINI_W = 570;
    private const MINI_H = 295;
    private const BG_COLOR = '#0f172a';

    public function generateDashboardImage(): ?string
    {
        $since = now()->subDay();

        $byMaker = $this->getByMaker($since);
        $byPrice = $this->getByPrice($since);
        $byModel = $this->getByModel($since);
        $daily = $this->getDailyTrend();

        $charts = [
            $this->fetchChart($this->buildMakerConfig($byMaker)),
            $this->fetchChart($this->buildPriceConfig($byPrice)),
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

    public function getTotalCount(): int
    {
        return DB::table('listings')
            ->where('is_sold_out', false)
            ->where('created_at', '>=', now()->subDay())
            ->count();
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
            'options' => $this->chartOptions('メーカー別 新着入荷', '__TICK_CB_1__'),
        ];

        return ['config' => $config, 'callbacks' => [
            '"__TICK_CB_1__"' => "function(v){return Number.isInteger(v)?v+'台':'';}",
        ]];
    }

    private function buildPriceConfig(Collection $data): array
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
                'title' => $this->miniTitle('価格帯別 新着'),
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
            'options' => $this->chartOptions('車種別 新着TOP5', '__TICK_CB_3__'),
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
                    'label' => '入荷数',
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
                'title' => $this->miniTitle('入荷推移（7日間）'),
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

    private function getByMaker(\Carbon\Carbon $since): Collection
    {
        return DB::table('listings')
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->join('manufacturers', 'bike_models.manufacturer_id', '=', 'manufacturers.id')
            ->selectRaw('manufacturers.name, COUNT(*) as count')
            ->where('listings.is_sold_out', false)
            ->where('listings.created_at', '>=', $since)
            ->groupBy('manufacturers.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
    }

    private function getByPrice(\Carbon\Carbon $since): Collection
    {
        $bands = [
            ['max' => 200000, 'label' => '〜20万'],
            ['min' => 200000, 'max' => 400000, 'label' => '20〜40万'],
            ['min' => 400000, 'max' => 600000, 'label' => '40〜60万'],
            ['min' => 600000, 'max' => 800000, 'label' => '60〜80万'],
            ['min' => 800000, 'label' => '80万〜'],
        ];

        $results = [];
        foreach ($bands as $band) {
            $query = DB::table('listings')
                ->where('is_sold_out', false)
                ->where('total_price', '>', 0)
                ->where('created_at', '>=', $since);

            if (isset($band['min'])) {
                $query->where('total_price', '>=', $band['min']);
            }
            if (isset($band['max'])) {
                $query->where('total_price', '<', $band['max']);
            }

            $results[] = (object) [
                'label' => $band['label'],
                'count' => $query->count(),
            ];
        }

        return collect($results);
    }

    private function getByModel(\Carbon\Carbon $since): Collection
    {
        return DB::table('listings')
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->selectRaw('bike_models.name, COUNT(*) as count')
            ->where('listings.is_sold_out', false)
            ->where('listings.created_at', '>=', $since)
            ->whereNotNull('listings.bike_model_id')
            ->groupBy('bike_models.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
    }

    public function getTopModels(): Collection
    {
        return $this->getByModel(now()->subDay());
    }

    private function getDailyTrend(): Collection
    {
        return DB::table('listings')
            ->selectRaw("DATE(created_at) as day, DATE_FORMAT(created_at, '%m/%d') as label, COUNT(*) as count")
            ->where('is_sold_out', false)
            ->where('created_at', '>=', now()->subDays(7)->startOfDay())
            ->groupByRaw("DATE(created_at), DATE_FORMAT(created_at, '%m/%d')")
            ->orderBy('day')
            ->get();
    }
}
