<?php

declare(strict_types=1);

namespace App\Services\Twitter;

use App\Models\Listing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Typography\FontFactory;

final class DealChartService
{
    private const WIDTH = 1200;
    private const HEIGHT = 630;
    private const MINI_W = 570;
    private const MINI_H = 295;
    private const BG_COLOR = '#0f172a';

    /**
     * OGP 用: 単一の相場推移チャート (1200x630)
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

        return $this->fetchChart($this->buildTrendChartConfig(
            $labels, $prices, $listingPriceMan, "{$modelName} 相場推移", 24,
        ), self::WIDTH, self::HEIGHT);
    }

    /**
     * Tweet 用: 4 グラフダッシュボード (1200x630)
     */
    public function generateDashboardImage(Listing $listing): ?string
    {
        $listing->loadMissing(['bikeModel.manufacturer', 'shop']);

        $bikeModelId = $listing->bike_model_id;
        if (!$bikeModelId) {
            return null;
        }

        $listingPrice = (float) ($listing->total_price ?: $listing->price);
        $listingPriceMan = round($listingPrice / 10000, 1);
        $listingPrefecture = $listing->shop?->prefecture;
        $listingYear = $listing->model_year;

        // --- データ取得 ---
        $monthly = $this->getMonthlyAveragePrices($bikeModelId);
        if ($monthly->count() < 6) {
            $avgPrice = $this->getAveragePrice($listing) ?: $listingPrice;
            if (!$avgPrice) {
                return null;
            }
            $monthly = $this->generateDummyMonthlyData($avgPrice);
        }

        $priceDist = $this->getPriceDistribution($bikeModelId);
        $regional = $this->getRegionalPopularity($bikeModelId);
        $yearDist = $this->getYearDistribution($bikeModelId);

        // --- 4 チャート生成 ---
        $trendLabels = $monthly->pluck('month_label')->toArray();
        $trendPrices = $monthly->pluck('avg_price')->map(fn ($v) => round((float) $v / 10000, 1))->toArray();

        $charts = [
            $this->fetchChart($this->buildTrendChartConfig($trendLabels, $trendPrices, $listingPriceMan, '相場推移', 14), self::MINI_W, self::MINI_H, 1),
            $this->fetchChart($this->buildPriceDistConfig($priceDist, $listingPrice), self::MINI_W, self::MINI_H, 1),
            $this->fetchChart($this->buildRegionalConfig($regional, $listingPrefecture), self::MINI_W, self::MINI_H, 1),
            $this->fetchChart($this->buildYearDistConfig($yearDist, $listingYear), self::MINI_W, self::MINI_H, 1),
        ];

        // 全チャート生成成功チェック（最低限トレンドチャートは必須）
        if (!$charts[0]) {
            return null;
        }

        // --- Intervention Image で合成 ---
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

    /**
     * Tweet 用: 左右分割レイアウト (1200x630)
     * 左: テキスト情報 / 右: 相場推移グラフ
     */
    public function generateCombinedImage(Listing $listing, int $percentOff): ?string
    {
        $listing->loadMissing(['bikeModel.manufacturer']);

        $bikeModelId = $listing->bike_model_id;
        if (!$bikeModelId) {
            return null;
        }

        $modelName = $listing->bikeModel?->name ?? $listing->title ?? '車種名不明';
        $makerName = $listing->bikeModel?->manufacturer?->name ?? '';
        $listingPrice = (float) ($listing->total_price ?: $listing->price);
        $listingPriceMan = round($listingPrice / 10000, 1);

        // --- 相場推移データ ---
        $monthly = $this->getMonthlyAveragePrices($bikeModelId);
        if ($monthly->count() < 6) {
            $avgPrice = $this->getAveragePrice($listing) ?: $listingPrice;
            if (!$avgPrice) {
                return null;
            }
            $monthly = $this->generateDummyMonthlyData($avgPrice);
        }

        $trendLabels = $monthly->pluck('month_label')->toArray();
        $trendPrices = $monthly->pluck('avg_price')->map(fn ($v) => round((float) $v / 10000, 1))->toArray();

        // --- 右側: 相場推移グラフ (560x490, DPR=1) ---
        $chartPng = $this->fetchChart(
            $this->buildTrendChartConfig($trendLabels, $trendPrices, $listingPriceMan, '', 14),
            560, 490, 1,
        );

        if (!$chartPng) {
            return null;
        }

        // --- Intervention Image で合成 ---
        $manager = new ImageManager(new Driver());
        $canvas = $manager->create(self::WIDTH, self::HEIGHT)->fill(self::BG_COLOR);

        $fontBold = storage_path('app/fonts/NotoSansJP-Bold.ttf');
        $fontRegular = storage_path('app/fonts/NotoSansJP-Regular.ttf');

        // 左側テキスト描画
        $leftCenter = 300; // 左半分の中央X

        // 車種名（上部）
        $nameSize = mb_strlen($modelName) > 12 ? 28 : 34;
        $canvas->text($modelName, $leftCenter, 140, function (FontFactory $f) use ($fontBold, $nameSize) {
            $f->filename($fontBold);
            $f->size($nameSize);
            $f->color('#ffffff');
            $f->align('center');
            $f->valign('middle');
        });

        // メーカー名
        if ($makerName) {
            $canvas->text($makerName, $leftCenter, 185, function (FontFactory $f) use ($fontRegular) {
                $f->filename($fontRegular);
                $f->size(18);
                $f->color('#94a3b8');
                $f->align('center');
                $f->valign('middle');
            });
        }

        // 価格（超大きい）
        $priceText = number_format($listingPriceMan, 1) . '万円';
        $canvas->text($priceText, $leftCenter, 300, function (FontFactory $f) use ($fontBold) {
            $f->filename($fontBold);
            $f->size(64);
            $f->color('#ffffff');
            $f->align('center');
            $f->valign('middle');
        });

        // 割引率
        $discountText = "相場より{$percentOff}%安い！";
        $canvas->text($discountText, $leftCenter, 380, function (FontFactory $f) use ($fontBold) {
            $f->filename($fontBold);
            $f->size(26);
            $f->color('#22c55e');
            $f->align('center');
            $f->valign('middle');
        });

        // MotoHub ロゴテキスト（下部）
        $canvas->text('MotoHub', $leftCenter, 540, function (FontFactory $f) use ($fontBold) {
            $f->filename($fontBold);
            $f->size(20);
            $f->color('#475569');
            $f->align('center');
            $f->valign('middle');
        });

        $canvas->text('motohub.jp', $leftCenter, 570, function (FontFactory $f) use ($fontRegular) {
            $f->filename($fontRegular);
            $f->size(14);
            $f->color('#334155');
            $f->align('center');
            $f->valign('middle');
        });

        // 右上: 「相場推移」ラベル
        $canvas->text('相場推移（6ヶ月）', 900, 40, function (FontFactory $f) use ($fontBold) {
            $f->filename($fontBold);
            $f->size(16);
            $f->color('#94a3b8');
            $f->align('center');
            $f->valign('middle');
        });

        // 右側: グラフ配置
        $chartImg = $manager->read($chartPng);
        $canvas->place($chartImg, 'top-left', 620, 65);

        return (string) $canvas->toPng();
    }

    // =====================================================================
    // チャート設定ビルダー
    // =====================================================================

    private function buildTrendChartConfig(array $labels, array $prices, float $listingPriceMan, string $title, int $titleSize): array
    {
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
                'borderWidth' => 2,
                'borderDash' => [8, 4],
                'label' => [
                    'enabled' => true,
                    'content' => "この車両 {$listingPriceMan}万円",
                    'position' => 'left',
                    'yAdjust' => $labelPosition === 'bottom' ? 14 : -14,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.92)',
                    'fontColor' => '#ffffff',
                    'fontSize' => 11,
                    'fontStyle' => 'bold',
                    'cornerRadius' => 3,
                    'xPadding' => 6,
                    'yPadding' => 4,
                ],
            ];
        }

        $config = [
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
                    'pointRadius' => 5,
                    'pointBackgroundColor' => '#3B82F6',
                    'pointBorderColor' => self::BG_COLOR,
                    'pointBorderWidth' => 2,
                    'borderWidth' => 3,
                ]],
            ],
            'options' => [
                'legend' => ['display' => false],
                'title' => $this->miniTitle($title, $titleSize),
                'annotation' => ['annotations' => $annotations],
                'scales' => [
                    'xAxes' => [$this->miniXAxis()],
                    'yAxes' => [[
                        'gridLines' => $this->gridLines(),
                        'ticks' => array_merge($this->miniYTicks(), [
                            'callback' => '__TICK_CALLBACK_MAN__',
                        ]),
                    ]],
                ],
                'layout' => ['padding' => ['top' => 4, 'right' => 16, 'bottom' => 4, 'left' => 4]],
            ],
        ];

        return ['config' => $config, 'callbacks' => [
            '"__TICK_CALLBACK_MAN__"' => "function(v){return v%1===0?v+'万':v.toFixed(1)+'万';}",
        ]];
    }

    private function buildPriceDistConfig(Collection $data, float $listingPrice = 0): array
    {
        $bands = [200000, 400000, 600000, 800000];
        $highlightIndex = $listingPrice > 0 ? match (true) {
            $listingPrice < $bands[0] => 0,
            $listingPrice < $bands[1] => 1,
            $listingPrice < $bands[2] => 2,
            $listingPrice < $bands[3] => 3,
            default => 4,
        } : -1;

        $green = 'rgba(34, 197, 94, 0.7)';
        $red = 'rgba(239, 68, 68, 0.85)';
        $bgColors = [];
        $borderColors = [];
        $borderWidths = [];
        $datalabels = [];
        foreach ($data as $i => $row) {
            $hit = ($i === $highlightIndex);
            $bgColors[] = $hit ? $red : $green;
            $borderColors[] = $hit ? '#ffffff' : 'rgba(34, 197, 94, 1)';
            $borderWidths[] = $hit ? 2 : 1;
            $datalabels[] = $hit ? 'この車両' : '';
        }

        $config = [
            'type' => 'horizontalBar',
            'data' => [
                'labels' => $data->pluck('label')->toArray(),
                'datasets' => [[
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => $bgColors,
                    'borderColor' => $borderColors,
                    'borderWidth' => $borderWidths,
                    'datalabels' => ['labels' => ['value' => ['color' => '#ffffff']]],
                ]],
            ],
            'options' => [
                'legend' => ['display' => false],
                'title' => $this->miniTitle('価格帯分布'),
                'plugins' => ['datalabels' => [
                    'display' => '__DATALABEL_DISPLAY_PRICE__',
                    'color' => '#ffffff',
                    'font' => ['size' => 11, 'weight' => 'bold'],
                    'anchor' => 'end',
                    'align' => 'left',
                    'formatter' => '__DATALABEL_FMT_PRICE__',
                ]],
                'scales' => [
                    'xAxes' => [[
                        'gridLines' => $this->gridLines(),
                        'ticks' => array_merge($this->miniYTicks(), [
                            'beginAtZero' => true,
                            'callback' => '__TICK_CALLBACK_COUNT__',
                        ]),
                    ]],
                    'yAxes' => [[
                        'gridLines' => $this->gridLines(),
                        'ticks' => $this->miniXTicks(),
                    ]],
                ],
                'layout' => ['padding' => ['top' => 4, 'right' => 16, 'bottom' => 4, 'left' => 4]],
            ],
        ];

        return ['config' => $config, 'callbacks' => [
            '"__TICK_CALLBACK_COUNT__"' => "function(v){return Number.isInteger(v)?v+'台':'';}",
            '"__DATALABEL_DISPLAY_PRICE__"' => "function(ctx){return ctx.dataIndex==={$highlightIndex};}",
            '"__DATALABEL_FMT_PRICE__"' => "function(){return '◀ この車両';}",
        ]];
    }

    private function buildRegionalConfig(Collection $data, ?string $listingPrefecture = null): array
    {
        $orange = 'rgba(251, 146, 60, 0.7)';
        $red = 'rgba(239, 68, 68, 0.85)';

        // この車両の都道府県がTOP5に入っていなければ追加
        $prefectures = $data->pluck('prefecture')->toArray();
        if ($listingPrefecture && !in_array($listingPrefecture, $prefectures)) {
            $data->push((object) [
                'prefecture' => $listingPrefecture,
                'count' => 1,
            ]);
        }

        $highlightIndex = -1;
        $bgColors = [];
        $borderColors = [];
        $borderWidths = [];
        foreach ($data->values() as $i => $row) {
            $isListing = $listingPrefecture && $row->prefecture === $listingPrefecture;
            if ($isListing) {
                $highlightIndex = $i;
            }
            $bgColors[] = $isListing ? $red : $orange;
            $borderColors[] = $isListing ? '#ffffff' : 'rgba(251, 146, 60, 1)';
            $borderWidths[] = $isListing ? 2 : 1;
        }

        $config = [
            'type' => 'horizontalBar',
            'data' => [
                'labels' => $data->pluck('prefecture')->toArray(),
                'datasets' => [[
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => $bgColors,
                    'borderColor' => $borderColors,
                    'borderWidth' => $borderWidths,
                ]],
            ],
            'options' => [
                'legend' => ['display' => false],
                'title' => $this->miniTitle('地域別 在庫数 TOP5'),
                'plugins' => ['datalabels' => [
                    'display' => '__DATALABEL_DISPLAY_REGION__',
                    'color' => '#ffffff',
                    'font' => ['size' => 11, 'weight' => 'bold'],
                    'anchor' => 'start',
                    'align' => 'right',
                    'formatter' => '__DATALABEL_FMT_REGION__',
                ]],
                'scales' => [
                    'xAxes' => [[
                        'gridLines' => $this->gridLines(),
                        'ticks' => array_merge($this->miniYTicks(), [
                            'beginAtZero' => true,
                            'callback' => '__TICK_CALLBACK_COUNT2__',
                        ]),
                    ]],
                    'yAxes' => [[
                        'gridLines' => $this->gridLines(),
                        'ticks' => $this->miniXTicks(),
                    ]],
                ],
                'layout' => ['padding' => ['top' => 4, 'right' => 16, 'bottom' => 4, 'left' => 4]],
            ],
        ];

        return ['config' => $config, 'callbacks' => [
            '"__TICK_CALLBACK_COUNT2__"' => "function(v){return Number.isInteger(v)?v+'台':'';}",
            '"__DATALABEL_DISPLAY_REGION__"' => "function(ctx){return ctx.dataIndex==={$highlightIndex};}",
            '"__DATALABEL_FMT_REGION__"' => "function(){return 'この車両 ▶';}",
        ]];
    }

    private function buildYearDistConfig(Collection $data, ?int $listingYear = null): array
    {
        $purple = 'rgba(168, 85, 247, 0.7)';
        $red = 'rgba(239, 68, 68, 0.85)';

        // この車両の年式がなければ追加
        if ($listingYear && $listingYear > 0) {
            $existingYears = $data->pluck('model_year')->map(fn ($v) => (int) $v)->toArray();
            if (!in_array($listingYear, $existingYears)) {
                $data->push((object) [
                    'model_year' => $listingYear,
                    'year_label' => "{$listingYear}年",
                    'count' => 1,
                ]);
                $data = $data->sortBy('model_year')->values();
            }
        }

        $highlightIndex = -1;
        $bgColors = [];
        $borderColors = [];
        $borderWidths = [];
        foreach ($data->values() as $i => $row) {
            $isListing = $listingYear && (int) $row->model_year === $listingYear;
            if ($isListing) {
                $highlightIndex = $i;
            }
            $bgColors[] = $isListing ? $red : $purple;
            $borderColors[] = $isListing ? '#ffffff' : 'rgba(168, 85, 247, 1)';
            $borderWidths[] = $isListing ? 2 : 1;
        }

        $config = [
            'type' => 'bar',
            'data' => [
                'labels' => $data->pluck('year_label')->toArray(),
                'datasets' => [[
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => $bgColors,
                    'borderColor' => $borderColors,
                    'borderWidth' => $borderWidths,
                ]],
            ],
            'options' => [
                'legend' => ['display' => false],
                'title' => $this->miniTitle('年式分布'),
                'plugins' => ['datalabels' => [
                    'display' => '__DATALABEL_DISPLAY_YEAR__',
                    'color' => '#ffffff',
                    'font' => ['size' => 10, 'weight' => 'bold'],
                    'anchor' => 'end',
                    'align' => 'top',
                    'formatter' => '__DATALABEL_FMT_YEAR__',
                ]],
                'scales' => [
                    'xAxes' => [$this->miniXAxis()],
                    'yAxes' => [[
                        'gridLines' => $this->gridLines(),
                        'ticks' => array_merge($this->miniYTicks(), [
                            'beginAtZero' => true,
                            'callback' => '__TICK_CALLBACK_COUNT3__',
                        ]),
                    ]],
                ],
                'layout' => ['padding' => ['top' => 16, 'right' => 16, 'bottom' => 4, 'left' => 4]],
            ],
        ];

        return ['config' => $config, 'callbacks' => [
            '"__TICK_CALLBACK_COUNT3__"' => "function(v){return Number.isInteger(v)?v+'台':'';}",
            '"__DATALABEL_DISPLAY_YEAR__"' => "function(ctx){return ctx.dataIndex==={$highlightIndex};}",
            '"__DATALABEL_FMT_YEAR__"' => "function(){return '▼この車両';}",
        ]];
    }

    // =====================================================================
    // 共通スタイルヘルパー
    // =====================================================================

    private function miniTitle(string $text, int $size = 14): array
    {
        return [
            'display' => true,
            'text' => $text,
            'fontColor' => 'rgba(255, 255, 255, 0.8)',
            'fontSize' => $size,
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

    private function miniXAxis(): array
    {
        return [
            'gridLines' => $this->gridLines(),
            'ticks' => $this->miniXTicks(),
        ];
    }

    private function miniXTicks(): array
    {
        return [
            'fontColor' => 'rgba(255, 255, 255, 0.6)',
            'fontSize' => 12,
            'fontStyle' => 'bold',
        ];
    }

    private function miniYTicks(): array
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

    private function fetchChart(array $spec, int $width, int $height, int $dpr = 2): ?string
    {
        $chartJson = json_encode($spec['config'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($spec['callbacks'] ?? [] as $placeholder => $jsFunc) {
            $chartJson = str_replace($placeholder, $jsFunc, $chartJson);
        }

        $body = json_encode([
            'backgroundColor' => self::BG_COLOR,
            'width' => $width,
            'height' => $height,
            'devicePixelRatio' => $dpr,
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

    private function getMonthlyAveragePrices(int $bikeModelId): Collection
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

    private function getPriceDistribution(int $bikeModelId): Collection
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
                ->where('bike_model_id', $bikeModelId)
                ->where('is_sold_out', false)
                ->where('total_price', '>', 0);

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

    private function getRegionalPopularity(int $bikeModelId): Collection
    {
        return DB::table('listings')
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->selectRaw('shops.prefecture, COUNT(*) as count')
            ->where('listings.bike_model_id', $bikeModelId)
            ->where('listings.is_sold_out', false)
            ->where('listings.total_price', '>', 0)
            ->whereNotNull('shops.prefecture')
            ->groupBy('shops.prefecture')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
    }

    private function getYearDistribution(int $bikeModelId): Collection
    {
        return DB::table('listings')
            ->selectRaw("model_year, CONCAT(model_year, '年') as year_label, COUNT(*) as count")
            ->where('bike_model_id', $bikeModelId)
            ->where('is_sold_out', false)
            ->where('total_price', '>', 0)
            ->whereNotNull('model_year')
            ->where('model_year', '>', 0)
            ->groupBy('model_year')
            ->orderBy('model_year')
            ->get();
    }

    private function generateDummyMonthlyData(float $avgPrice): Collection
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

    private function getAveragePrice(Listing $listing): ?float
    {
        if (!$listing->bike_model_id) {
            return null;
        }

        return (float) Cache::remember(
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
