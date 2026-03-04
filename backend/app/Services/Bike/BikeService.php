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
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;

/**
 * 車種マスタ・メーカー情報のビジネスロジック
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

    public function incrementViewCount(int $id): void
    {
        // クローラー（Bot）からのアクセスはカウントしない
        $userAgent = request()->userAgent() ?? '';
        $isBot = preg_match('/bot|crawler|spider|slurp|facebook|twitter|line|insomnia|curl|wget/i', strtolower($userAgent));
        
        if ($isBot) {
            return;
        }

        $viewed = Session::get('viewed_listings', []);

        if (!in_array($id, $viewed)) {
            $this->listingRepo->incrementViewCount($id);

            array_unshift($viewed, $id);
            if (count($viewed) > 50) {
                $viewed = array_slice($viewed, 0, 50);
            }
            
            Session::put('viewed_listings', $viewed);
        }
    }

    public function getRegions(): array
    {
        return config('bike.regions', []);
    }

    public function getLicenses(): array
    {
        return config('bike.licenses', []);
    }

    public function getMajorManufacturers(): Collection
    {
        $allMakers = $this->manufacturerRepo->getAllSortedByName();
        $targetNames = ['ホンダ', 'ヤマハ', 'スズキ', 'カワサキ', 'ハーレーダビッドソン'];
        $results = new Collection();

        foreach ($targetNames as $name) {
            $found = $allMakers->first(function ($m) use ($name) {
                return str_contains($m->name, $name) || str_contains(strtolower($m->name), strtolower($name));
            });
            if ($found) $results->push($found);
        }
        return $results;
    }

    public function createReview(int $bikeModelId, array $data): Review
    {
        return $this->reviewRepo->create([
            'bike_model_id' => $bikeModelId,
            'nickname'      => $data['nickname'] ?: '名無しライダー',
            'rating'        => $data['rating'],
            'title'         => $data['title'],
            'body'          => $data['body'],
            'is_approved'   => true, 
        ]);
    }

    public function getBikeModelDetail(int $id): BikeModel
    {
        return $this->modelRepo->findDetailOrFail($id);
    }

    public function getAllManufacturers(): Collection
    {
        return $this->manufacturerRepo->getAllSortedByName();
    }

    public function getAllManufacturersById(): Collection
    {
        return $this->manufacturerRepo->getAllSortedById();
    }

    public function getCategoriesForTopPage(): Collection
    {
        return $this->categoryRepo->getWithImagesSorted();
    }

    public function getPopularBikesForTopPage(): Collection
    {
        return $this->modelRepo->getTopModels(16);
    }

    /**
     * 車種一覧ページ用：急上昇トレンド（熱量スコア順）
     */
    public function getTrendingBikes(int $limit = 10): Collection
    {
        // 1時間（3600秒）ごとに結果をキャッシュして爆速化する
        return Cache::remember('trending_bikes_v1', 3600, function () use ($limit) {
            return $this->modelRepo->getTrendingModels($limit);
        });
    }

    public function getSearchSuggestions(string $keyword): array
    {
        $models = $this->modelRepo->searchByName($keyword, 10);
        return $models->map(fn($m) => [
            'id'    => $m->id,
            'name'  => $m->name,
            'count' => $m->listings_count,
        ])->toArray();
    }

    public function getAllModelsForIndex(): array
    {
        return Cache::remember('all_models_for_index', 86400, function () {
            $manufacturersRaw = $this->manufacturerRepo->getAll();
            $totalModelsCount = 0;

            $formattedManufacturers = $manufacturersRaw->map(function ($maker) use (&$totalModelsCount) {
                $models = $this->modelRepo->getByManufacturerId((int)$maker->id);
                $count = $models->count();
                $totalModelsCount += $count;

                if ($count === 0) return null;

                $topModel = $models->sortByDesc('listings_count')->first(fn($m) => !empty($m->image_url)) 
                            ?? $models->sortByDesc('listings_count')->first();
                $makerImage = $topModel?->image_url;

                $groups = $this->groupModelsByName($models);

                return [
                    'id' => $maker->id,
                    'name' => $maker->name,
                    'bike_models_count' => $count,
                    'image_url' => $makerImage,
                    'groups' => $groups,
                ];
            })->filter();

            return [
                'manufacturers' => $formattedManufacturers,
                'totalModelsCount' => $totalModelsCount
            ];
        });
    }

    private function groupModelsByName(Collection $models): array
    {
        $groups = [];
        foreach (range('A', 'Z') as $char) $groups[$char] = [];
        foreach (['あ行', 'か行', 'さ行', 'た行', 'な行', 'は行', 'ま行', 'や行', 'ら行', 'わ行'] as $row) $groups[$row] = [];
        $groups['0-9'] = [];
        $groups['その他'] = [];

        foreach ($models as $bike) {
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

    public function getRelatedListings(int $bikeModelId, int $excludeId, int $limit = 8): Collection
    {
        return $this->listingRepo->getRelatedListings($bikeModelId, $excludeId, $limit);
    }

    public function getSimilarListings(?int $manufacturerId, ?int $excludeModelId, int $limit = 8): Collection
    {
        return $this->listingRepo->getSimilarListings($manufacturerId, $excludeModelId, $limit);
    }

    public function getListingDetail(int $id): Listing
    {
        return $this->listingRepo->getListingDetail($id);
    }

    public function getSeoLinks(Listing $listing): array
    {
        $links = [];
        $pref = $listing->shop->prefecture ?? null;
        $maker = $listing->bikeModel->manufacturer->name ?? null;
        $catName = $listing->bikeModel->categoryData->name ?? null;
        $catId = $listing->bikeModel?->category_id;
        $makerId = $listing->manufacturer_id ?? $listing->bikeModel?->manufacturer_id;

        // ★こちらも相対パスに変更
        if ($pref && $maker && $makerId) {
            $links[] = ['label' => "{$pref}の{$maker}在庫一覧", 'url' => route('bikes.landing', ['prefecture' => $pref, 'slug' => $maker], false)];
        }
        if ($pref && $catName && $catName !== 'その他') {
            $links[] = ['label' => "{$pref}の{$catName}一覧", 'url' => route('bikes.landing', ['prefecture' => $pref, 'slug' => $catName], false)];
        }
        if ($maker && $catName && $makerId && $catId) {
            $links[] = ['label' => "{$maker}の{$catName} (全国)", 'url' => route('bikes.search', ['manufacturer_id' => $makerId, 'category_id' => $catId], false)];
        }
        if ($pref) {
            $links[] = ['label' => "{$pref}のバイク一覧", 'url' => route('bikes.search', ['prefecture' => $pref], false)];
        }
        if ($maker && $makerId) {
            $links[] = ['label' => "{$maker}の中古・新車一覧", 'url' => route('bikes.search', ['manufacturer_id' => $makerId], false)];
        }

        return $links;
    }

    public function getMarketAnalysis(?int $bikeModelId, ?int $currentPrice): array
    {
        $defaultStats = ['avg' => 0, 'min' => 0, 'max' => 0, 'count' => 0, 'rank' => 'unknown', 'diff' => 0];
        $defaultHistogram = ['labels' => [], 'data' => []];

        if (!$bikeModelId) return ['stats' => $defaultStats, 'histogram' => $defaultHistogram];

        $prices = $this->listingRepo->findValidPricesByModelId($bikeModelId);
        $count = count($prices);
        if ($count === 0) return ['stats' => $defaultStats, 'histogram' => $defaultHistogram];

        $avg = round(array_sum($prices) / $count, 1);
        $min = min($prices);
        $max = max($prices);
        $currentPrice = $currentPrice ?? 0;
        $diff = round($currentPrice - $avg, 1);

        $rank = 'unknown';
        if ($currentPrice > 0) {
            if ($currentPrice < $avg * 0.9) $rank = 'S';
            elseif ($currentPrice < $avg) $rank = 'A';
            elseif ($currentPrice < $avg * 1.1) $rank = 'B';
            else $rank = 'C';
        }

        return [
            'stats' => ['avg' => $avg, 'min' => $min, 'max' => $max, 'count' => $count, 'rank' => $rank, 'diff' => $diff],
            'histogram' => $this->generateHistogram($prices)
        ];
    }

    private function generateHistogram(array $prices): array
    {
        if (empty($prices)) return ['labels' => [], 'data' => []];
        $min = floor(min($prices) / 10) * 10;
        $max = ceil(max($prices) / 10) * 10;
        $step = 10;
        if ($max - $min < $step * 3) {
            $min = max(0, $min - $step);
            $max = $max + $step;
        }

        $labels = []; $data = [];
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

    public function getLatestReviews(): Collection
    {
        return $this->reviewRepo->getLatest(6); 
    }

    public function getReviewsByModelId(int $modelId, int $limit = 3): Collection
    {
        return $this->reviewRepo->getLatestByModelId($modelId, $limit);
    }

    public function getFeaturesForTopPage(): array
    {
        $now = now();
        $hour = $now->hour;
        $isWeekend = $now->isWeekend() || ($now->isFriday() && $hour >= 18);
        
        $cacheKey = "top_features_h{$hour}_w" . ($isWeekend ? '1' : '0');

        return Cache::remember($cacheKey, 3600, function () use ($hour, $isWeekend) {
            $featurePool = [
                [
                    'id' => 'commute_scooter', 'title' => '🔥 通勤・通学を快適に！原付二種スクーター', 'icon' => 'zap',
                    'color' => 'bg-gradient-to-br from-blue-400 to-cyan-500',
                    'url' => route('bikes.search', ['max_displacement' => 125, 'keyword' => 'スクーター'], false), // ★修正: 相対パス
                    'base_score' => 50, 'boost_timing' => ['morning', 'weekday'],
                ],
                [
                    'id' => 'commute_cub', 'title' => '🛵 燃費最強！お財布に優しいカブ系特集', 'icon' => 'leaf',
                    'color' => 'bg-gradient-to-br from-green-500 to-emerald-600',
                    'url' => route('bikes.search', ['keyword' => 'カブ'], false), // ★修正: 相対パス
                    'base_score' => 45, 'boost_timing' => ['morning', 'daytime', 'weekday'],
                ],
                [
                    'id' => 'cost_under30', 'title' => '💰 初めての相棒に！支払総額30万円以下', 'icon' => 'coins',
                    'color' => 'bg-gradient-to-br from-amber-400 to-yellow-600',
                    'url' => route('bikes.search', ['max_price' => 30], false), // ★修正: 相対パス
                    'base_score' => 50, 'boost_timing' => ['daytime', 'weekday'],
                ],
                [
                    'id' => 'touring_etc', 'title' => '🛣️ 週末はどこへ行く？ETC搭載の大型バイク', 'icon' => 'map',
                    'color' => 'bg-gradient-to-br from-indigo-500 to-blue-700',
                    'url' => route('bikes.search', ['min_displacement' => 401, 'tag' => 'ETC'], false), // ★修正: 相対パス
                    'base_score' => 45, 'boost_timing' => ['evening', 'night', 'weekend'],
                ],
                [
                    'id' => 'adventure', 'title' => '🏕️ 荷物を積んでキャンプへ！アドベンチャー', 'icon' => 'mountain',
                    'color' => 'bg-gradient-to-br from-stone-500 to-stone-700',
                    'url' => route('bikes.search', ['keyword' => 'アドベンチャー'], false), // ★修正: 相対パス
                    'base_score' => 40, 'boost_timing' => ['evening', 'weekend'],
                ],
                [
                    'id' => 'supersport', 'title' => '🏁 圧倒的な所有感。スーパースポーツ大集合', 'icon' => 'flag',
                    'color' => 'bg-gradient-to-br from-red-600 to-rose-800',
                    'url' => route('bikes.search', ['keyword' => 'スーパースポーツ'], false), // ★修正: 相対パス
                    'base_score' => 40, 'boost_timing' => ['night', 'weekend'],
                ],
                [
                    'id' => 'classic', 'title' => '☕ 夜の街に映える。ネオクラシック特集', 'icon' => 'coffee',
                    'color' => 'bg-gradient-to-br from-orange-800 to-amber-900',
                    'url' => route('bikes.search', ['keyword' => 'クラシック'], false), // ★修正: 相対パス
                    'base_score' => 40, 'boost_timing' => ['night', 'midnight'],
                ],
                [
                    'id' => 'vintage', 'title' => '🕰️ 時代を超える名車。絶版・旧車特集', 'icon' => 'history',
                    'color' => 'bg-gradient-to-br from-gray-700 to-black',
                    'url' => route('bikes.search', ['max_year' => 2000], false), // ★修正: 相対パス
                    'base_score' => 35, 'boost_timing' => ['midnight'],
                ],
                [
                    'id' => 'one_owner', 'title' => '👑 大切に乗られた証。ワンオーナー車特集', 'icon' => 'crown',
                    'color' => 'bg-gradient-to-br from-purple-400 to-pink-500',
                    'url' => route('bikes.search', ['tag' => 'ワンオーナー'], false), // ★修正: 相対パス
                    'base_score' => 55, 'boost_timing' => ['daytime', 'evening'],
                ],
                [
                    'id' => 'low_mileage', 'title' => '💎 まるで新車！走行距離5,000km以下の極上車', 'icon' => 'gem',
                    'color' => 'bg-gradient-to-br from-rose-400 to-red-500',
                    'url' => route('bikes.search', ['max_mileage' => 5000, 'tag' => '美車'], false), // ★修正: 相対パス
                    'base_score' => 55, 'boost_timing' => ['daytime', 'evening'],
                ],
                [
                    'id' => 'middle_class', 'title' => '✨ 車検不要で維持費がお得！250ccモデル', 'icon' => 'sparkles',
                    'color' => 'bg-gradient-to-br from-yellow-400 to-orange-500',
                    'url' => route('bikes.search', ['min_displacement' => 126, 'max_displacement' => 250], false), // ★修正: 相対パス
                    'base_score' => 60, 'boost_timing' => ['all'],
                ],
                [
                    'id' => 'bargain', 'title' => '📉 掘り出し物！AIが選ぶお買い得車両', 'icon' => 'trending-down',
                    'color' => 'bg-gradient-to-br from-teal-400 to-green-600',
                    'url' => route('bikes.search', ['sort' => 'bargain_desc'], false), // ★修正: 相対パス
                    'base_score' => 55, 'boost_timing' => ['all'],
                ],
            ];

            $currentTimings = ['all'];
            $currentTimings[] = $isWeekend ? 'weekend' : 'weekday';

            if ($hour >= 5 && $hour < 10) $currentTimings[] = 'morning';
            elseif ($hour >= 10 && $hour < 17) $currentTimings[] = 'daytime';
            elseif ($hour >= 17 && $hour < 22) $currentTimings[] = 'evening';
            else {
                $currentTimings[] = 'night';
                if ($hour >= 0 && $hour < 5) $currentTimings[] = 'midnight';
            }

            return collect($featurePool)->map(function ($feature) use ($currentTimings) {
                $score = $feature['base_score'];
                $matchingTimings = array_intersect($currentTimings, $feature['boost_timing']);
                if (count($matchingTimings) > 0) $score += (count($matchingTimings) * 15);
                $score += rand(0, 25);
                $feature['final_score'] = $score;
                return $feature;
            })
            ->sortByDesc('final_score')
            ->take(3)
            ->values()
            ->map(function ($f) {
                return [
                    'title' => $f['title'], 'icon' => $f['icon'],
                    'color' => $f['color'], 'url' => $f['url'],
                ];
            })
            ->toArray();
        });
    }
    
    public function generateDynamicLinks($listing, array $baseSeoLinks, $tags): array
    {
        $dynamicLinks = [];
        
        foreach ($baseSeoLinks as $link) {
            $dynamicLinks[] = [
                'icon'  => 'chevron-right',
                'label' => $link['label'] ?? '関連車両',
                'url'   => $link['url'] ?? '#'
            ];
        }

        $maker = $listing->maker ?? '';
        $pref = $listing->prefecture ?? '';
        $cat = $listing->category ?? '';
        
        // ★こちらも相対パスに変更
        if ($maker && $pref) {
            $dynamicLinks[] = ['icon' => 'map-pin', 'label' => "{$pref} × {$maker} のバイク", 'url' => route('bikes.search', ['prefecture' => $pref, 'keyword' => $maker], false)];
        }
        if ($cat && $pref) {
            $dynamicLinks[] = ['icon' => 'map-pin', 'label' => "{$pref} × {$cat}", 'url' => route('bikes.search', ['prefecture' => $pref, 'keyword' => $cat], false)];
        }
        
        if ($tags && is_iterable($tags)) {
            $count = 0;
            foreach ($tags as $tag) {
                if ($count >= 6) break;
                
                $tagName = $tag->name ?? '';
                $tagSlug = $tag->slug ?? '';
                if (!$tagName) continue;

                $dynamicLinks[] = ['icon' => 'hash', 'label' => "{$tagName} のバイク", 'url' => route('bikes.search', ['tag' => $tagSlug], false)];

                if ($cat) $dynamicLinks[] = ['icon' => 'hash', 'label' => "{$tagName} × {$cat}", 'url' => route('bikes.search', ['tag' => $tagSlug, 'keyword' => $cat], false)];
                if ($maker) $dynamicLinks[] = ['icon' => 'hash', 'label' => "{$tagName} × {$maker}", 'url' => route('bikes.search', ['tag' => $tagSlug, 'keyword' => $maker], false)];
                $count++;
            }
        }
        
        return collect($dynamicLinks)->unique('url')->values()->toArray();
    }
}