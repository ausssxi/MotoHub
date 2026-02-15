<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Repositories\Bike\ManufacturerRepository;
use App\Repositories\Bike\CategoryRepository;
use App\Repositories\Bike\BikeModelRepository; // ★追加
use Illuminate\Support\Str;

/**
 * SEO着地ページ用のロジッククラス
 * URLパラメータから検索条件やメタデータを生成します
 */
final class SeoLandingService
{
    public function __construct(
        private readonly ManufacturerRepository $manufacturerRepo,
        private readonly CategoryRepository $categoryRepo,
        private readonly BikeModelRepository $modelRepo // ★追加
    ) {}

    /**
     * パラメータを解析してページ情報を生成
     *
     * @param string $prefecture 都道府県名 (例: 東京都)
     * @param string $slug メーカー名、カテゴリ名、車種名、排気量キーワード
     */
    public function resolvePageInfo(string $prefecture, string $slug): array
    {
        $filters = ['prefecture' => $prefecture];
        $typeLabel = '';
        $type = 'unknown';

        // 1. メーカー判定 (例: ホンダ, Yamaha)
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
        // 2. カテゴリ判定 (例: ネイキッド, scooter)
        elseif ($category = $this->findCategory($slug)) {
            $filters['category_id'] = $category->id;
            $typeLabel = $category->name;
            $type = 'category';
        }
        // 3. ★追加: 車種名判定 (例: PCX, CB400SF)
        elseif ($model = $this->findModel($slug)) {
            $filters['bike_model_id'] = $model->id;
            // メーカー名も含めるとSEO的に強い (例: ホンダ PCX)
            $makerName = $model->manufacturer->name ?? '';
            $typeLabel = "{$makerName} {$model->name}";
            $type = 'model';
        }
        // 4. ★追加: 排気量・免許区分判定 (例: 原付, 大型)
        elseif ($displacement = $this->findDisplacement($slug)) {
            if (isset($displacement['min'])) $filters['min_displacement'] = $displacement['min'];
            if (isset($displacement['max'])) $filters['max_displacement'] = $displacement['max'];
            $typeLabel = $displacement['label'];
            $type = 'displacement';
        }

        // どれにも該当しない場合は空を返す
        if (empty($typeLabel)) {
            return [];
        }

        return [
            'filters' => $filters,
            'meta' => $this->generateMetaData($prefecture, $typeLabel, $type)
        ];
    }

    /**
     * カテゴリを検索
     */
    private function findCategory(string $slug)
    {
        $categories = $this->categoryRepo->getAllSorted();
        return $categories->first(function ($c) use ($slug) {
            return strtolower($c->slug) === strtolower($slug) || $c->name === $slug;
        });
    }

    /**
     * 車種名を検索
     */
    private function findModel(string $slug)
    {
        // searchByName は部分一致検索なので、先頭1件を取得して検証
        // (厳密にするなら完全一致チェックを入れる)
        $models = $this->modelRepo->searchByName($slug, 1);
        return $models->first();
    }

    /**
     * 排気量キーワードを判定
     */
    private function findDisplacement(string $slug): ?array
    {
        $map = [
            '原付' => ['min' => 0, 'max' => 50, 'label' => '原付バイク(50cc以下)'],
            'スクーター' => ['min' => 0, 'max' => 125, 'label' => 'スクーター・原付二種'], // カテゴリになければここで拾う
            '小型' => ['min' => 51, 'max' => 125, 'label' => '小型バイク(125cc以下)'],
            '原付二種' => ['min' => 51, 'max' => 125, 'label' => '原付二種'],
            '中型' => ['min' => 126, 'max' => 400, 'label' => '中型バイク(400cc以下)'],
            '普通二輪' => ['min' => 126, 'max' => 400, 'label' => '普通二輪'],
            '大型' => ['min' => 401, 'max' => null, 'label' => '大型バイク'],
            'リッター' => ['min' => 1000, 'max' => null, 'label' => 'リッターバイク'],
        ];

        foreach ($map as $key => $value) {
            if (str_contains($slug, $key)) {
                return $value;
            }
        }
        return null;
    }

    /**
     * タイプに応じたSEOメタデータを生成
     */
    private function generateMetaData(string $prefecture, string $label, string $type): array
    {
        // デフォルト
        $title = "{$prefecture}の{$label} バイク在庫一覧";
        $h1 = "{$prefecture}の<span class='text-blue-600'>{$label}</span>在庫一覧";
        $description = "{$prefecture}で販売中の{$label}をまとめて検索！MotoHubなら、複数のバイクショップの在庫を一括で比較・検討できます。";

        // タイプ別の最適化（クリック率向上）
        switch ($type) {
            case 'model': // 車種名の場合（一番購買意欲が高い）
                $title = "{$prefecture}の{$label} 中古車・新車在庫【相場・価格比較】";
                $description = "{$prefecture}の{$label}の中古車・新車を掲載中。支払総額の安い順や走行距離の少ない順で比較できます。相場情報や買取価格もチェック！";
                break;

            case 'maker': // メーカーの場合
                $title = "{$prefecture}の{$label} バイク在庫一覧 | MotoHub";
                break;
            
            case 'displacement': // 排気量の場合
                $title = "{$prefecture}の{$label} 中古・新車を探す";
                break;
        }

        return [
            'title' => $title,
            'description' => $description,
            'h1_html' => $h1,
            'prefecture' => $prefecture,
            'target_name' => $label,
            'type' => $type,
        ];
    }
}