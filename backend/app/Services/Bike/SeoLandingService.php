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
            return $m->name !== null && (strtolower($m->name) === strtolower($slug) ||
                   str_contains(strtolower($m->name), strtolower($slug)));
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
            $makerName = $model->manufacturer?->name ?? '';
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
     * カタログページ（地域なし）のslugを解析してページ情報を生成
     *
     * slug例:
     *   "honda-naked"  → メーカー×カテゴリ
     *   "250cc"        → 排気量帯
     *   "honda"        → メーカー全車種
     */
    public function resolveCatalogPage(string $slug): array
    {
        $filters = [];
        $typeLabel = '';
        $type = 'unknown';

        // 1. 排気量帯判定 (例: 250cc, 125cc, 400cc)
        if (preg_match('/^(\d+)cc$/', $slug, $matches)) {
            $cc = (int) $matches[1];
            $displacement = $this->mapCcToRange($cc);
            if ($displacement) {
                if (isset($displacement['min'])) $filters['min_displacement'] = $displacement['min'];
                if (isset($displacement['max'])) $filters['max_displacement'] = $displacement['max'];
                $typeLabel = $displacement['label'];
                $type = 'displacement';
            }
        }

        // 2. メーカー×カテゴリ判定 (例: honda-naked)
        if (empty($typeLabel) && str_contains($slug, '-')) {
            [$mfrSlug, $catSlug] = explode('-', $slug, 2);

            $manufacturers = $this->manufacturerRepo->getAllSortedByName();
            $maker = $manufacturers->first(fn($m) => $m->slug !== null && strtolower($m->slug) === strtolower($mfrSlug));

            $categories = $this->categoryRepo->getAllSorted();
            $category = $categories->first(fn($c) => $c->slug !== null && strtolower($c->slug) === strtolower($catSlug));

            if ($maker && $category) {
                $filters['manufacturer_id'] = $maker->id;
                $filters['category_id'] = $category->id;
                $typeLabel = "{$maker->name} {$category->name}";
                $type = 'maker_category';
            }
        }

        // 3. メーカー単体判定 (例: honda)
        if (empty($typeLabel)) {
            $manufacturers = $this->manufacturerRepo->getAllSortedByName();
            $maker = $manufacturers->first(fn($m) => $m->slug !== null && strtolower($m->slug) === strtolower($slug));

            if ($maker) {
                $filters['manufacturer_id'] = $maker->id;
                $typeLabel = $maker->name;
                $type = 'maker';
            }
        }

        if (empty($typeLabel)) {
            return [];
        }

        return [
            'filters' => $filters,
            'meta' => $this->generateCatalogMetaData($typeLabel, $type, $slug),
        ];
    }

    /**
     * cc数値を排気量帯にマッピング
     */
    private function mapCcToRange(int $cc): ?array
    {
        return match (true) {
            $cc <= 50  => ['min' => 0, 'max' => 50, 'label' => '50cc以下（原付）バイク'],
            $cc <= 125 => ['min' => 51, 'max' => 125, 'label' => "{$cc}cc以下バイク"],
            $cc <= 250 => ['min' => 126, 'max' => 250, 'label' => "{$cc}ccバイク"],
            $cc <= 400 => ['min' => 126, 'max' => 400, 'label' => "{$cc}cc以下（中型）バイク"],
            $cc <= 750 => ['min' => 401, 'max' => 750, 'label' => "{$cc}ccバイク"],
            default    => ['min' => 401, 'max' => null, 'label' => "大型（{$cc}cc〜）バイク"],
        };
    }

    /**
     * カタログページ用のSEOメタデータを生成
     */
    private function generateCatalogMetaData(string $label, string $type, string $slug): array
    {
        $title = "{$label} 中古バイク・新車一覧";
        $h1 = "<span class='text-blue-600'>{$label}</span> 中古バイク・新車一覧";
        $description = "{$label}の中古バイク・新車を一括検索。MotoHubなら、複数のバイクショップの在庫を一括で比較・検討できます。価格相場やスペックの比較も簡単です。";

        if ($type === 'maker_category') {
            $title = "{$label} 中古バイク・新車【在庫一覧・価格比較】";
            $description = "{$label}の中古・新車バイクの在庫一覧。支払総額の安い順や走行距離の少ない順で比較できます。{$label}の相場情報も掲載中。";
        } elseif ($type === 'displacement') {
            $title = "{$label} 中古・新車を探す | MotoHub";
        }

        return [
            'title' => $title,
            'description' => $description,
            'h1_html' => $h1,
            'target_name' => $label,
            'type' => $type,
            'slug' => $slug,
        ];
    }

    /**
     * カテゴリを検索
     */
    private function findCategory(string $slug)
    {
        $categories = $this->categoryRepo->getAllSorted();
        return $categories->first(function ($c) use ($slug) {
            return ($c->slug !== null && strtolower($c->slug) === strtolower($slug)) || $c->name === $slug;
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