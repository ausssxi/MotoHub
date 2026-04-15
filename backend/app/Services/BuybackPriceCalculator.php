<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class BuybackPriceCalculator
{
    // ショップ利益率（売価の何%が買取額か）
    private const SHOP_MARGIN_RATE = 0.70;

    // キャッシュTTL（6時間）
    private const CACHE_TTL = 21600;

    /**
     * 買取予想価格を算出
     */
    public function calculate(
        int $bikeModelId,
        ?int $mileage = null,
        ?int $year = null,
    ): array {
        $stats = $this->getSoldStats($bikeModelId);

        if ($stats->data_count < 3) {
            return $this->fallback($bikeModelId, $mileage, $year);
        }

        $basePrice = $stats->avg_sold_price;

        $shopMargin = self::SHOP_MARGIN_RATE;
        $speedFactor = $this->getSpeedFactor($stats->avg_days);
        $mileageFactor = $this->getMileageFactor($mileage);
        $yearFactor = $this->getYearFactor($year);

        $estimatedPrice = (int) round(
            $basePrice * $shopMargin * $speedFactor * $mileageFactor * $yearFactor
        );

        // 価格レンジ（±15%）
        $minPrice = (int) round($estimatedPrice * 0.85);
        $maxPrice = (int) round($estimatedPrice * 1.15);

        $confidence = $this->getConfidence($stats->data_count);

        return [
            'status' => 'success',
            'estimated_price' => max(0, $estimatedPrice),
            'price_range' => [
                'min' => max(0, $minPrice),
                'max' => max(0, $maxPrice),
            ],
            'base_sold_price' => (int) round($basePrice),
            'factors' => [
                'shop_margin' => $shopMargin,
                'speed_factor' => $speedFactor,
                'speed_label' => $this->getSpeedLabel($stats->avg_days),
                'avg_days' => (int) round($stats->avg_days),
                'mileage_factor' => $mileageFactor,
                'year_factor' => $yearFactor,
            ],
            'confidence' => $confidence,
            'data_count' => $stats->data_count,
        ];
    }

    /**
     * model_detail用: 万円単位の簡易レスポンス（既存のgetResaleStats互換）
     */
    public function getResaleStatsCompat(int $bikeModelId): array
    {
        $result = $this->calculate($bikeModelId);

        if ($result['status'] !== 'success') {
            return [];
        }

        return [
            'market_avg' => round($result['base_sold_price'] / 10000, 1),
            'resale_min' => round($result['price_range']['min'] / 10000, 1),
            'resale_max' => round($result['price_range']['max'] / 10000, 1),
            'data_count' => $result['data_count'],
            'confidence' => $result['confidence'],
            'factors' => $result['factors'],
        ];
    }

    /**
     * 車種別の売り切れ統計データを取得
     */
    private function getSoldStats(int $bikeModelId): object
    {
        return Cache::remember(
            "buyback_v2_stats_{$bikeModelId}",
            self::CACHE_TTL,
            function () use ($bikeModelId) {
                $result = DB::table('listings')
                    ->select(
                        DB::raw('AVG(total_price) as avg_sold_price'),
                        DB::raw('MIN(total_price) as min_sold_price'),
                        DB::raw('MAX(total_price) as max_sold_price'),
                        DB::raw('COUNT(*) as data_count'),
                        DB::raw('AVG(DATEDIFF(updated_at, created_at)) as avg_days')
                    )
                    ->where('bike_model_id', $bikeModelId)
                    ->where('is_sold_out', true)
                    ->whereNotNull('total_price')
                    ->where('total_price', '>', 0)
                    ->where('created_at', '>=', now()->subMonths(6))
                    ->first();

                return (object) [
                    'avg_sold_price' => (float) ($result->avg_sold_price ?? 0),
                    'min_sold_price' => (float) ($result->min_sold_price ?? 0),
                    'max_sold_price' => (float) ($result->max_sold_price ?? 0),
                    'data_count' => (int) ($result->data_count ?? 0),
                    'avg_days' => (float) ($result->avg_days ?? 30),
                ];
            }
        );
    }

    /**
     * 回転速度補正係数
     */
    private function getSpeedFactor(float $avgDays): float
    {
        return match (true) {
            $avgDays <= 7  => 1.10,
            $avgDays <= 14 => 1.05,
            $avgDays <= 30 => 1.00,
            $avgDays <= 60 => 0.95,
            default        => 0.90,
        };
    }

    /**
     * 回転速度ラベル
     */
    private function getSpeedLabel(float $avgDays): string
    {
        return match (true) {
            $avgDays <= 7  => '超人気（平均7日以内に売却）',
            $avgDays <= 14 => '人気（平均2週間以内に売却）',
            $avgDays <= 30 => '標準',
            $avgDays <= 60 => 'やや長め',
            default        => '長期在庫になりやすい',
        };
    }

    /**
     * 走行距離補正係数
     */
    private function getMileageFactor(?int $mileage): float
    {
        if ($mileage === null) return 1.00;

        return match (true) {
            $mileage < 5000   => 1.10,
            $mileage < 10000  => 1.05,
            $mileage < 20000  => 1.00,
            $mileage < 30000  => 0.90,
            $mileage < 50000  => 0.80,
            default           => 0.65,
        };
    }

    /**
     * 年式補正係数
     */
    private function getYearFactor(?int $year): float
    {
        if ($year === null) return 1.00;

        $age = (int) date('Y') - $year;

        return match (true) {
            $age <= 1  => 1.10,
            $age <= 3  => 1.00,
            $age <= 5  => 0.95,
            $age <= 10 => 0.85,
            $age <= 15 => 0.75,
            default    => 0.65,
        };
    }

    /**
     * データ信頼度
     */
    private function getConfidence(int $dataCount): string
    {
        return match (true) {
            $dataCount >= 30 => 'high',
            $dataCount >= 10 => 'medium',
            $dataCount >= 3  => 'low',
            default          => 'insufficient',
        };
    }

    /**
     * データ不足時のフォールバック（V1ロジック）
     */
    private function fallback(int $bikeModelId, ?int $mileage, ?int $year): array
    {
        $avgPrice = DB::table('listings')
            ->where('bike_model_id', $bikeModelId)
            ->where('is_sold_out', false)
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->avg('total_price') ?? 0;

        if ($avgPrice <= 0) {
            return ['status' => 'empty'];
        }

        $mileageFactor = $this->getMileageFactor($mileage);
        $yearFactor = $this->getYearFactor($year);

        $estimatedPrice = (int) round($avgPrice * 0.65 * $mileageFactor * $yearFactor);

        return [
            'status' => 'success',
            'estimated_price' => max(0, $estimatedPrice),
            'price_range' => [
                'min' => (int) round($estimatedPrice * 0.80),
                'max' => (int) round($estimatedPrice * 1.20),
            ],
            'base_sold_price' => 0,
            'factors' => [
                'shop_margin' => 0.65,
                'speed_factor' => 1.00,
                'speed_label' => 'データ不足',
                'avg_days' => 0,
                'mileage_factor' => $mileageFactor,
                'year_factor' => $yearFactor,
            ],
            'confidence' => 'insufficient',
            'data_count' => 0,
            'is_fallback' => true,
        ];
    }
}
