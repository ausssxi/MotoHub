<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * レビューデータの操作を担当
 */
final class ReviewRepository
{
    /**
     * レビュー一覧ハブ用の最新フィード（承認済み・"テスト"系シードを除外・車種情報付き）。
     * maker(manufacturer_id) / cc(帯 min-max) でブラウズ絞り込み。N+1なし。
     *
     * @param  array{maker?: int|null, cc_min?: int|null, cc_max?: int|null}  $filters
     */
    public function getFeed(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Review::query()
            // representativeListing も eager load（image_url accessor がカード毎に引かないよう N+1防止）
            // user は投稿者アバター表示用（ゲスト=user_id null は null で非クエリ）。
            ->with(['bikeModel.manufacturer', 'bikeModel.representativeListing', 'user'])
            ->where('is_approved', true)
            // "テスト"系の明らかなシードはハブ表示から除外（DBは消さない・前方一致）
            ->where('nickname', 'not like', 'テスト%')
            ->where('title', 'not like', 'テスト%')
            ->whereHas('bikeModel'); // 車種が無いものは出さない

        if (! empty($filters['maker'])) {
            $maker = (int) $filters['maker'];
            $query->whereHas('bikeModel', fn ($q) => $q->where('manufacturer_id', $maker));
        }

        if (isset($filters['cc_min'], $filters['cc_max'])) {
            $min = (int) $filters['cc_min'];
            $max = (int) $filters['cc_max'];
            $query->whereHas('bikeModel', fn ($q) => $q->whereBetween('displacement', [$min, $max]));
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * ハブのメーカー絞り込みチップ用：レビューのある（"テスト"除外後）メーカーと件数。
     */
    public function reviewMakerCounts(): Collection
    {
        return DB::table('reviews')
            ->join('bike_models', 'reviews.bike_model_id', '=', 'bike_models.id')
            ->join('manufacturers', 'bike_models.manufacturer_id', '=', 'manufacturers.id')
            ->where('reviews.is_approved', true)
            ->where('reviews.nickname', 'not like', 'テスト%')
            ->where('reviews.title', 'not like', 'テスト%')
            ->select('manufacturers.id', 'manufacturers.name', DB::raw('COUNT(*) as c'))
            ->groupBy('manufacturers.id', 'manufacturers.name')
            ->orderByDesc('c')
            ->get();
    }

    /**
     * レビューを新規作成
     */
    public function create(array $data): Review
    {
        return Review::create($data);
    }

    /**
     * 最新のレビューを取得（車種情報付き）
     */
    public function getLatest(int $limit = 6): Collection
    {
        return Review::with(['bikeModel.manufacturer', 'user']) // user=投稿者アバター用
            ->where('is_approved', true)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 特定の車種の最新レビューを取得する
     */
    public function getLatestByModelId(int $modelId, int $limit = 3): Collection
    {
        return Review::with(['bikeModel.manufacturer', 'user']) // N+1対策（user=投稿者アバター用）
            ->where('bike_model_id', $modelId)
            ->where('is_approved', true) // 既存のロジックに合わせて承認済みのみに絞る
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
