<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Repositories\Bike\ManufacturerRepository;
use App\Repositories\Bike\BikeModelRepository;
use App\Services\Bike\PriceStatsService;
use Illuminate\Database\Eloquent\Collection;

/**
 * 買取査定LPのビジネスロジック
 */
final class SellService
{
    public function __construct(
        private readonly ManufacturerRepository $manufacturerRepo,
        private readonly BikeModelRepository $modelRepo,
        private readonly PriceStatsService $priceStatsService
    ) {}

    /**
     * フォーム用のメーカー一覧を取得 (ID順)
     */
    public function getManufacturersForForm(): Collection
    {
        // BikeServiceを経由せず、直接リポジトリを使う形でもOKです
        return $this->manufacturerRepo->getAllSortedById();
    }

    /**
     * 査定額を計算して結果を整形して返す
     * * @param int $modelId 車種ID
     * @param int|null $year 年式
     * @return array
     */
    public function calculateAssessment(int $modelId, ?int $year): array
    {
        // 1. 車種情報の取得 (Repository経由)
        $model = $this->modelRepo->findWithManufacturer($modelId);

        if (!$model) {
            return ['status' => 'error', 'message' => '指定された車種が見つかりません。'];
        }

        // 2. 査定額の計算 (PriceStatsService利用)
        $result = $this->priceStatsService->estimatePurchasePrice($modelId, $year);

        // 3. 結果の整形 (コントローラーでやっていた結合処理)
        return array_merge($result, [
            'model_name' => $model->name,
            'maker_name' => $model->manufacturer->name ?? '',
            'year' => $year,
        ]);
    }
}