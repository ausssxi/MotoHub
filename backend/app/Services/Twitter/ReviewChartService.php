<?php

declare(strict_types=1);

namespace App\Services\Twitter;

use App\Models\Review;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Typography\FontFactory;

final class ReviewChartService
{
    private const WIDTH = 1200;
    private const HEIGHT = 630;
    private const BG_COLOR = '#0f172a';
    private const RADAR_W = 550;
    private const RADAR_H = 450;

    public function generateReviewCard(Review $review): ?string
    {
        $review->loadMissing(['bikeModel.manufacturer']);

        $radarData = $this->getRadarData($review);
        $radarPng = $this->fetchRadarChart($radarData);
        $salesKpi = $this->getSalesKpi($review->bike_model_id);

        $manager = new ImageManager(new Driver());
        $canvas = $manager->create(self::WIDTH, self::HEIGHT)->fill(self::BG_COLOR);

        $this->drawHeader($canvas, $review);
        $this->drawKpiGrid($canvas, $salesKpi);

        if ($radarPng) {
            $radarImage = $manager->read($radarPng);
            $canvas->place($radarImage, 'top-left', 10, 140);
        }

        $this->drawBranding($canvas);

        return (string) $canvas->toPng();
    }

    // =====================================================================
    // レーダーチャート
    // =====================================================================

    private function getRadarData(Review $review): array
    {
        $items = [
            'デザイン' => $review->rating_design,
            'エンジン性能' => $review->rating_engine,
            '取り回し' => $review->rating_handling,
            '燃費' => $review->rating_fuel_economy,
            'コスパ' => $review->rating_cost_performance,
        ];

        $labeled = [];
        foreach ($items as $name => $score) {
            $labeled["{$name} {$score}.0"] = $score;
        }

        return $labeled;
    }

