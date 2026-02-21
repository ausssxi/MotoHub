<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Repositories\Bike\BikeModelRepository;
use App\Repositories\Bike\ListingRepository;
use App\Repositories\Bike\ManufacturerRepository;
use App\Repositories\Bike\CategoryRepository;
use App\Repositories\Bike\ReviewRepository;
use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\Review;
use Illuminate\Support\Collection;

/**
 * 車種マスタ・メーカー情報のビジネスロジック
 * フォルダ移動: Services/Bike/ 配下へ
 */
final class BikeService
{
    public function __construct(
        private readonly BikeModelRepository $modelRepo,
        private readonly ManufacturerRepository $manufacturerRepo,
        private readonly ListingRepository $listingRepo,
        private readonly CategoryRepository $categoryRepo,
        private readonly ReviewRepository $reviewRepo
    ) {}

    /**
     * 地域・都道府県データを取得
     */
    public function getRegions(): array
    {
        return config('bike.regions', []);
    }

    /**
     * 免許区分（排気量）データを取得
     */
    public function getLicenses(): array
    {
        return config('bike.licenses', []);
    }

    /**
     * トップページ表示用の主要メーカーを取得
     * (ホンダ、ヤマハ、スズキ、カワサキ、ハーレー の順で取得)
     */
    public function getMajorManufacturers(): Collection
    {
        // 全メーカーを取得
        $allMakers = $this->manufacturerRepo->getAllSortedByName();
        
        // 表示したいメーカー名のリスト（順序を維持したい場合）
        $targetNames = ['ホンダ', 'ヤマハ', 'スズキ', 'カワサキ', 'ハーレーダビッドソン'];
        
        $results = new Collection();

        foreach ($targetNames as $name) {
            // 名前で検索（部分一致など柔軟に）
            $found = $allMakers->first(function ($m) use ($name) {
                return str_contains($m->name, $name) || str_contains(strtolower($m->name), strtolower($name));
            });

            if ($found) {
                $results->push($found);
            }
        }

        return $results;
    }

    /**
     * レビューを投稿する
     */
    public function createReview(int $bikeModelId, array $data): Review
    {
        // データの整形（ビジネスロジック）はここで行い、
        // 純粋な保存処理だけをリポジトリに任せます
        return $this->reviewRepo->create([
            'bike_model_id' => $bikeModelId,
            'nickname'      => $data['nickname'] ?: '名無しライダー',
            'rating'        => $data['rating'],
            'title'         => $data['title'],
            'body'          => $data['body'],
            'is_approved'   => true, // 即時公開設定
        ]);
    }

    /**
     * 車種詳細情報の取得
     */
    public function getBikeModelDetail(int $id): BikeModel
    {
        return $this->modelRepo->findDetailOrFail($id);
    }

    /**
     *  全てのメーカーを取得するメソッドに変更
     * （以前の getMajorManufacturers を置き換え）
     */
    public function getAllManufacturers(): Collection
    {
        // ManufacturerRepository に getAllSortedByName がある前提です
        // もしエラーになる場合は getAll() に変えてください
        return $this->manufacturerRepo->getAllSortedByName();
    }

    /**
     * 全てのメーカーを取得 (ID順)
     * 買取査定などで「国内4メーカー」を上に表示したい場合に使用
     */
    public function getAllManufacturersById(): Collection
    {
        return $this->manufacturerRepo->getAllSortedById();
    }

    /**
     * トップページ用カテゴリ一覧
     * 画像があるカテゴリのみを返すように変更
     */
    public function getCategoriesForTopPage(): Collection
    {
        return $this->categoryRepo->getWithImagesSorted();
    }

    /**
     * トップページ用の人気車種
     */
    public function getPopularBikesForTopPage(): Collection
    {
        return $this->modelRepo->getTopModels(16);
    }

    /**
     * 検索サジェスト用
     */
    public function getSearchSuggestions(string $keyword): array
    {
        $models = $this->modelRepo->searchByName($keyword, 10);
        return $models->map(fn($m) => [
            'name' => $m->name,
            'count' => $m->listings_count,
        ])->toArray();
    }

    /**
     * 車種一覧ページ用のデータ
     */
    public function getAllModelsForIndex(): array
    {
        $manufacturersRaw = $this->manufacturerRepo->getAll();
        $totalModelsCount = 0;

        // メーカーごとにデータを整形
        $formattedManufacturers = $manufacturersRaw->map(function ($maker) use (&$totalModelsCount) {
            // 1. 車種一覧を取得
            $models = $this->modelRepo->getByManufacturerId((int)$maker->id);
            $count = $models->count();
            $totalModelsCount += $count;

            if ($count === 0) {
                return null; // 車種がないメーカーは除外用
            }

            // 2. 代表画像の決定ロジック (掲載数が多い順 && 画像があるもの優先)
            $topModel = $models->sortByDesc('listings_count')->first(fn($m) => !empty($m->image_url)) 
                        ?? $models->sortByDesc('listings_count')->first();
            $makerImage = $topModel?->image_url;

            // 3. グループ分けロジック
            $groups = $this->groupModelsByName($models);

            // Viewで使いやすい配列にして返す
            return [
                'id' => $maker->id,
                'name' => $maker->name,
                'bike_models_count' => $count,
                'image_url' => $makerImage,
                'groups' => $groups,
            ];
        })->filter(); // nullを除外

        return [
            'manufacturers' => $formattedManufacturers,
            'totalModelsCount' => $totalModelsCount
        ];
    }

