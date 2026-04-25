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
    private const RADAR_W = 580;
    private const RADAR_H = 580;

    public function generateReviewCard(Review $review): ?string
    {
        $review->loadMissing(['bikeModel.manufacturer']);

        $radarData = $this->getRadarData($review);
        $radarPng = $this->fetchRadarChart($radarData);

        $manager = new ImageManager(new Driver());
        $canvas = $manager->create(self::WIDTH, self::HEIGHT)->fill(self::BG_COLOR);

        if ($radarPng) {
            $radarImage = $manager->read($radarPng);
            $canvas->place($radarImage, 'top-left', 10, 25);
        }

        $this->drawTextInfo($canvas, $review);

        return (string) $canvas->toPng();
    }

    // =====================================================================
    // レーダーチャート
    // =====================================================================

    private function getRadarData(Review $review): array
    {
        return [
            'デザイン' => $review->rating_design ?? $review->rating,
            'エンジン性能' => $review->rating_engine ?? $review->rating,
            '取り回し' => $review->rating_handling ?? $review->rating,
            '燃費' => $review->rating_fuel_economy ?? $review->rating,
            'コスパ' => $review->rating_cost_performance ?? $review->rating,
        ];
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
    // 右側テキスト描画
    // =====================================================================

    private function drawTextInfo(mixed $canvas, Review $review): void
    {
        $fontPath = public_path('fonts/NotoSansJP-VariableFont_wght.ttf');
        if (!file_exists($fontPath)) {
            $fontPath = public_path('fonts/font.ttf');
        }

        $rightX = 620;
        $bikeName = $review->bikeModel?->name ?? '車種不明';
        $makerName = $review->bikeModel?->manufacturer?->name ?? '';

        // --- 車種名（大きく、白文字）---
        $displayName = $makerName ? "{$makerName} {$bikeName}" : $bikeName;
        if (mb_strlen($displayName) > 18) {
            $displayName = mb_substr($displayName, 0, 17) . '…';
        }
        $canvas->text($displayName, $rightX, 80, function (FontFactory $font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(32);
            $font->color('#ffffff');
        });

        // --- 総合評価（黄色の★）---
        $rating = $review->rating;
        $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
        $avg = number_format($this->calcAvgRating($review), 1);
        $canvas->text("{$stars} {$avg}", $rightX, 140, function (FontFactory $font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(28);
            $font->color('#FBBF24');
        });

        // --- レビュータイトル ---
        $title = $review->title;
        if (mb_strlen($title) > 20) {
            $title = mb_substr($title, 0, 19) . '…';
        }
        $canvas->text("「{$title}」", $rightX, 210, function (FontFactory $font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(22);
            $font->color('rgba(255, 255, 255, 0.9)');
        });

        // --- レビュー本文抜粋（グレー、折り返し）---
        $excerpt = mb_substr($review->body, 0, 100);
        if (mb_strlen($review->body) > 100) {
            $excerpt .= '…';
        }

        $lines = $this->wrapText($excerpt, 22);
        $y = 270;
        foreach ($lines as $line) {
            $canvas->text($line, $rightX, $y, function (FontFactory $font) use ($fontPath) {
                $font->filename($fontPath);
                $font->size(16);
                $font->color('rgba(255, 255, 255, 0.55)');
            });
            $y += 28;
        }

        // --- レビュワー名（小さく）---
        $nickname = $review->nickname ?: '名無しライダー';
        $canvas->text("— {$nickname}", $rightX, 520, function (FontFactory $font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(14);
            $font->color('rgba(255, 255, 255, 0.4)');
        });

        // --- MotoHub ブランディング（右下）---
        $canvas->text('MotoHub', 1080, 590, function (FontFactory $font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(18);
            $font->color('rgba(255, 255, 255, 0.3)');
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

    private function wrapText(string $text, int $charsPerLine): array
    {
        $lines = [];
        $remaining = $text;
        $maxLines = 8;

        while (mb_strlen($remaining) > 0 && count($lines) < $maxLines) {
            if (mb_strlen($remaining) <= $charsPerLine) {
                $lines[] = $remaining;
                break;
            }
            $lines[] = mb_substr($remaining, 0, $charsPerLine);
            $remaining = mb_substr($remaining, $charsPerLine);
        }

        return $lines;
    }
}
