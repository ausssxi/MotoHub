<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Repositories\Bike\ManufacturerRepository;
use App\Repositories\Bike\CategoryRepository;
use Illuminate\Support\Str;

/**
 * SEO着地ページ用のロジッククラス
 * URLパラメータから検索条件やメタデータを生成します
 */
final class SeoLandingService
{
    public function __construct(
        private readonly ManufacturerRepository $manufacturerRepo,
        private readonly CategoryRepository $categoryRepo
    ) {}

    /**
     * パラメータを解析してページ情報を生成
     *
     * @param string $prefecture 都道府県名 (例: 東京都)
     * @param string $slug メーカー名またはカテゴリ名 (例: ホンダ, scooter)
     */
    public function resolvePageInfo(string $prefecture, string $slug): array
    {
        $filters = ['prefecture' => $prefecture];
        $typeLabel = '';
        $type = 'unknown';

        // 1. メーカー判定
        // 英名(honda)でも和名(ホンダ)でもヒットするように検索
        $manufacturers = $this->manufacturerRepo->getAllSortedByName();
        $maker = $manufacturers->first(function ($m) use ($slug) {
            return strtolower($m->name) === strtolower($slug) || 
                   str_contains(strtolower($m->name), strtolower($slug));
        });

        if ($maker) {
            $filters['manufacturer_id'] = $maker->id;
            $typeLabel = $maker->name;
            $type = 'maker';
        } 
        else {
            // 2. カテゴリ判定
            // slug(naked)でも名前(ネイキッド)でもヒットするように検索
            $categories = $this->categoryRepo->getAllSorted();
            $category = $categories->first(function ($c) use ($slug) {
                return strtolower($c->slug) === strtolower($slug) || 
                       $c->name === $slug;
            });

            if ($category) {
                $filters['category_id'] = $category->id;
                $typeLabel = $category->name;
                $type = 'category';
            }
        }

        // どちらにも該当しない場合はnullを返す（404扱い）
        if (empty($typeLabel)) {
            return [];
        }

        // SEO用テキスト生成
        $title = "{$prefecture}の{$typeLabel} バイク在庫一覧";
        $description = "{$prefecture}で販売中の{$typeLabel}の中古バイク・新車バイクをまとめて検索！MotoHubなら、複数のバイクショップの在庫を一括で比較・検討できます。";
        $h1 = "{$prefecture}の<span class='text-blue-600'>{$typeLabel}</span>在庫一覧";

        return [
            'filters' => $filters,
            'meta' => [
                'title' => $title,
                'description' => $description,
                'h1_html' => $h1,
                'prefecture' => $prefecture,
                'target_name' => $typeLabel,
                'type' => $type,
            ]
        ];
    }
}