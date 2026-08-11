<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Repositories\Bike\ManufacturerRepository;
use App\Repositories\Bike\BikeModelRepository;
use App\Services\BuybackPriceCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * 買取査定LPのビジネスロジック
 */
final class SellService
{
    /**
     * 査定フォームのメーカー一覧キャッシュ。
     *
     * 元になるクエリはメーカー1件ごとの相関COUNTサブクエリで、listings に
     * (manufacturer_id, is_sold_out) の複合インデックスが無いため1回1秒超かかる。
     * ビューが使うのは id と name だけで、件数は並び順を決めるためだけに使う
     * （在庫の増減で並びが分単位に変わることはない）ので、1時間の陳腐化は許容できる。
     *
     * キャッシュはリポジトリ側ではなくここに置く。リポジトリは汎用の入口で、
     * 他の呼び出し元が暗黙にキャッシュ済みデータを掴むのを避けるため。
     */
    private const FORM_MANUFACTURERS_CACHE_KEY = 'sell_form_manufacturers_v1';

    private const FORM_MANUFACTURERS_CACHE_TTL = 3600;

    public function __construct(
        private readonly ManufacturerRepository $manufacturerRepo,
        private readonly BikeModelRepository $modelRepo,
        private readonly BuybackPriceCalculator $calculator
    ) {}

    /**
     * フォーム用のメーカー一覧を取得（在庫の多い順・在庫0のメーカーも末尾に残す）。
     *
     * 査定フォームは「売りたい人」が使うもので、当サイトの在庫状況とは無関係。
     * 在庫0のメーカーを落とすと、そのメーカーのバイクを売りたい人が車種を選べない。
     * そのため在庫0も残す getAllSortedByListingCount() を使う（検索用コンボボックスと同じ方針）。
     */
    public function getManufacturersForForm(): Collection
    {
        return Cache::remember(
            self::FORM_MANUFACTURERS_CACHE_KEY,
            self::FORM_MANUFACTURERS_CACHE_TTL,
            fn () => $this->manufacturerRepo->getAllSortedByListingCount(),
        );
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
