<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Repositories\Bike\ManufacturerRepository;
use App\Repositories\Bike\BikeModelRepository;
use App\Services\BuybackPriceCalculator;
use Illuminate\Database\Eloquent\Collection;

/**
 * 買取査定LPのビジネスロジック
 */
final class SellService
{
    public function __construct(
        private readonly ManufacturerRepository $manufacturerRepo,
        private readonly BikeModelRepository $modelRepo,
        private readonly BuybackPriceCalculator $calculator
    ) {}

    /**
     * フォーム用のメーカー一覧を取得 (ID順)
     */
    public function getManufacturersForForm(): Collection
    {
        return $this->manufacturerRepo->getAllSortedById();
    }

    /**
     * 査定額を計算して結果を整形して返す
     */
    public function calculateAssessment(int $modelId, ?int $year, ?int $mileage = null): array
    {
        $model = $this->modelRepo->findWithManufacturer($modelId);

        if (!$model) {
            return ['status' => 'error', 'message' => '指定された車種が見つかりません。'];
        }

        // V2: BuybackPriceCalculator を使用
        $result = $this->calculator->calculate(
            bikeModelId: $modelId,
            mileage: $mileage,
            year: $year,
        );

        if ($result['status'] === 'empty') {
            return $result;
        }

        // 万円単位に変換してレスポンス（既存フロントとの互換性を保持）
        $estimatedPrice = $result['estimated_price'];
        $rangeMin = $result['price_range']['min'];
        $rangeMax = $result['price_range']['max'];

        return array_merge($result, [
            'purchase_min' => number_format($rangeMin / 10000),
            'purchase_max' => number_format($rangeMax / 10000),
            'retail_avg' => number_format(round(($result['base_sold_price'] ?: $estimatedPrice) / 10000, 1), 1),
            'estimated_man' => number_format(round($estimatedPrice / 10000)),
            'model_name' => $model->name,
            'maker_name' => $model->manufacturer->name ?? '',
            'year' => $year,
            'mileage' => $mileage,
            'is_fallback' => $result['confidence'] === 'insufficient',
        ]);
    }
}
