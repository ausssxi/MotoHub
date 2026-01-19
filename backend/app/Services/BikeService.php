<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BikeRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * バイク車種情報に関するビジネスロジックを提供するサービス
 * 
 * リポジトリから取得したデータを整形し、コントローラー向けに
 * 適切な形式で提供します。
 */
final class BikeService
{
    /**
     * @param BikeRepository $repository バイク車種情報のデータアクセスを担当するリポジトリ
     */
    public function __construct(
        private readonly BikeRepository $repository
    ) {}

    /**
     * トップページ用の人気バイク車種を取得
     * 
     * 出品数の多い順にソートされたバイク車種を16件取得します。
     * トップページの人気車種表示に使用されます。
     * 
     * @return Collection 出品数でソートされたバイク車種のコレクション
     */
    public function getPopularBikesForTopPage(): Collection
    {
        return $this->repository->getTopBikesByCount(16);
    }

    /**
     * 検索候補を取得
     * 
     * 入力されたキーワードに部分一致するバイク車種名を検索し、
     * 車種名と出品数を配列形式で返します。検索候補の表示に使用されます。
     * 
     * @param string $keyword 検索キーワード
     * @return array 検索候補の配列（各要素は 'name' と 'count' を含む）
     */
    public function getSearchSuggestions(string $keyword): array
    {
        // 10件程度に絞って候補を取得
        $models = $this->repository->searchNamesByKeyword($keyword, 10);

        return $models->map(fn($m) => [
            'name' => $m->name,
            'count' => $m->listings_count,
        ])->toArray();
    }

    /**
     * 全車種一覧ページ用のデータを取得
     * 
     * メーカーごとにグループ化された全車種情報と、全車種の総数を
     * 配列形式で返します。車種一覧ページの表示に使用されます。
     * 
     * @return array メーカー別車種情報と総数を含む配列（'manufacturers' と 'totalModelsCount'）
     */
    public function getAllModelsForIndex(): array
    {
        return [
            'manufacturers' => $this->repository->getAllModelsGroupedByManufacturer(),
            'totalModelsCount' => $this->repository->countAllModels(),
        ];
    }
}