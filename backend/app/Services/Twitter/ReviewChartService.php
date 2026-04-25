<?php

declare(strict_types=1);

namespace App\Services\Twitter;

use App\Models\Review;
use Illuminate\Support\Facades\Http;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Typography\FontFactory;

final class ReviewChartService
{
    private const WIDTH = 1200;
    private const HEIGHT = 630;
    private const BG_COLOR = '#0f172a';
    private const RADAR_W = 600;
    private const RADAR_H = 450;

    public function generateReviewCard(Review $review): ?string
    {
        $review->loadMissing(['bikeModel.manufacturer']);

        $radarData = $this->getRadarData($review);
        $radarPng = $this->fetchRadarChart($radarData);

        $manager = new ImageManager(new Driver());
        $canvas = $manager->create(self::WIDTH, self::HEIGHT)->fill(self::BG_COLOR);

        $this->drawTextInfo($canvas, $review);

        if ($radarPng) {
            $radarImage = $manager->read($radarPng);
            $canvas->place($radarImage, 'top-left', 300, 140);
        }

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
                        'fontSize' => 16,
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
    // テキスト描画（縦並び中央揃え）
    // =====================================================================

    private function drawTextInfo(mixed $canvas, Review $review): void
    {
        $fontPath = public_path('fonts/NotoSansJP-VariableFont_wght.ttf');
        if (!file_exists($fontPath)) {
            $fontPath = public_path('fonts/font.ttf');
        }

        $centerX = self::WIDTH / 2;
        $bikeName = $review->bikeModel?->name ?? '車種不明';
        $makerName = $review->bikeModel?->manufacturer?->name ?? '';

        // --- 車種名（y=40、36px、白、中央揃え）---
        $displayName = $makerName ? "{$makerName} {$bikeName}" : $bikeName;
        if (mb_strlen($displayName) > 20) {
            $displayName = mb_substr($displayName, 0, 19) . '…';
        }
        $canvas->text($displayName, (int) $centerX, 40, function (FontFactory $font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(36);
            $font->color('#ffffff');
            $font->align('center');
            $font->valign('top');
        });

        // --- 総合評価（y=100、32px、黄色、中央揃え）---
        $rating = $review->rating;
        $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
        $avg = number_format($this->calcAvgRating($review), 1);
        $canvas->text("{$stars} {$avg}", (int) $centerX, 100, function (FontFactory $font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(32);
            $font->color('#FBBF24');
            $font->align('center');
            $font->valign('top');
        });

        // --- MotoHub ブランディング（右下）---
        $canvas->text('MotoHub', 1150, 600, function (FontFactory $font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(16);
            $font->color('rgba(255, 255, 255, 0.3)');
            $font->align('right');
            $font->valign('bottom');
        });
    }

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
