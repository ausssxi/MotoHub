<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BikeModel;
use App\Models\Shop;
use App\Models\Manufacturer;
use App\Models\Category;
use App\Models\Tag;
use App\Models\SeoFeature;
use App\Models\BikeParking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';
    protected $description = 'サイトマップを分割生成し、インデックスファイルを作成してGoogleに通知します';

    // 1ファイルあたりのURL上限 (Google推奨: 10,000以下)
    private const MAX_URLS_PER_FILE = 10000;

    public function handle(): void
    {
        ini_set('memory_limit', '512M');
        $this->info("サイトマップの分割生成を開始します...");
        $startTime = microtime(true);

        // 古い分割サイトマップファイルを削除（ファイル数が減った時のゴースト参照を防止）
        foreach (glob(public_path('sitemap-landings-*.xml')) as $old) {
            unlink($old);
        }
        foreach (glob(public_path('sitemap-listings-*.xml')) as $old) {
            unlink($old);
        }
        foreach (glob(public_path('sitemap-parking-*.xml')) as $old) {
            unlink($old);
        }
        foreach (glob(public_path('sitemap-parking-area*.xml')) as $old) {
            unlink($old);
        }
        foreach (glob(public_path('sitemap-parts*.xml')) as $old) {
            unlink($old);
        }
        $this->info("古いサイトマップファイルを削除しました。");

        $sitemapFiles = [];

        // 都道府県リストの取得
        $regions = config('bike.regions', []);
        $allPrefectures = collect($regions)->flatten();

        // =========================================================
        // 1. メインサイトマップ (固定ページ + 基本検索)
        // =========================================================
        $mainFileName = 'sitemap-main.xml';
        $this->info("メインサイトマップ ({$mainFileName}) を生成中...");
        
        $handle = $this->openSitemap($mainFileName);
        $count = 0;

        // 固定ページリスト
        $staticPages = [
            // 主要ページ
            ['route' => 'bikes.index',       'priority' => '1.0', 'freq' => 'daily'],
            ['route' => 'bikes.prefectures', 'priority' => '0.9', 'freq' => 'monthly'],
            ['route' => 'bikes.search',      'priority' => '0.9', 'freq' => 'daily'],
            ['route' => 'bikes.models',      'priority' => '0.9', 'freq' => 'weekly'],
            
            // 買取査定LP (SEO重要度・収益性が高いので優先度高めに)
            ['route' => 'sell.index',        'priority' => '0.9', 'freq' => 'weekly'],
            
            // 相場ランキング (データが毎日変わるので daily)
            ['route' => 'bikes.trends',      'priority' => '0.8', 'freq' => 'daily'],

            // 駐車場マップ
            ['route' => 'parking.index',     'priority' => '0.8', 'freq' => 'daily'],

            // パーツ検索
            ['route' => 'parts.index',       'priority' => '0.7', 'freq' => 'weekly'],

            // ツール系
            ['route' => 'bikes.compare',     'priority' => '0.5', 'freq' => 'daily'],
            ['route' => 'wishlist',          'priority' => '0.5', 'freq' => 'monthly'],
            
            // 情報ページ
            ['route' => 'pages.about',       'priority' => '0.3', 'freq' => 'monthly'],
            ['route' => 'pages.contact',     'priority' => '0.3', 'freq' => 'monthly'],
            ['route' => 'pages.privacy-policy', 'priority' => '0.1', 'freq' => 'yearly'],
            ['route' => 'pages.terms',       'priority' => '0.1', 'freq' => 'yearly'],
        ];

        foreach ($staticPages as $page) {
            $this->writeUrl($handle, route($page['route']), date('Y-m-d'), $page['freq'], $page['priority']);
            $count++;
        }

        // 都道府県別の検索結果ページ
        foreach ($allPrefectures as $pref) {
            $this->writeUrl(
                $handle,
                route('bikes.search', ['prefecture' => $pref]),
                date('Y-m-d'),
                'daily',
                '0.8'
            );
            $count++;
        }

        // メーカー別の検索結果ページ
        Manufacturer::chunk(100, function ($makers) use ($handle, &$count) {
            foreach ($makers as $maker) {
                $this->writeUrl(
                    $handle,
                    route('bikes.search', ['manufacturer_id' => $maker->id]),
                    date('Y-m-d'),
                    'daily',
                    '0.8'
                );
                $count++;
            }
        });

        // カテゴリ別の検索結果ページ
        Category::chunk(100, function ($cats) use ($handle, &$count) {
            foreach ($cats as $cat) {
                $this->writeUrl(
                    $handle,
                    route('bikes.search', ['category_id' => $cat->id]),
                    date('Y-m-d'),
                    'daily',
                    '0.8'
                );
                $count++;
            }
        });

        // 車種別の検索結果ページ (keyword検索)
        BikeModel::select('name')->chunk(500, function ($models) use ($handle, &$count) {
            foreach ($models as $model) {
                $this->writeUrl(
                    $handle,
                    route('bikes.search', ['keyword' => $model->name]),
                    date('Y-m-d'),
                    'daily',
                    '0.8'
                );
                $count++;
            }
        });

                // ★追加: タグ別の検索結果ページ
        Tag::select('slug', 'updated_at')->chunk(100, function ($tags) use ($handle, &$count) {
            foreach ($tags as $tag) {
                // タグ一覧は検索需要が高いので、優先度0.8のdailyでクローラーを呼び込みます
                $this->writeUrl(
                    $handle,
                    route('bikes.search', ['tag' => $tag->slug]),
                    $tag->updated_at ? $tag->updated_at->format('Y-m-d') : date('Y-m-d'),
                    'daily',
                    '0.8'
                );
                $count++;
            }
        });

        // SEO特集ページ: 一覧ページ
        $this->writeUrl($handle, route('features.index'), date('Y-m-d'), 'daily', '0.8');
        $count++;

        // SEO特集ページ: 各詳細ページ
        SeoFeature::active()->select('slug', 'updated_at')->chunk(100, function ($features) use ($handle, &$count) {
            foreach ($features as $feature) {
                $this->writeUrl(
                    $handle,
                    route('features.show', $feature->slug),
                    $feature->updated_at->format('Y-m-d'),
                    'weekly',
                    '0.8'
                );
                $count++;
            }
        });

        $this->closeSitemap($handle);
        $sitemapFiles[] = $mainFileName;
        $this->info(" -> {$count} URL (Main)");


        // =========================================================
        // 2. SEOランディングページ (分割生成)
        // =========================================================
        $this->info("SEOランディングサイトマップを生成中...");
        
        $landingFileIndex = 1;
        $landingUrlCount = 0;
        $totalLandingCount = 0;
        
        $currentLandingFileName = "sitemap-landings-{$landingFileIndex}.xml";
        $handle = $this->openSitemap($currentLandingFileName);
        $sitemapFiles[] = $currentLandingFileName;

        // 書き込みとファイルローテーションを行う共通関数
        $writeLandingUrl = function($loc, $lastmod, $freq, $priority) use (&$handle, &$landingUrlCount, &$landingFileIndex, &$sitemapFiles, &$totalLandingCount) {
            // 上限に達したらファイルを分割
            if ($landingUrlCount >= self::MAX_URLS_PER_FILE) {
                $this->closeSitemap($handle);
                $this->info("  -> 分割: sitemap-landings-{$landingFileIndex}.xml 完了");

                $landingFileIndex++;
                $landingUrlCount = 0;
                
                $nextFileName = "sitemap-landings-{$landingFileIndex}.xml";
                $handle = $this->openSitemap($nextFileName);
                $sitemapFiles[] = $nextFileName;
            }

            $this->writeUrl($handle, $loc, $lastmod, $freq, $priority);
            $landingUrlCount++;
            $totalLandingCount++;
        };

        $manufacturers = Manufacturer::all();
        $categories = Category::all();
        // 排気量キーワードリスト
        $displacements = ['原付', 'スクーター', '小型', '中型', '大型', 'リッター'];

        // config都道府県(短縮形) → DB shops.prefecture(正式名) への変換
        $toFullPref = fn(string $pref): string => match($pref) {
            '北海道' => '北海道',
            '東京' => '東京都',
            '大阪' => '大阪府',
            '京都' => '京都府',
            default => $pref . '県',
        };

        // ★ Listingが存在する組み合わせのみサイトマップに含める（クロールバジェット最適化）
        // 都道府県×メーカーの有効な組み合わせを事前取得
        $activeManufPrefSet = DB::table('listings')
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->where('listings.is_sold_out', false)
            ->whereNotNull('listings.bike_model_id')
            ->select(DB::raw('DISTINCT CONCAT(shops.prefecture, "-", bike_models.manufacturer_id) as combo'))
            ->pluck('combo')
            ->flip(); // flip で O(1) ルックアップ

        // 都道府県×車種(bike_model_id)の有効な組み合わせを事前取得
        $activeModelPrefSet = DB::table('listings')
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->where('listings.is_sold_out', false)
            ->whereNotNull('listings.bike_model_id')
            ->select(DB::raw('DISTINCT CONCAT(shops.prefecture, "-", listings.bike_model_id) as combo'))
            ->pluck('combo')
            ->flip();

        $this->info("  有効な都道府県×メーカー: {$activeManufPrefSet->count()} / 都道府県×車種: {$activeModelPrefSet->count()}");

        // 1. メーカー・カテゴリ・排気量の組み合わせ（Listingがある場合のみ）
        foreach ($allPrefectures as $pref) {
            $fullPref = $toFullPref($pref);

            foreach ($manufacturers as $maker) {
                if (!$activeManufPrefSet->has("{$fullPref}-{$maker->id}")) {
                    continue;
                }
                $writeLandingUrl(
                    route('bikes.landing', ['prefecture' => $pref, 'slug' => $maker->name]),
                    date('Y-m-d'),
                    'weekly',
                    '0.7'
                );
            }

            // カテゴリと排気量は在庫数に関係なく含める（種類が少ない & 需要がある）
            foreach ($categories as $cat) {
                $writeLandingUrl(
                    route('bikes.landing', ['prefecture' => $pref, 'slug' => $cat->name]),
                    date('Y-m-d'),
                    'weekly',
                    '0.7'
                );
            }

            foreach ($displacements as $disp) {
                $writeLandingUrl(
                    route('bikes.landing', ['prefecture' => $pref, 'slug' => $disp]),
                    date('Y-m-d'),
                    'weekly',
                    '0.7'
                );
            }
        }

        // 2. 車種名(モデル)との掛け合わせ（Listingがある場合のみ）
        BikeModel::select('id', 'name', 'updated_at')->chunk(500, function ($models) use ($allPrefectures, $writeLandingUrl, $activeModelPrefSet, $toFullPref) {
            foreach ($models as $model) {
                foreach ($allPrefectures as $pref) {
                    if (!$activeModelPrefSet->has("{$toFullPref($pref)}-{$model->id}")) {
                        continue;
                    }
                    $writeLandingUrl(
                        route('bikes.landing', ['prefecture' => $pref, 'slug' => $model->name]),
                        $model->updated_at->format('Y-m-d'),
                        'weekly',
                        '0.7'
                    );
                }
            }
        });

        $this->closeSitemap($handle);
        $this->info(" -> {$totalLandingCount} URL (Landings Total)");


        // =========================================================
        // 3. カタログページ (sitemap-catalog.xml)
        // =========================================================
        $this->info("カタログページサイトマップを生成中...");
        $catalogFileName = 'sitemap-catalog.xml';
        $handle = $this->openSitemap($catalogFileName);
        $sitemapFiles[] = $catalogFileName;
        $catalogCount = 0;

        // メーカー単体のカタログページ
        $catalogManufacturers = Manufacturer::whereNotNull('slug')->get();
        foreach ($catalogManufacturers as $maker) {
            $this->writeUrl(
                $handle,
                route('bikes.catalog', $maker->slug),
                date('Y-m-d'),
                'weekly',
                '0.8'
            );
            $catalogCount++;
        }

        // メーカー×カテゴリの組み合わせカタログページ
        $catalogCategories = Category::whereNotNull('slug')->get();
        foreach ($catalogManufacturers as $maker) {
            foreach ($catalogCategories as $cat) {
                $this->writeUrl(
                    $handle,
                    route('bikes.catalog', "{$maker->slug}-{$cat->slug}"),
                    date('Y-m-d'),
                    'weekly',
                    '0.7'
                );
                $catalogCount++;
            }
        }

        // 排気量帯カタログページ
        foreach (['50cc', '125cc', '250cc', '400cc', '750cc'] as $cc) {
            $this->writeUrl(
                $handle,
                route('bikes.catalog', $cc),
                date('Y-m-d'),
                'weekly',
                '0.8'
            );
            $catalogCount++;
        }

        $this->closeSitemap($handle);
        $this->info(" -> {$catalogCount} URL (Catalog)");


        // =========================================================
        // 4. 店舗詳細サイトマップ (sitemap-shops.xml)
        // =========================================================
        $this->info("店舗詳細サイトマップを生成中...");
        $shopFileName = 'sitemap-shops.xml';
        $handle = $this->openSitemap($shopFileName);
        $sitemapFiles[] = $shopFileName;
        $shopCount = 0;

        Shop::select('id', 'updated_at')
            ->orderBy('updated_at', 'desc')
            ->chunk(1000, function ($shops) use ($handle, &$shopCount) {
                foreach ($shops as $shop) {
                    $this->writeUrl(
                        $handle,
                        route('shops.show', $shop->id),
                        $shop->updated_at->format('Y-m-d'),
                        'weekly',
                        '0.7'
                    );
                    $shopCount++;
                }
            });

        // チェーン別まとめページ
        $chains = config('bike.chains', []);
        $chainCount = 0;
        foreach ($chains as $slug => $chain) {
            $this->writeUrl(
                $handle,
                route('shops.chain', $slug),
                date('Y-m-d'),
                'weekly',
                '0.7'
            );
            $chainCount++;
        }

        $this->closeSitemap($handle);
        $this->info(" -> {$shopCount} URL (Shops)");
        $this->info(" -> {$chainCount} URL (Chain Shops)");


        // =========================================================
        // 4.5. 駐車場サイトマップ (10,000件ごとにファイルを分割)
        // =========================================================
        $this->info("駐車場サイトマップを生成中...");

        $parkingFileIndex = 1;
        $parkingUrlCount = 0;
        $totalParkingCount = 0;

        $currentParkingFileName = "sitemap-parking-{$parkingFileIndex}.xml";
        $handle = $this->openSitemap($currentParkingFileName);
        $sitemapFiles[] = $currentParkingFileName;

        // 駐車場マップトップ
        $this->writeUrl($handle, route('parking.index'), date('Y-m-d'), 'daily', '0.8');
        $parkingUrlCount++;
        $totalParkingCount++;

        // 各駐車場詳細ページ
        BikeParking::active()->select('id', 'updated_at')
            ->orderBy('updated_at', 'desc')
            ->chunk(1000, function ($parkings) use (&$handle, &$parkingUrlCount, &$parkingFileIndex, &$sitemapFiles, &$totalParkingCount) {
                foreach ($parkings as $parking) {
                    if ($parkingUrlCount >= self::MAX_URLS_PER_FILE) {
                        $this->closeSitemap($handle);
                        $this->info("  -> 分割: sitemap-parking-{$parkingFileIndex}.xml 完了");

                        $parkingFileIndex++;
                        $parkingUrlCount = 0;

                        $nextFileName = "sitemap-parking-{$parkingFileIndex}.xml";
                        $handle = $this->openSitemap($nextFileName);
                        $sitemapFiles[] = $nextFileName;
                    }

                    $this->writeUrl(
                        $handle,
                        route('parking.show', $parking->id),
                        $parking->updated_at->format('Y-m-d'),
                        'weekly',
                        '0.6'
                    );
                    $parkingUrlCount++;
                    $totalParkingCount++;
                }
            });

        $this->closeSitemap($handle);
        $this->info(" -> {$totalParkingCount} URL (Parking Total, {$parkingFileIndex} files)");


        // =========================================================
        // 4.6. 駐車場エリアページ (sitemap-parking-area.xml)
        // =========================================================
        $this->info("駐車場エリアサイトマップを生成中...");
        $parkingAreaFileName = 'sitemap-parking-area.xml';
        $handle = $this->openSitemap($parkingAreaFileName);
        $sitemapFiles[] = $parkingAreaFileName;
        $parkingAreaCount = 0;

        // エリアインデックス
        $this->writeUrl($handle, route('parking.area.index'), date('Y-m-d'), 'weekly', '0.8');
        $parkingAreaCount++;

        // 都道府県ページ + 市区町村ページ
        $parkingAreaService = app(\App\Services\Parking\ParkingAreaService::class);
        $allParkingPrefs = $parkingAreaService->getAllPrefectures();

        foreach ($allParkingPrefs as $pref) {
            $this->writeUrl(
                $handle,
                route('parking.area.prefecture', $pref),
                date('Y-m-d'),
                'weekly',
                '0.7'
            );
            $parkingAreaCount++;

            // 市区町村ページ
            $cities = $parkingAreaService->getCitiesForPrefecture($pref);
            foreach ($cities as $city) {
                $this->writeUrl(
                    $handle,
                    route('parking.area.city', [$pref, $city]),
                    date('Y-m-d'),
                    'weekly',
                    '0.6'
                );
                $parkingAreaCount++;
            }
        }

        $this->closeSitemap($handle);
        $this->info(" -> {$parkingAreaCount} URL (Parking Area)");


        // =========================================================
        // 4.7. 駅別駐車場ページ (sitemap-parking-station.xml)
        // =========================================================
        $this->info("駅別駐車場サイトマップを生成中...");
        $stationFileName = 'sitemap-parking-station.xml';
        $handle = $this->openSitemap($stationFileName);
        $sitemapFiles[] = $stationFileName;
        $stationCount = 0;

        // 駅一覧インデックス
        $this->writeUrl($handle, route('parking.station.index'), date('Y-m-d'), 'weekly', '0.7');
        $stationCount++;

        // 主要駅 + 駐車場5件以上の駅
        $stationService = app(\App\Services\Parking\StationParkingService::class);
        $sitemapStations = $stationService->getSitemapStations(5);

        foreach ($sitemapStations as $station) {
            $lastmod = $station->updated_at?->format('Y-m-d') ?? date('Y-m-d');
            $priority = $station->is_major ? '0.6' : '0.5';

            $this->writeUrl(
                $handle,
                route('parking.station.show', $station->slug),
                $lastmod,
                'weekly',
                $priority
            );
            $stationCount++;
        }

        $this->closeSitemap($handle);
        $this->info(" -> {$stationCount} URL (Parking Station)");


        // =========================================================
        // 5. 車種別カタログページ (sitemap-models.xml)
        // =========================================================
        $this->info("車種別カタログサイトマップを生成中...");
        $modelFileName = 'sitemap-models.xml';
        $handle = $this->openSitemap($modelFileName);
        $sitemapFiles[] = $modelFileName;
        $modelCount = 0;

        BikeModel::with('manufacturer')->select('id', 'slug', 'manufacturer_id', 'updated_at')
            ->orderBy('updated_at', 'desc')
            ->chunk(1000, function ($models) use ($handle, &$modelCount) {
                foreach ($models as $model) {
                    $this->writeUrl(
                        $handle,
                        url($model->seo_url),
                        $model->updated_at->format('Y-m-d'),
                        'weekly',
                        '0.8'
                    );
                    $modelCount++;
                }
            });

        $this->closeSitemap($handle);
        $this->info(" -> {$modelCount} URL (Model Catalogs)");


        // =========================================================
        // 6. パーツカテゴリページ (sitemap-parts.xml)
        // =========================================================
        $this->info("パーツカテゴリサイトマップを生成中...");
        $partsFileName = 'sitemap-parts.xml';
        $handle = $this->openSitemap($partsFileName);
        $sitemapFiles[] = $partsFileName;
        $partsCount = 0;

        foreach (config('parts-categories', []) as $cat) {
            $this->writeUrl(
                $handle,
                route('parts.category', $cat['slug']),
                date('Y-m-d'),
                'weekly',
                '0.7'
            );
            $partsCount++;
        }

        $this->closeSitemap($handle);
        $this->info(" -> {$partsCount} URL (Parts Category)");


        // =========================================================
        // 6.7. ランキングサイトマップ (sitemap-rankings.xml)
        // =========================================================
        $this->info("ランキングサイトマップを生成中...");
        $rankingFileName = 'sitemap-rankings.xml';
        $handle = $this->openSitemap($rankingFileName);
        $sitemapFiles[] = $rankingFileName;
        $rankingCount = 0;

        $this->writeUrl($handle, route('ranking.index'), date('Y-m-d'), 'daily', '0.8');
        $rankingCount++;

        $this->writeUrl($handle, route('ranking.weekly'), date('Y-m-d'), 'weekly', '0.7');
        $rankingCount++;

        for ($i = 0; $i < 6; $i++) {
            $rankDate = now()->subMonths($i);
            $this->writeUrl($handle, route('ranking.monthly', $rankDate->format('Y-m')), $rankDate->toDateString(), 'monthly', '0.6');
            $rankingCount++;
        }

        // 車種別ランキングページ（在庫がある全bike_model_id）
        $rankingModelIds = DB::table('listings')
            ->where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->distinct()
            ->pluck('bike_model_id');

        foreach ($rankingModelIds as $modelId) {
            $this->writeUrl($handle, route('ranking.model_stats', $modelId), date('Y-m-d'), 'weekly', '0.5');
            $rankingCount++;
        }

        $this->closeSitemap($handle);
        $this->info(" -> {$rankingCount} URL (Rankings)");


        // =========================================================
        // 7. サイトマップインデックス (目次) の生成
        // =========================================================
        $this->info("インデックスファイル (sitemap.xml) を生成中...");
        $this->generateIndexFile($sitemapFiles);

        $duration = round(microtime(true) - $startTime, 2);
        $this->info("全ての処理が完了しました！ ({$duration}秒)");
    }

    private function pingGoogle(): void
    {
        // GoogleのPing送信機能は廃止されたため、メソッド内は空にしておくか、削除してもOKです
    }

    private function openSitemap(string $filename)
    {
        $path = public_path($filename);
        $handle = fopen($path, 'w');
        fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);
        fwrite($handle, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL);
        return $handle;
    }

    private function writeUrl($handle, $loc, $lastmod, $freq, $priority)
    {
        $loc = htmlspecialchars($loc, ENT_XML1, 'UTF-8');
        $xml = "    <url>\n";
        $xml .= "        <loc>{$loc}</loc>\n";
        $xml .= "        <lastmod>{$lastmod}</lastmod>\n";
        $xml .= "        <changefreq>{$freq}</changefreq>\n";
        $xml .= "        <priority>{$priority}</priority>\n";
        $xml .= "    </url>\n";
        fwrite($handle, $xml);
    }

    private function closeSitemap($handle)
    {
        fwrite($handle, '</urlset>');
        fclose($handle);
    }

    private function generateIndexFile(array $files)
    {
        $indexPath = public_path('sitemap.xml');
        $handle = fopen($indexPath, 'w');

        fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);
        fwrite($handle, '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL);

        foreach ($files as $file) {
            $lastmod = date('Y-m-d\TH:i:sP', filemtime(public_path($file)));
            $loc = url($file);
            $xml = "    <sitemap>\n";
            $xml .= "        <loc>{$loc}</loc>\n";
            $xml .= "        <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    </sitemap>\n";
            fwrite($handle, $xml);
        }

        fwrite($handle, '</sitemapindex>');
        fclose($handle);
    }
}