<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\BrowsingHistory;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * 閲覧履歴テーブル(browsing_histories)のデータ操作を担当
 */
final class BrowsingHistoryRepository
{
    /**
     * 特定のユーザーと車両の履歴を取得
     */
    public function findByUserAndListing(int $userId, int $listingId): ?BrowsingHistory
    {
        return BrowsingHistory::where('user_id', $userId)
            ->where('listing_id', $listingId)
            ->first();
    }

    /**
     * 履歴の更新日時を現在に更新
     */
    public function touch(BrowsingHistory $history): bool
    {
        return $history->touch();
    }

    /**
     * 履歴を新規作成
     */
    public function create(int $userId, int $listingId): BrowsingHistory
    {
        return BrowsingHistory::create([
            'user_id' => $userId,
            'listing_id' => $listingId,
        ]);
    }

    /**
     * ユーザーの履歴件数を取得
     */
    public function countByUser(int $userId): int
    {
        return BrowsingHistory::where('user_id', $userId)->count();
    }

    /**
     * ユーザーの一番古い履歴を削除
     */
    public function deleteOldestByUser(User $user): void
    {
        // リレーション経由で削除（中間テーブルのレコード削除）
        $user->browsingHistories()
             ->orderByPivot('updated_at', 'asc')
             ->limit(1)
             ->detach();
    }

    /**
     * ユーザーの履歴にある車両ID一覧を取得（新しい順）
     */
    public function getListingIdsByUser(User $user): Collection
    {
        return $user->browsingHistories()
                    ->pluck('listings.id');
    }

    /**
     * 履歴が存在するかチェック
     */
    public function exists(int $userId, int $listingId): bool
    {
        return BrowsingHistory::where('user_id', $userId)
            ->where('listing_id', $listingId)
            ->exists();
    }
}