<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\User;
use App\Repositories\Bike\BrowsingHistoryRepository;
use Illuminate\Support\Collection;

/**
 * 閲覧履歴のビジネスロジック
 */
final class BrowsingHistoryService
{
    private const MAX_HISTORY_COUNT = 50;

    public function __construct(
        private readonly BrowsingHistoryRepository $repository
    ) {}

    /**
     * 履歴を記録する
     * 既に存在すれば日時更新、なければ新規作成（上限超えなら古いのを削除）
     */
    public function recordHistory(User $user, int $listingId): void
    {
        // 既存チェック
        $history = $this->repository->findByUserAndListing($user->id, $listingId);

        if ($history) {
            // あれば日時更新
            $this->repository->touch($history);
        } else {
            // なければ新規作成（その前に上限チェック）
            $count = $this->repository->countByUser($user->id);
            if ($count >= self::MAX_HISTORY_COUNT) {
                $this->repository->deleteOldestByUser($user);
            }

            $this->repository->create($user->id, $listingId);
        }
    }

    /**
     * ユーザーの履歴ID一覧を取得
     */
    public function getUserHistoryIds(User $user): Collection
    {
        return $this->repository->getListingIdsByUser($user);
    }

    /**
     * ローカルデータの同期（統合）
     */
    public function syncLocalHistory(User $user, array $localIds): void
    {
        // 古い順に処理することで、最新のものが最後に記録され、順序が保たれるようにする
        foreach (array_reverse($localIds) as $id) {
            // DBに存在しない場合のみ追加（または更新）
            // recordHistoryを使えば上限チェックも自動で行われるため安全です
            $this->recordHistory($user, (int)$id);
        }
    }
}