    /**
     * 車種リストを名前の頭文字でグループ分けする
     */
    private function groupModelsByName(Collection $models): array
    {
        // グループの初期化
        $groups = [];
        foreach (range('A', 'Z') as $char) $groups[$char] = [];
        foreach (['あ行', 'か行', 'さ行', 'た行', 'な行', 'は行', 'ま行', 'や行', 'ら行', 'わ行'] as $row) $groups[$row] = [];
        $groups['0-9'] = [];
        $groups['その他'] = [];

        foreach ($models as $bike) {
            // 半角カタカナを全角に変換し、先頭1文字を取得
            $firstChar = mb_convert_kana(mb_substr($bike->name, 0, 1), 'KaC');

            if (preg_match('/^[0-9]/', $firstChar)) {
                $groups['0-9'][] = $bike;
            } elseif (preg_match('/^[A-Za-z]/', $firstChar)) {
                $key = strtoupper(substr($firstChar, 0, 1));
                if (isset($groups[$key])) {
                    $groups[$key][] = $bike;
                } else {
                    $groups['その他'][] = $bike;
                }
            } elseif (preg_match('/^[ア-オァ-ォヴ]/u', $firstChar)) {
                $groups['あ行'][] = $bike;
            } elseif (preg_match('/^[カ-コガ-ゴヵヶ]/u', $firstChar)) {
                $groups['か行'][] = $bike;
            } elseif (preg_match('/^[サ-ソザ-ゾ]/u', $firstChar)) {
                $groups['さ行'][] = $bike;
            } elseif (preg_match('/^[タ-トダ-ドッ]/u', $firstChar)) {
                $groups['た行'][] = $bike;
            } elseif (preg_match('/^[ナ-ノ]/u', $firstChar)) {
                $groups['な行'][] = $bike;
            } elseif (preg_match('/^[ハ-ホバ-ボパ-ポ]/u', $firstChar)) {
                $groups['は行'][] = $bike;
            } elseif (preg_match('/^[マ-モ]/u', $firstChar)) {
                $groups['ま行'][] = $bike;
            } elseif (preg_match('/^[ヤ-ヨャ-ョ]/u', $firstChar)) {
                $groups['や行'][] = $bike;
            } elseif (preg_match('/^[ラ-ロ]/u', $firstChar)) {
                $groups['ら行'][] = $bike;
            } elseif (preg_match('/^[ワ-ンヮ]/u', $firstChar)) {
                $groups['わ行'][] = $bike;
            } else {
                $groups['その他'][] = $bike;
            }
        }

        return $groups;
    }

    /**
     * 関連車両の取得 (Repositoryへ委譲)
     */
    public function getRelatedListings(int $bikeModelId, int $excludeId, int $limit = 8): Collection
    {
        return $this->listingRepo->getRelatedListings($bikeModelId, $excludeId, $limit);
    }

    /**
     * 詳細ページ用のSEOリンク集を生成
     */
    public function getSeoLinks(Listing $listing): array
    {
        $links = [];
        
        // ★修正: リレーション経由でデータを正しく取得
        // Listingモデルには直接 prefecture や maker カラムはないため、関連モデルから取得します
        $pref = $listing->shop->prefecture ?? null;
        $maker = $listing->bikeModel->manufacturer->name ?? null;
        
        // カテゴリ名はリレーション名 'categoryData' を使用
        $catName = $listing->bikeModel->categoryData->name ?? null;
        
        // カテゴリIDの取得
        $catId = $listing->bikeModel?->category_id;
        
        // メーカーID
        $makerId = $listing->manufacturer_id ?? $listing->bikeModel?->manufacturer_id;

        // 1. エリア × メーカー
        if ($pref && $maker && $makerId) {
            $links[] = [
                'label' => "{$pref}の{$maker}在庫一覧",
                'url' => route('bikes.landing', ['prefecture' => $pref, 'slug' => $maker]),
            ];
        }

        // 2. エリア × カテゴリ
        if ($pref && $catName && $catName !== 'その他') {
            $links[] = [
                'label' => "{$pref}の{$catName}一覧",
                'url' => route('bikes.landing', ['prefecture' => $pref, 'slug' => $catName]),
            ];
        }

        // 3. メーカー × カテゴリ (全国)
        if ($maker && $catName && $makerId && $catId) {
            $links[] = [
                'label' => "{$maker}の{$catName} (全国)",
                'url' => route('bikes.search', ['manufacturer_id' => $makerId, 'category_id' => $catId]),
            ];
        }

        // 4. エリアの全車両
        if ($pref) {
            $links[] = [
                'label' => "{$pref}のバイク一覧",
                'url' => route('bikes.search', ['prefecture' => $pref]),
            ];
        }

        // 5. メーカーの全車両
        if ($maker && $makerId) {
            $links[] = [
                'label' => "{$maker}の中古・新車一覧",
                'url' => route('bikes.search', ['manufacturer_id' => $makerId]),
            ];
        }

        return $links;
    }