    private function fetchRadarChart(array $data): ?string
    {
        $config = [
            'type' => 'radar',
            'data' => [
                'labels' => array_keys($data),
                'datasets' => [[
                    'data' => array_values($data),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.3)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 2,
                    'pointBackgroundColor' => 'rgba(59, 130, 246, 1)',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 1,
                    'pointRadius' => 5,
                ]],
            ],
            'options' => [
                'legend' => ['display' => false],
                'scale' => [
                    'ticks' => [
                        'beginAtZero' => true,
                        'max' => 5,
                        'min' => 0,
                        'stepSize' => 1,
                        'fontColor' => 'rgba(255, 255, 255, 0.5)',
                        'backdropColor' => 'transparent',
                        'display' => false,
                    ],
                    'pointLabels' => [
                        'fontColor' => '#ffffff',
                        'fontSize' => 14,
                        'fontStyle' => 'bold',
                    ],
                    'gridLines' => [
                        'color' => 'rgba(255, 255, 255, 0.15)',
                    ],
                    'angleLines' => [
                        'color' => 'rgba(255, 255, 255, 0.15)',
                    ],
                ],
                'layout' => ['padding' => ['top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20]],
            ],
        ];

        $chartJson = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $body = json_encode([
            'backgroundColor' => self::BG_COLOR,
            'width' => self::RADAR_W,
            'height' => self::RADAR_H,
            'devicePixelRatio' => 1,
            'chart' => $chartJson,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = Http::timeout(15)
            ->withBody($body, 'application/json')
            ->post('https://quickchart.io/chart');

        return $response->successful() ? $response->body() : null;
    }

    // =====================================================================
    // 販売 KPI データ取得
    // =====================================================================

    /**
     * @return array{monthly_sold: int, rank: int, total_models: int, daily_avg: float, avg_days: float}
     */
    private function getSalesKpi(int $bikeModelId): array
    {
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = now()->subMonthNoOverflow()->endOfMonth();
        $daysInMonth = (int) $lastMonthStart->daysInMonth;

        $monthlySold = DB::table('listings')
            ->where('bike_model_id', $bikeModelId)
            ->where('is_sold_out', true)
            ->whereBetween('updated_at', [$lastMonthStart, $lastMonthEnd])
            ->count();

        [$rank, $totalModels] = $this->getModelRank($bikeModelId, $lastMonthStart, $lastMonthEnd);

        $dailyAvg = $daysInMonth > 0 ? $monthlySold / $daysInMonth : 0;

        $avgDays = (float) DB::table('listings')
            ->where('bike_model_id', $bikeModelId)
            ->where('is_sold_out', true)
            ->whereBetween('updated_at', [$lastMonthStart, $lastMonthEnd])
            ->selectRaw('AVG(DATEDIFF(updated_at, created_at)) as avg_days')
            ->value('avg_days') ?: 0;

        return [
            'monthly_sold' => $monthlySold,
            'rank' => $rank,
            'total_models' => $totalModels,
            'daily_avg' => round($dailyAvg, 1),
            'avg_days' => round($avgDays),
        ];
    }

    /**
     * @return array{0: int, 1: int} [rank, totalModels]
     */
    private function getModelRank(int $bikeModelId, mixed $start, mixed $end): array
    {
        $cacheKey = "review_card_rank_{$start->format('Y-m')}";

        $rankings = Cache::remember($cacheKey, 3600, function () use ($start, $end) {
            return DB::table('listings')
                ->select('bike_model_id', DB::raw('COUNT(*) as sold_count'))
                ->where('is_sold_out', true)
                ->whereBetween('updated_at', [$start, $end])
                ->whereNotNull('bike_model_id')
                ->groupBy('bike_model_id')
                ->orderByDesc('sold_count')
                ->pluck('sold_count', 'bike_model_id');
        });

        $totalModels = $rankings->count();
        $rank = 0;
        $position = 0;
        foreach ($rankings as $modelId => $count) {
            $position++;
            if ($modelId === $bikeModelId) {
                $rank = $position;
                break;
            }
        }

        if ($rank === 0) {
            $rank = $totalModels + 1;
            $totalModels++;
        }

        return [$rank, $totalModels];
    }

    // =====================================================================
    // 描画
    // =====================================================================

    private function fontPath(): string
    {
        $noto = public_path('fonts/NotoSansJP-VariableFont_wght.ttf');

        return file_exists($noto) ? $noto : public_path('fonts/font.ttf');
    }

    private function drawHeader(mixed $canvas, Review $review): void
    {
        $fontPath = $this->fontPath();
        $centerX = (int) (self::WIDTH / 2);
        $bikeName = $review->bikeModel?->name ?? '車種不明';
        $makerName = $review->bikeModel?->manufacturer?->name ?? '';

        // --- 車種名（y=30、32px、白、中央揃え）---
        $displayName = $makerName ? "{$makerName} {$bikeName}" : $bikeName;
        if (mb_strlen($displayName) > 24) {
            $displayName = mb_substr($displayName, 0, 23) . '…';
        }
        $canvas->text($displayName, $centerX, 30, function (FontFactory $font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(32);
            $font->color('#ffffff');
            $font->align('center');
            $font->valign('top');
        });

        // --- 総合評価（y=80、28px、黄色、中央揃え）---
        $rating = $review->rating;
        $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
        $avg = number_format($this->calcAvgRating($review), 1);
        $canvas->text("{$stars} {$avg}", $centerX, 80, function (FontFactory $font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(28);
            $font->color('#FBBF24');
            $font->align('center');
            $font->valign('top');
        });
    }

    private function drawKpiGrid(mixed $canvas, array $kpi): void
    {
        $fontPath = $this->fontPath();

        // 2x2 グリッド: 右半分 (x=620〜1180) の y=140〜600
        // 各セル: 280x220、中央x: 760, 1040
        $cells = [
            // [label, value, color, x, y]
            ['先月販売台数', number_format($kpi['monthly_sold']) . '台', '#ffffff', 760, 210],
            ['全車種順位', "{$kpi['rank']}位/" . number_format($kpi['total_models']) . '種', '#FFD700', 1040, 210],
            ['1日平均', "{$kpi['daily_avg']}台", '#ffffff', 760, 430],
            ['平均在庫日数', (int) $kpi['avg_days'] . '日', '#ffffff', 1040, 430],
        ];

        foreach ($cells as [$label, $value, $color, $x, $y]) {
            // ラベル（白70%、18px）
            $canvas->text($label, $x, $y - 50, function (FontFactory $font) use ($fontPath) {
                $font->filename($fontPath);
                $font->size(18);
                $font->color('rgba(255, 255, 255, 0.7)');
                $font->align('center');
                $font->valign('top');
            });

            // 値（42px）
            $canvas->text($value, $x, $y, function (FontFactory $font) use ($fontPath, $color) {
                $font->filename($fontPath);
                $font->size(42);
                $font->color($color);
                $font->align('center');
                $font->valign('top');
            });
        }
    }

    private function drawBranding(mixed $canvas): void
    {
        $fontPath = $this->fontPath();

        $canvas->text('MotoHub', 1150, 600, function (FontFactory $font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(16);
            $font->color('rgba(255, 255, 255, 0.3)');
            $font->align('right');
            $font->valign('bottom');
        });
    }

    // =====================================================================
    // ユーティリティ
    // =====================================================================

    private function calcAvgRating(Review $review): float
    {
        $ratings = array_filter([
            $review->rating_design,
            $review->rating_engine,
            $review->rating_handling,
            $review->rating_fuel_economy,
            $review->rating_cost_performance,
        ]);

        if (empty($ratings)) {
            return (float) $review->rating;
        }

        return array_sum($ratings) / count($ratings);
    }
}
