<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BikeModel;
use App\Models\Listing;
use App\Services\Bike\ListingSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * フロントエンドからの非同期リクエストを処理するAPIコントローラー
 */
final class BikeApiController extends Controller
{
    public function __construct(
        private readonly ListingSearchService $searchService
    ) {}

    /**
     * 現在の絞り込み条件に基づくヒット件数を返す
     * GET /api/bikes/count
     */
    public function count(Request $request): JsonResponse
    {
        // 検索パラメータの取得
        $keyword = $request->query('keyword');
        $prefecture = $request->query('prefecture');

        // フィルター項目の抽出
        $filters = $request->only([
            'min_price', 'max_price',
            'min_mileage', 'max_mileage',
            'min_year', 'max_year',
            'is_new', 'has_repair_history',
            'manufacturer_id', 'bike_model_id',
        ]);

        // 件数の取得（Service側のロジックを利用）
        $count = $this->searchService->getFilteredCount($keyword, $prefecture, $filters);

        return response()->json([
            'count' => $count,
        ]);
    }

    /**
     * 閲覧履歴の車種スラグ群 → 現在の active 在庫数（再訪フック「あなたが見た車種の新着」用）。
     * GET /api/bikes/viewed-stock?models=mfrSlug/modelSlug,mfrSlug/modelSlug,...（最大10）
     *
     * 個人情報は受け取らない（スラグのみ）。在庫は「新着検知」目的のためキャッシュせずライブ集計。
     * クエリはモデル解決＋COUNT(GROUP BY)の定数本数で、車種数に対してN+1にならない。
     */
    public function viewedStock(Request $request): JsonResponse
    {
        $pairs = collect(explode(',', (string) $request->query('models', '')))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->take(10) // 再訪フックは直近10件まで
            ->map(function ($s) {
                [$mfr, $model] = array_pad(explode('/', $s, 2), 2, null);

                return ($mfr && $model) ? ['mfr' => $mfr, 'model' => $model] : null;
            })
            ->filter()
            ->unique(fn ($p) => $p['mfr'].'/'.$p['model'])
            ->values();

        if ($pairs->isEmpty()) {
            return response()->json([]);
        }

        // ① (mfrSlug, modelSlug) から車種を解決（1クエリ＋manufacturer eager）
        $models = BikeModel::query()
            ->whereIn('slug', $pairs->pluck('model')->all())
            ->whereHas('manufacturer', fn ($q) => $q->whereIn('slug', $pairs->pluck('mfr')->all()))
            ->with('manufacturer:id,slug')
            ->get(['id', 'slug', 'manufacturer_id']);

        // 別メーカーの同名スラグ混入を排除し、要求ペアに正確一致するものだけ残す
        $wanted = $pairs->map(fn ($p) => $p['mfr'].'/'.$p['model'])->flip();
        $models = $models->filter(fn ($m) => isset($wanted[($m->manufacturer->slug ?? '').'/'.$m->slug]));

        if ($models->isEmpty()) {
            return response()->json([]);
        }

        // ② active 在庫数を GROUP BY で一括集計（N+1なし）
        $counts = Listing::whereIn('bike_model_id', $models->pluck('id')->all())
            ->active()
            ->groupBy('bike_model_id')
            ->selectRaw('bike_model_id, COUNT(*) as c')
            ->pluck('c', 'bike_model_id');

        $out = [];
        foreach ($models as $m) {
            $out[($m->manufacturer->slug ?? '').'/'.$m->slug] = (int) ($counts[$m->id] ?? 0);
        }

        return response()->json($out);
    }

    /**
     * 特定メーカーの車種一覧を返す（ドリルダウン用）
     * GET /api/manufacturers/{manufacturer}/models
     */
    public function models(int $manufacturerId): JsonResponse
    {
        $models = Cache::remember(
            "api_models_by_manufacturer_v1_{$manufacturerId}",
            1800,
            fn () => $this->searchService->getModelsByManufacturer($manufacturerId)
        );

        return response()->json($models);
    }

    /**
     * 特定メーカーの車種一覧（軽量版: id, name のみ）
     * GET /api/manufacturers/{manufacturer}/models-light
     */
    public function modelsLight(int $manufacturerId): JsonResponse
    {
        $models = BikeModel::where('manufacturer_id', $manufacturerId)
            ->select(['id', 'name'])
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($models);
    }
}
