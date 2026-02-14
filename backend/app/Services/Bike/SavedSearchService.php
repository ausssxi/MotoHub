<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\SavedSearch;
use App\Models\User;
use App\Repositories\Bike\SavedSearchRepository;
use Exception;

/**
 * 検索条件保存のビジネスロジック
 */
final class SavedSearchService
{
    // 保存件数の上限
    private const MAX_SAVED_SEARCHES = 10;

    public function __construct(
        private readonly SavedSearchRepository $repository
    ) {}

    /**
     * 検索条件を保存する
     * * @throws Exception 上限エラーなど
     */
    public function saveSearchCondition(User $user, array $conditions): SavedSearch
    {
        // 1. 保存数の上限チェック
        if ($this->repository->countByUser($user->id) >= self::MAX_SAVED_SEARCHES) {
            throw new Exception('保存できる条件は10件までです。不要な条件を削除してください。', 400);
        }

        // 2. 名前の自動生成（指定がなければ）
        // name_override は保存データ本体には含めないので、変数として取り出しておく
        $name = $conditions['name_override'] ?? $this->generateName($conditions);
        
        // DBに保存しない一時的なパラメータを除外
        unset($conditions['name_override']);

        // 3. データ作成
        $data = [
            'user_id' => $user->id,
            'name' => $name,
            'conditions' => $conditions, // 配列のまま渡せばModelのcastsでJSONになる
            'is_active' => true,
            'mail_notify' => true,
        ];

        return $this->repository->create($data);
    }

    /**
     * 検索条件からわかりやすい名前を自動生成
     */
    private function generateName(array $conditions): string
    {
        $parts = [];

        if (!empty($conditions['keyword'])) {
            $parts[] = "KW:{$conditions['keyword']}";
        }
        
        if (!empty($conditions['min_price']) || !empty($conditions['max_price'])) {
            $parts[] = '価格指定';
        }

        if (!empty($conditions['manufacturer_id'])) {
            $parts[] = 'メーカー指定';
        }

        if (!empty($conditions['bike_model_id'])) {
            $parts[] = '車種指定';
        }

        return !empty($parts) ? implode(' / ', $parts) : '条件なし検索';
    }
}