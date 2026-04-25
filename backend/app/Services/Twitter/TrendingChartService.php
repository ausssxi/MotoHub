<?php

declare(strict_types=1);

namespace App\Services\Twitter;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Typography\FontFactory;

final class TrendingChartService
{
    private const WIDTH = 1200;
    private const HEIGHT = 630;
    private const CHART_W = 1100;
    private const CHART_H = 500;
    private const BG_COLOR = '#0f172a';

    public function generateDashboardImage(Collection $ranking): ?string
    {
        $chartPng = $this->fetchChart($this->buildRankingConfig($ranking));

        $manager = new ImageManager(new Driver());
        $canvas = $manager->create(self::WIDTH, self::HEIGHT)->fill(self::BG_COLOR);

        $this->drawTitle($canvas);

        if ($chartPng) {
            $chartImage = $manager->read($chartPng);
            $canvas->place($chartImage, 'top-left', 50, 100);
        }

        return (string) $canvas->toPng();
    }

    /**
     * 今週の売れ筋TOP5を取得（is_sold_out=true、updated_atが今週）
     */
    public function getWeeklyRanking(): Collection
    {
        $thisWeekStart = now()->startOfWeek();
        $thisWeekEnd = now();
        $lastWeekStart = now()->subWeek()->startOfWeek();
        $lastWeekEnd = now()->subWeek()->endOfWeek();

        // 今週の販売ランキング
        $thisWeek = DB::table('listings')
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->join('manufacturers', 'bike_models.manufacturer_id', '=', 'manufacturers.id')
            ->select(
                'listings.bike_model_id',
                'bike_models.name as bike_name',
                'manufacturers.name as maker_name',
                DB::raw('COUNT(*) as sold_count'),
            )
            ->where('listings.is_sold_out', true)
            ->whereBetween('listings.updated_at', [$thisWeekStart, $thisWeekEnd])
            ->whereNotNull('listings.bike_model_id')
            ->groupBy('listings.bike_model_id', 'bike_models.name', 'manufacturers.name')
            ->orderByDesc('sold_count')
            ->limit(5)
            ->get();

        if ($thisWeek->isEmpty()) {
            return collect();
        }

        // 先週のランキング（順位比較用）
        $lastWeekRanks = DB::table('listings')
            ->select('bike_model_id', DB::raw('COUNT(*) as sold_count'))
            ->where('is_sold_out', true)
            ->whereBetween('updated_at', [$lastWeekStart, $lastWeekEnd])
            ->whereNotNull('bike_model_id')
            ->groupBy('bike_model_id')
            ->orderByDesc('sold_count')
            ->get()
            ->values()
            ->mapWithKeys(fn ($row, $i) => [$row->bike_model_id => $i + 1]);

        // 平均価格を取得
        $modelIds = $thisWeek->pluck('bike_model_id')->toArray();
        $avgPrices = DB::table('listings')
            ->select('bike_model_id', DB::raw('AVG(total_price) as avg_price'))
            ->whereIn('bike_model_id', $modelIds)
            ->where('is_sold_out', false)
            ->where('total_price', '>', 0)
            ->groupBy('bike_model_id')
            ->pluck('avg_price', 'bike_model_id');

        return $thisWeek->values()->map(function ($item, $index) use ($lastWeekRanks, $avgPrices) {
            $currentRank = $index + 1;
            $lastRank = $lastWeekRanks->get($item->bike_model_id);
            $avgPrice = round(((float) ($avgPrices->get($item->bike_model_id) ?? 0)) / 10000, 1);

            if ($lastRank === null) {
                $change = 'NEW';
            } else {
                $diff = $lastRank - $currentRank;
                $change = $diff > 0 ? "↑{$diff}" : ($diff < 0 ? "↓" . abs($diff) : '→');
            }

            $item->rank = $currentRank;
            $item->avg_price = $avgPrice;
            $item->change = $change;

            return $item;
        });
    }

    // =====================================================================
    // チャート設定
    // =====================================================================

    private function buildRankingConfig(Collection $ranking): array
    {
        $barColors = ['#FFD700', '#C0C0C0', '#CD7F32', 'rgba(59, 130, 246, 0.7)', 'rgba(59, 130, 246, 0.7)'];
        $borderColors = ['#FFD700', '#C0C0C0', '#CD7F32', 'rgba(59, 130, 246, 1)', 'rgba(59, 130, 246, 1)'];

        $labels = [];
        $bgColors = [];
        $borders = [];
        foreach ($ranking as $i => $item) {
            $rankNum = $item->rank;
            $changeStr = $item->change !== '→' ? " {$item->change}" : '';
            $priceStr = $item->avg_price > 0 ? " / 平均{$item->avg_price}万円" : '';
            $labels[] = "{$rankNum}位 {$item->bike_name}（{$item->maker_name}）{$item->sold_count}台{$priceStr}{$changeStr}";
            $bgColors[] = $barColors[$i] ?? $barColors[4];
            $borders[] = $borderColors[$i] ?? $borderColors[4];
        }

        $config = [
            'type' => 'horizontalBar',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'data' => $ranking->pluck('sold_count')->toArray(),
                    'backgroundColor' => $bgColors,
                    'borderColor' => $borders,
                    'borderWidth' => 1,
                ]],
            ],
            'options' => [
                'legend' => ['display' => false],
                'scales' => [
                    'xAxes' => [[
                        'gridLines' => [
                            'color' => 'rgba(255, 255, 255, 0.15)',
                            'zeroLineColor' => 'rgba(255, 255, 255, 0.15)',
                        ],
                        'ticks' => [
                            'fontColor' => 'rgba(255, 255, 255, 0.9)',
                            'fontSize' => 16,
                            'beginAtZero' => true,
                            'callback' => '__TICK_CB__',
                        ],
                    ]],
                    'yAxes' => [[
                        'gridLines' => [
                            'color' => 'rgba(255, 255, 255, 0.06)',
                            'zeroLineColor' => 'rgba(255, 255, 255, 0.06)',
                        ],
                        'ticks' => [
                            'fontColor' => 'rgba(255, 255, 255, 0.85)',
                            'fontSize' => 16,
                            'fontStyle' => 'bold',
                        ],
                    ]],
                ],
                'layout' => ['padding' => ['top' => 8, 'right' => 20, 'bottom' => 8, 'left' => 8]],
            ],
        ];

        return ['config' => $config, 'callbacks' => [
            '"__TICK_CB__"' => "function(v){return Number.isInteger(v)?v+'台':'';}",
        ]];
    }

    // =====================================================================
    // 描画
    // =====================================================================

    private function drawTitle(mixed $canvas): void
    {
        $fontPath = public_path('fonts/NotoSansJP-VariableFont_wght.ttf');
        if (!file_exists($fontPath)) {
            $fontPath = public_path('fonts/font.ttf');
        }

        $canvas->text('今週の売れ筋ランキング TOP5', (int) (self::WIDTH / 2), 20, function (FontFactory $font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(28);
            $font->color('#ffffff');
            $font->align('center');
            $font->valign('top');
        });
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
            'width' => self::CHART_W,
            'height' => self::CHART_H,
            'devicePixelRatio' => 1,
            'chart' => $chartJson,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = Http::timeout(15)
            ->withBody($body, 'application/json')
            ->post('https://quickchart.io/chart');

        return $response->successful() ? $response->body() : null;
    }
}