    /**
     * 車種の相場分析データを取得（統計情報 + ヒストグラム）
     */
    public function getMarketAnalysis(?int $bikeModelId, ?int $currentPrice): array
    {
        // デフォルト値
        $defaultStats = [
            'avg' => 0, 'min' => 0, 'max' => 0, 'count' => 0,
            'rank' => 'unknown', 'diff' => 0
        ];
        $defaultHistogram = ['labels' => [], 'data' => []];

        if (!$bikeModelId) {
            return ['stats' => $defaultStats, 'histogram' => $defaultHistogram];
        }

        // 1. 価格データの取得
        $prices = $this->listingRepo->findValidPricesByModelId($bikeModelId);

        $count = count($prices);
        if ($count === 0) {
            return ['stats' => $defaultStats, 'histogram' => $defaultHistogram];
        }

        // 2. 統計計算
        $avg = round(array_sum($prices) / $count, 1);
        $min = min($prices);
        $max = max($prices);
        $currentPrice = $currentPrice ?? 0;
        $diff = round($currentPrice - $avg, 1);

        // ランク判定
        $rank = 'unknown';
        if ($currentPrice > 0) {
            if ($currentPrice < $avg * 0.9) {
                $rank = 'S';
            } elseif ($currentPrice < $avg) {
                $rank = 'A';
            } elseif ($currentPrice < $avg * 1.1) {
                $rank = 'B';
            } else {
                $rank = 'C';
            }
        }

        $stats = [
            'avg' => $avg,
            'min' => $min,
            'max' => $max,
            'count' => $count,
            'rank' => $rank,
            'diff' => $diff,
        ];

        // 3. ヒストグラム生成
        $histogram = $this->generateHistogram($prices);

        return [
            'stats' => $stats,
            'histogram' => $histogram
        ];
    }

    /**
     * ヒストグラムデータの生成
     */
    private function generateHistogram(array $prices): array
    {
        if (empty($prices)) return ['labels' => [], 'data' => []];

        $min = floor(min($prices) / 10) * 10;
        $max = ceil(max($prices) / 10) * 10;
        $step = 10;

        // 幅調整
        if ($max - $min < $step * 3) {
            $min = max(0, $min - $step);
            $max = $max + $step;
        }

        $labels = [];
        $data = [];

        for ($i = $min; $i <= $max; $i += $step) {
            $labels[] = $i . '万円台';
            $count = 0;
            foreach ($prices as $p) {
                if ($p >= $i && $p < $i + $step) $count++;
            }
            $data[] = $count;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * 最新のレビューを取得
     */
    public function getLatestReviews(): Collection
    {
        return $this->reviewRepo->getLatest(6); // 6件取得
    }

    /**
     * トップページ用のおすすめ特集データを取得
     */
    public function getFeaturesForTopPage(): array
    {
        return [
            [
                'title' => '✨ 乗り出し30万円以下！お買い得な250cc特集',
                'icon' => 'sparkles',
                'color' => 'bg-gradient-to-br from-yellow-400 to-orange-500',
                'url' => route('bikes.search', ['max_price' => 30, 'min_displacement' => 126, 'max_displacement' => 250]),
            ],
            [
                'title' => '🔥 通勤・通学最強！原付二種（125cc）スクーター',
                'icon' => 'zap',
                'color' => 'bg-gradient-to-br from-blue-400 to-cyan-500',
                'url' => route('bikes.search', ['max_displacement' => 125, 'keyword' => 'スクーター']),
            ],
            [
                'title' => '👑 すぐ乗れる！状態良好なワンオーナー車',
                'icon' => 'crown',
                'color' => 'bg-gradient-to-br from-purple-400 to-pink-500',
                'url' => route('bikes.search', ['tag' => 'ワンオーナー']),
            ],
            [
                'title' => '🛣️ ツーリングに最適！ETC搭載の大型バイク',
                'icon' => 'map',
                'color' => 'bg-gradient-to-br from-green-400 to-emerald-500',
                'url' => route('bikes.search', ['min_displacement' => 401, 'tag' => 'ETC']),
            ],
        ];
    }
}