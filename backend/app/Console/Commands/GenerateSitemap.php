<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\BikeParking;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Manufacturer;
use App\Models\SeoCompare;
use App\Models\SeoFeature;
use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'サイトマップを分割生成し、インデックスファイルを作成してGoogleに通知します';

    // 1ファイルあたりのURL上限 (Google推奨: 10,000以下)
    private const MAX_URLS_PER_FILE = 10000;

    // Googleニュース sitemap のファイル名（news:news 付き・直近2日のオリジナルのみ）
    public const GOOGLE_NEWS_SITEMAP = 'sitemap-news.xml';

    /**
     * Googleニュース sitemap の対象記事: オリジナル(source='MotoHub')かつ直近2日(48h)以内。
     * Google News sitemap の仕様（2日より古い記事は含めない）に準拠。
     *
     * @return \Illuminate\Support\Collection<int,\App\Models\BikeNews>
     */
    public static function googleNewsArticles(): \Illuminate\Support\Collection
    {
        return \App\Models\BikeNews::original()
            ->where('published_at', '>=', now()->subDays(2))
            ->orderByDesc('published_at')
            ->get(['id', 'title', 'published_at']);
    }

    /** Googleニュース sitemap の 1URL 分（<news:news> 付き）を返す。 */
    public static function renderNewsSitemapEntry(\App\Models\BikeNews $news): string
    {
        $loc = htmlspecialchars(route('news.show', $news->id), ENT_XML1, 'UTF-8');
        $title = htmlspecialchars((string) $news->title, ENT_XML1, 'UTF-8');
        $pubDate = $news->published_at->toAtomString();

        return '    <url>'.PHP_EOL
            ."        <loc>{$loc}</loc>".PHP_EOL
            .'        <news:news>'.PHP_EOL
            .'            <news:publication>'.PHP_EOL
            .'                <news:name>MotoHub</news:name>'.PHP_EOL
            .'                <news:language>ja</news:language>'.PHP_EOL
            .'            </news:publication>'.PHP_EOL
            ."            <news:publication_date>{$pubDate}</news:publication_date>".PHP_EOL
            ."            <news:title>{$title}</news:title>".PHP_EOL
            .'        </news:news>'.PHP_EOL
            .'    </url>'.PHP_EOL;
    }

    // IndexNow 1リクエストあたりの最大URL数
    private const INDEXNOW_BATCH_SIZE = 10000;

    // IndexNow差分検出用スナップショットの保存先
    private const INDEXNOW_SNAPSHOT_FILE = 'indexnow_snapshot.json';

    /** @var array<string, string> url => lastmod */
    private array $collectedUrls = [];

    public function handle(): void
    {
        ini_set('memory_limit', '512M');
        $this->info('サイトマップの分割生成を開始します...');
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
        foreach (glob(public_path('sitemap-touring*.xml')) as $old) {
            unlink($old);
        }
        $this->info('古いサイトマップファイルを削除しました。');

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

            ['route' => 'bikes.models',      'priority' => '0.9', 'freq' => 'weekly'],

            // 買取査定LP (SEO重要度・収益性が高いので優先度高めに)
            ['route' => 'sell.index',        'priority' => '0.9', 'freq' => 'weekly'],

            // 相場ランキング (データが毎日変わるので daily)
            ['route' => 'bikes.trends',      'priority' => '0.8', 'freq' => 'daily'],

            // 駐車場マップ
            ['route' => 'parking.index',     'priority' => '0.8', 'freq' => 'daily'],

            // お買い得バイク
            ['route' => 'bikes.bargains',    'priority' => '0.8', 'freq' => 'daily'],

            // 絶版バイク一覧
            ['route' => 'bikes.discontinued', 'priority' => '0.8', 'freq' => 'weekly'],

            // レビュー一覧ハブ（単一ハブ・低リスク）
            ['route' => 'bikes.reviews_index', 'priority' => '0.7', 'freq' => 'daily'],

            // ショップ口コミ 新着フィード（全店横断・即反映コメントが集まる）
            ['route' => 'shops.reviews',     'priority' => '0.6', 'freq' => 'daily'],

            // 駐車場口コミ 新着フィード（全駐車場横断）
            ['route' => 'parking.reviews',   'priority' => '0.6', 'freq' => 'daily'],

            // パーツ検索
            ['route' => 'parts.index',       'priority' => '0.7', 'freq' => 'weekly'],

            // ツール系
            ['route' => 'pages.widget',      'priority' => '0.7', 'freq' => 'monthly'],
            ['route' => 'data',              'priority' => '0.6', 'freq' => 'monthly'],
            ['route' => 'trouble.index',     'priority' => '0.6', 'freq' => 'monthly'],
            ['route' => 'bikes.compare',     'priority' => '0.5', 'freq' => 'daily'],
            // 車種比較ハブ（個別比較ページの入口・オーファン解消）
            ['route' => 'bikes.model_compare_hub', 'priority' => '0.5', 'freq' => 'weekly'],
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
        $this->info('SEOランディングサイトマップを生成中...');

        $landingFileIndex = 1;
        $landingUrlCount = 0;
        $totalLandingCount = 0;

        $currentLandingFileName = "sitemap-landings-{$landingFileIndex}.xml";
        $handle = $this->openSitemap($currentLandingFileName);
        $sitemapFiles[] = $currentLandingFileName;

        // 書き込みとファイルローテーションを行う共通関数
        $writeLandingUrl = function ($loc, $lastmod, $freq, $priority) use (&$handle, &$landingUrlCount, &$landingFileIndex, &$sitemapFiles, &$totalLandingCount) {
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
        $toFullPref = fn (string $pref): string => match ($pref) {
            '北海道' => '北海道',
            '東京' => '東京都',
            '大阪' => '大阪府',
            '京都' => '京都府',
            default => $pref.'県',
        };

        // ★ 在庫が一定以上の組み合わせのみサイトマップに含める（クロールバジェット最適化）
        //   閾値: メーカー/カテゴリ/排気量×県 = 10台以上、車種×県 = 5台以上（勝ちパターンを優遇）
        // 都道府県×メーカーの有効な組み合わせを事前取得（10台以上）
        $activeManufPrefSet = DB::table('listings')
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->where('listings.is_sold_out', false)
            ->whereNotNull('listings.bike_model_id')
            ->select(DB::raw('CONCAT(shops.prefecture, "-", bike_models.manufacturer_id) as combo'))
            ->groupBy('combo')
            ->havingRaw('COUNT(*) >= 10')
            ->pluck('combo')
            ->flip(); // flip で O(1) ルックアップ

        // 都道府県×車種(bike_model_id)の有効な組み合わせを事前取得（在庫5台以上）
        // ※ 車種×エリアはCTR最高の勝ちパターンのため、メーカー/カテゴリ/排気量(10台)より
        //    閾値を緩めて面を広げる。noindex閾値(landing.blade)も type=model のみ5に揃えてある。
        $activeModelPrefSet = DB::table('listings')
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->where('listings.is_sold_out', false)
            ->whereNotNull('listings.bike_model_id')
            ->select(DB::raw('CONCAT(shops.prefecture, "-", listings.bike_model_id) as combo'))
            ->groupBy('combo')
            ->havingRaw('COUNT(*) >= 5')
            ->pluck('combo')
            ->flip();

        // 都道府県×カテゴリの有効な組み合わせを事前取得（10台以上）
        $activeCatPrefSet = DB::table('listings')
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->where('listings.is_sold_out', false)
            ->whereNotNull('bike_models.category_id')
            ->select(DB::raw('CONCAT(shops.prefecture, "-", bike_models.category_id) as combo'))
            ->groupBy('combo')
            ->havingRaw('COUNT(*) >= 10')
            ->pluck('combo')
            ->flip();

        // 都道府県×排気量帯の有効な組み合わせを事前取得（10台以上）
        // SeoLandingService::findDisplacement と同じレンジ定義
        $dispSlugRanges = [
            '原付' => [0, 50],
            'スクーター' => [0, 125],
            '小型' => [51, 125],
            '中型' => [126, 400],
            '大型' => [401, null],
            'リッター' => [1000, null],
        ];
        $activeDispPrefSet = collect();
        foreach ($dispSlugRanges as $slug => [$min, $max]) {
            $query = DB::table('listings')
                ->join('shops', 'listings.shop_id', '=', 'shops.id')
                ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
                ->where('listings.is_sold_out', false)
                ->whereNotNull('bike_models.displacement')
                ->where('bike_models.displacement', '>=', $min);
            if ($max !== null) {
                $query->where('bike_models.displacement', '<=', $max);
            }
            $prefectures = $query
                ->select('shops.prefecture')
                ->groupBy('shops.prefecture')
                ->havingRaw('COUNT(*) >= 10')
                ->pluck('prefecture');
            foreach ($prefectures as $p) {
                $activeDispPrefSet->put("{$p}-{$slug}", true);
            }
        }

        $this->info("  有効な都道府県×メーカー: {$activeManufPrefSet->count()} / 都道府県×車種: {$activeModelPrefSet->count()}");

        // 1. メーカー・カテゴリ・排気量の組み合わせ（10台以上のみ）
        foreach ($allPrefectures as $pref) {
            $fullPref = $toFullPref($pref);

            foreach ($manufacturers as $maker) {
                if (! $activeManufPrefSet->has("{$fullPref}-{$maker->id}")) {
                    continue;
                }
                $writeLandingUrl(
                    route('bikes.landing', ['prefecture' => $pref, 'slug' => $maker->name]),
                    date('Y-m-d'),
                    'weekly',
                    '0.7'
                );
            }

            foreach ($categories as $cat) {
                if (! $activeCatPrefSet->has("{$fullPref}-{$cat->id}")) {
                    continue;
                }
                $writeLandingUrl(
                    route('bikes.landing', ['prefecture' => $pref, 'slug' => $cat->name]),
                    date('Y-m-d'),
                    'weekly',
                    '0.7'
                );
            }

            foreach ($displacements as $disp) {
                if (! $activeDispPrefSet->has("{$fullPref}-{$disp}")) {
                    continue;
                }
                $writeLandingUrl(
                    route('bikes.landing', ['prefecture' => $pref, 'slug' => $disp]),
                    date('Y-m-d'),
                    'weekly',
                    '0.7'
                );
            }
        }

        // 2. 車種名(モデル)との掛け合わせ（Listingがある場合のみ）
        // URLは車種「名」で生成するため、同名の重複モデル行が複数 active だと
        // 同一URLを二重出力してしまう。(都道府県,車種名) で重複排除する。
        // ※ resolvePageInfo 側は searchByName が listings_count desc 順なので
        //   在庫最多の行に解決される＝出力URLとページ実体は整合する。
        $seenModelPref = [];
        BikeModel::select('id', 'name', 'updated_at')->chunk(500, function ($models) use ($allPrefectures, $writeLandingUrl, $activeModelPrefSet, $toFullPref, &$seenModelPref) {
            foreach ($models as $model) {
                foreach ($allPrefectures as $pref) {
                    if (! $activeModelPrefSet->has("{$toFullPref($pref)}-{$model->id}")) {
                        continue;
                    }
                    $dedupeKey = $pref."\x1f".$model->name;
                    if (isset($seenModelPref[$dedupeKey])) {
                        continue;
                    }
                    $seenModelPref[$dedupeKey] = true;
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
        // 2.5. 市区町村レベルSEOランディングページ (sitemap-city-landings-{n}.xml)
        // =========================================================
        $this->info('市区町村SEOランディングサイトマップを生成中...');

        // 古いファイルを削除
        foreach (glob(public_path('sitemap-city-landings-*.xml')) as $old) {
            unlink($old);
        }

        $cityFileIndex = 1;
        $cityUrlCount = 0;
        $totalCityCount = 0;

        $currentCityFileName = "sitemap-city-landings-{$cityFileIndex}.xml";
        $cityHandle = $this->openSitemap($currentCityFileName);
        $sitemapFiles[] = $currentCityFileName;

        $writeCityUrl = function ($loc, $lastmod, $freq, $priority) use (&$cityHandle, &$cityUrlCount, &$cityFileIndex, &$sitemapFiles, &$totalCityCount) {
            if ($cityUrlCount >= self::MAX_URLS_PER_FILE) {
                $this->closeSitemap($cityHandle);
                $this->info("  -> 分割: sitemap-city-landings-{$cityFileIndex}.xml 完了");

                $cityFileIndex++;
                $cityUrlCount = 0;

                $nextFileName = "sitemap-city-landings-{$cityFileIndex}.xml";
                $cityHandle = $this->openSitemap($nextFileName);
                $sitemapFiles[] = $nextFileName;
            }

            $this->writeUrl($cityHandle, $loc, $lastmod, $freq, $priority);
            $cityUrlCount++;
            $totalCityCount++;
        };

        // 市区町村×メーカー（5台以上のみ）
        $cityMakerCombos = DB::table('listings')
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->where('listings.is_sold_out', false)
            ->whereNotNull('listings.bike_model_id')
            ->whereNotNull('shops.city')
            ->select(DB::raw('shops.prefecture, shops.city, listings.manufacturer_id, COUNT(*) as cnt'))
            ->groupBy('shops.prefecture', 'shops.city', 'listings.manufacturer_id')
            ->havingRaw('COUNT(*) >= 10')
            ->get();

        $makerSlugs = Manufacturer::whereNotNull('slug')->pluck('slug', 'id');

        // config都道府県→短縮形への逆引きマップ
        $toShortPref = collect($regions)->flatten()->mapWithKeys(fn ($p) => [$toFullPref($p) => $p]);

        foreach ($cityMakerCombos as $combo) {
            $mfrSlug = $makerSlugs->get($combo->manufacturer_id);
            $shortPref = $toShortPref->get($combo->prefecture);
            if (! $mfrSlug || ! $shortPref) {
                continue;
            }
            $writeCityUrl(
                route('bikes.city_landing', ['prefecture' => $shortPref, 'city' => $combo->city, 'slug' => $mfrSlug]),
                date('Y-m-d'),
                'weekly',
                '0.6'
            );
        }

        // 市区町村×車種（5台以上のみ）
        $cityModelCombos = DB::table('listings')
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->where('listings.is_sold_out', false)
            ->whereNotNull('listings.bike_model_id')
            ->whereNotNull('shops.city')
            ->whereNotNull('bike_models.slug')
            ->select(DB::raw('shops.prefecture, shops.city, bike_models.slug as model_slug, COUNT(*) as cnt'))
            ->groupBy('shops.prefecture', 'shops.city', 'bike_models.slug')
            ->havingRaw('COUNT(*) >= 10')
            ->get();

        foreach ($cityModelCombos as $combo) {
            $shortPref = $toShortPref->get($combo->prefecture);
            if (! $shortPref) {
                continue;
            }
            $writeCityUrl(
                route('bikes.city_landing', ['prefecture' => $shortPref, 'city' => $combo->city, 'slug' => $combo->model_slug]),
                date('Y-m-d'),
                'weekly',
                '0.6'
            );
        }

        $this->closeSitemap($cityHandle);
        $this->info(" -> {$totalCityCount} URL (City Landings Total, {$cityFileIndex} files)");

        // =========================================================
        // 3. カタログページ (sitemap-catalog.xml)
        // =========================================================
        $this->info('カタログページサイトマップを生成中...');
        $catalogFileName = 'sitemap-catalog.xml';
        $handle = $this->openSitemap($catalogFileName);
        $sitemapFiles[] = $catalogFileName;
        $catalogCount = 0;

        // 在庫チェック用データを事前取得（N+1回避）
        $catalogMakersWithStock = Listing::where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->distinct()
            ->pluck('bike_models.manufacturer_id')
            ->flip();

        $catalogMakerCatWithStock = Listing::where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->selectRaw('DISTINCT bike_models.manufacturer_id, bike_models.category_id')
            ->get()
            ->map(fn ($r) => $r->manufacturer_id.'-'.$r->category_id)
            ->flip();

        $catalogCcWithStock = Listing::where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->whereNotNull('bike_models.displacement')
            ->pluck('bike_models.displacement');

        // 排気量帯の範囲定義
        $ccRanges = [
            '50cc' => ['min' => 0,   'max' => 50],
            '125cc' => ['min' => 51,  'max' => 125],
            '250cc' => ['min' => 126, 'max' => 250],
            '400cc' => ['min' => 251, 'max' => 400],
            '750cc' => ['min' => 401, 'max' => 750],
        ];

        // メーカー単体のカタログページ（在庫ありのみ）
        $catalogManufacturers = Manufacturer::whereNotNull('slug')->get();
        foreach ($catalogManufacturers as $maker) {
            if (! $catalogMakersWithStock->has($maker->id)) {
                continue;
            }
            $this->writeUrl(
                $handle,
                route('bikes.catalog', $maker->slug),
                date('Y-m-d'),
                'weekly',
                '0.8'
            );
            $catalogCount++;
        }

        // メーカー×カテゴリの組み合わせカタログページ（在庫ありのみ）
        $catalogCategories = Category::whereNotNull('slug')->get();
        foreach ($catalogManufacturers as $maker) {
            foreach ($catalogCategories as $cat) {
                if (! $catalogMakerCatWithStock->has($maker->id.'-'.$cat->id)) {
                    continue;
                }
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

        // 排気量帯カタログページ（在庫ありのみ）
        foreach ($ccRanges as $cc => $range) {
            $hasStock = $catalogCcWithStock->contains(fn ($d) => $d >= $range['min'] && $d <= $range['max']);
            if (! $hasStock) {
                continue;
            }
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
        // 3.4. 輸入バイクLP (sitemap-overseas.xml)
        // =========================================================
        $this->info('輸入バイクサイトマップを生成中...');
        $overseasFileName = 'sitemap-overseas.xml';
        $handle = $this->openSitemap($overseasFileName);
        $sitemapFiles[] = $overseasFileName;
        $overseasCount = 0;

        // まとめページ
        $this->writeUrl($handle, route('bikes.overseas'), date('Y-m-d'), 'weekly', '0.7');
        $overseasCount++;

        // メーカー別ページ（在庫5台以上）
        $domesticNames = ['ホンダ', 'ヤマハ', 'カワサキ', 'スズキ'];
        $overseasMakers = Manufacturer::whereNotIn('name', $domesticNames)
            ->whereNotNull('slug')
            ->whereHas('bikeModels.listings', fn ($q) => $q->where('is_sold_out', false))
            ->get();

        foreach ($overseasMakers as $maker) {
            $listingCount = DB::table('listings')
                ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
                ->where('bike_models.manufacturer_id', $maker->id)
                ->where('listings.is_sold_out', false)
                ->count();

            if ($listingCount >= 5) {
                $this->writeUrl(
                    $handle,
                    route('bikes.overseas.maker', $maker->slug),
                    date('Y-m-d'),
                    'weekly',
                    '0.6'
                );
                $overseasCount++;
            }
        }

        $this->closeSitemap($handle);
        $this->info(" -> {$overseasCount} URL (Overseas)");

        // =========================================================
        // 3.4.1. カテゴリ別LP (排気量+タイプ 計16ページ)
        // =========================================================
        $this->info('カテゴリ別LPをメインサイトマップに追加中...');
        $categoryLpFileName = 'sitemap-category-lp.xml';
        $handle = $this->openSitemap($categoryLpFileName);
        $sitemapFiles[] = $categoryLpFileName;
        $categoryLpCount = 0;

        // 排気量別 6ページ
        foreach (['50', '125', '250', '400', '750', 'over750'] as $ccSlug) {
            $this->writeUrl($handle, route('bikes.category_cc', $ccSlug), date('Y-m-d'), 'weekly', '0.7');
            $categoryLpCount++;
        }

        // タイプ別 10ページ
        foreach (['naked', 'scooter', 'american', 'sport', 'offroad', 'tourer', 'classic', 'adventure', 'street-fighter', 'mini'] as $typeSlug) {
            $this->writeUrl($handle, route('bikes.category_type', $typeSlug), date('Y-m-d'), 'weekly', '0.7');
            $categoryLpCount++;
        }

        $this->closeSitemap($handle);
        $this->info(" -> {$categoryLpCount} URL (Category LP)");

        // =========================================================
        // 3.5. 車種比較ページ (sitemap-compare.xml)
        // =========================================================
        if (config('comparison.sitemap_enabled')) {
            $this->info('車種比較サイトマップを生成中...');
            $compareFileName = 'sitemap-compare.xml';
            $handle = $this->openSitemap($compareFileName);
            $sitemapFiles[] = $compareFileName;
            $compareCount = 0;

            SeoCompare::active()->select('slug', 'updated_at')->chunk(100, function ($compares) use ($handle, &$compareCount) {
                foreach ($compares as $compare) {
                    $this->writeUrl(
                        $handle,
                        route('bikes.model_compare', $compare->slug),
                        $compare->updated_at?->format('Y-m-d') ?? date('Y-m-d'),
                        'weekly',
                        '0.7'
                    );
                    $compareCount++;
                }
            });

            $this->closeSitemap($handle);
            $this->info(" -> {$compareCount} URL (Compare)");
        } else {
            $this->info('比較サイトマップ: スキップ(gated until Slice B)');
        }

        // =========================================================
        // 3.55. 地域差ページ (sitemap-region-price.xml) — config フラグでゲート
        // =========================================================
        if (config('region_price.sitemap_enabled')) {
            $this->info('地域差ページサイトマップを生成中...');
            $regionPriceFileName = 'sitemap-region-price.xml';
            $handle = $this->openSitemap($regionPriceFileName);
            $sitemapFiles[] = $regionPriceFileName;
            $regionPriceCount = 0;

            foreach (app(\App\Services\Bike\RegionalPriceService::class)->gatedRegionPriceModels(3, 20) as $row) {
                $this->writeUrl(
                    $handle,
                    route('bikes.region_price', $row['model']->slug),
                    date('Y-m-d'),
                    'weekly',
                    '0.6'
                );
                $regionPriceCount++;
            }

            $this->closeSitemap($handle);
            $this->info(" -> {$regionPriceCount} URL (RegionPrice)");
        } else {
            $this->info('地域差ページサイトマップ: スキップ(gated・既定false)');
        }

        // =========================================================
        // 3.6. 特集LP (sitemap-features.xml)
        // =========================================================
        $this->info('特集LPサイトマップを生成中...');
        $featureFileName = 'sitemap-features.xml';
        $handle = $this->openSitemap($featureFileName);
        $sitemapFiles[] = $featureFileName;
        $featureCount = 0;

        SeoFeature::active()->select('slug', 'updated_at')->ordered()->chunk(100, function ($features) use ($handle, &$featureCount) {
            foreach ($features as $feature) {
                $this->writeUrl(
                    $handle,
                    route('features.show', $feature->slug),
                    $feature->updated_at?->format('Y-m-d') ?? date('Y-m-d'),
                    'weekly',
                    '0.8'
                );
                $featureCount++;
            }
        });

        $this->closeSitemap($handle);
        $this->info(" -> {$featureCount} URL (Features)");

        // =========================================================
        // 4. 店舗詳細サイトマップ (sitemap-shops.xml)
        // =========================================================
        $this->info('店舗詳細サイトマップを生成中...');
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
        $this->info('駐車場サイトマップを生成中...');

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
        $this->info('駐車場エリアサイトマップを生成中...');
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
        // 4.6.1. ショップエリアページ (sitemap-shop-area.xml)
        // =========================================================
        $this->info('ショップエリアサイトマップを生成中...');
        $shopAreaFileName = 'sitemap-shop-area.xml';
        $handle = $this->openSitemap($shopAreaFileName);
        $sitemapFiles[] = $shopAreaFileName;
        $shopAreaCount = 0;

        // エリアインデックス
        $this->writeUrl($handle, route('shops.area.index'), date('Y-m-d'), 'weekly', '0.8');
        $shopAreaCount++;

        // 都道府県ページ + 市区町村ページ
        $shopAreaService = app(\App\Services\Shop\ShopAreaService::class);
        $allShopPrefs = $shopAreaService->getAllPrefectures();

        foreach ($allShopPrefs as $pref) {
            $this->writeUrl(
                $handle,
                route('shops.area.prefecture', $pref),
                date('Y-m-d'),
                'weekly',
                '0.7'
            );
            $shopAreaCount++;

            // 市区町村ページ（3店以上のみ）
            $shopCities = $shopAreaService->getCitiesForPrefecture($pref);
            foreach ($shopCities as $city) {
                if ($shopAreaService->getShopCountForCity($pref, $city) >= 3) {
                    $this->writeUrl(
                        $handle,
                        route('shops.area.city', [$pref, $city]),
                        date('Y-m-d'),
                        'weekly',
                        '0.6'
                    );
                    $shopAreaCount++;
                }
            }
        }

        $this->closeSitemap($handle);
        $this->info(" -> {$shopAreaCount} URL (Shop Area)");

        // =========================================================
        // 4.6.2. 整備・修理ショップエリアページ (sitemap-shop-repair.xml)
        // =========================================================
        $this->info('整備・修理ショップサイトマップを生成中...');
        $shopRepairFileName = 'sitemap-shop-repair.xml';
        $handle = $this->openSitemap($shopRepairFileName);
        $sitemapFiles[] = $shopRepairFileName;
        $shopRepairCount = 0;

        // 整備インデックス
        $this->writeUrl($handle, route('shops.repair.index'), date('Y-m-d'), 'weekly', '0.7');
        $shopRepairCount++;

        // 都道府県ページ（整備店あり）+ 市区町村ページ（3店以上のみ）
        foreach ($shopAreaService->getAllRepairPrefectures() as $pref) {
            $this->writeUrl($handle, route('shops.repair.prefecture', $pref), date('Y-m-d'), 'weekly', '0.6');
            $shopRepairCount++;

            foreach ($shopAreaService->getRepairCitiesForPrefecture($pref) as $city) {
                if ($shopAreaService->getRepairShopCountForCity($pref, $city) >= 3) {
                    $this->writeUrl($handle, route('shops.repair.city', [$pref, $city]), date('Y-m-d'), 'weekly', '0.6');
                    $shopRepairCount++;
                }
            }
        }

        $this->closeSitemap($handle);
        $this->info(" -> {$shopRepairCount} URL (Shop Repair)");

        // =========================================================
        // 4.6.3. 車種×作業（型番）ページ (sitemap-fitments.xml)
        //  公開ゲート＝verified行のある モデル×task のみ。
        // =========================================================
        $this->info('型番（適合表）サイトマップを生成中...');
        $fitmentsFileName = 'sitemap-fitments.xml';
        $handle = $this->openSitemap($fitmentsFileName);
        $sitemapFiles[] = $fitmentsFileName;
        $fitmentsCount = 0;

        $fitmentPairs = \App\Models\ModelFitment::query()
            ->verified()
            ->join('bike_models', 'bike_models.id', '=', 'model_fitments.bike_model_id')
            ->whereNotNull('bike_models.slug')->where('bike_models.slug', '!=', '')
            ->select('bike_models.slug as slug', 'model_fitments.task as task')
            ->distinct()
            ->get();

        foreach ($fitmentPairs as $pair) {
            $this->writeUrl($handle, route('fitments.show', ['bikeModel' => $pair->slug, 'task' => $pair->task]), date('Y-m-d'), 'monthly', '0.6');
            $fitmentsCount++;
        }

        $this->closeSitemap($handle);
        $this->info(" -> {$fitmentsCount} URL (Fitments)");

        // =========================================================
        // 4.6.4. Googleニュース sitemap (sitemap-news.xml)
        //  仕様: 直近2日(48h)以内に公開された「オリジナル」記事のみ。
        //  RSS転載記事は含めない（scopeOriginal）。デイリーランキングで常時1本以上入る想定。
        // =========================================================
        $this->info('ニュース sitemap を生成中...');
        $newsFileName = self::GOOGLE_NEWS_SITEMAP;
        $newsHandle = fopen(public_path($newsFileName), 'w');
        fwrite($newsHandle, '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL);
        fwrite($newsHandle, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">'.PHP_EOL);
        $sitemapFiles[] = $newsFileName;
        $newsCount = 0;

        foreach (self::googleNewsArticles() as $news) {
            fwrite($newsHandle, self::renderNewsSitemapEntry($news));
            $newsCount++;
        }

        fwrite($newsHandle, '</urlset>');
        fclose($newsHandle);
        $this->info(" -> {$newsCount} URL (News・直近2日のオリジナルのみ)");

        // =========================================================
        // 4.7. 駅別駐車場ページ (sitemap-parking-station.xml)
        // =========================================================
        $this->info('駅別駐車場サイトマップを生成中...');
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
        // 5. 車種詳細ページ (sitemap-models.xml)
        // =========================================================
        $this->info('車種詳細サイトマップを生成中...');
        $modelFileName = 'sitemap-models.xml';
        $handle = $this->openSitemap($modelFileName);
        $sitemapFiles[] = $modelFileName;
        $modelCount = 0;

        BikeModel::with('manufacturer')
            ->select('id', 'slug', 'manufacturer_id', 'updated_at')
            ->whereNotNull('slug')
            ->whereNull('merged_into_id')
            ->whereHas('manufacturer', fn ($q) => $q->whereNotNull('slug'))
            ->orderBy('updated_at', 'desc')
            ->chunk(1000, function ($models) use ($handle, &$modelCount) {
                foreach ($models as $model) {
                    $mfrSlug = $model->manufacturer->slug;
                    $url = url("/bikes/{$mfrSlug}/{$model->slug}");
                    $this->writeUrl(
                        $handle,
                        $url,
                        $model->updated_at->format('Y-m-d'),
                        'weekly',
                        '0.7'
                    );
                    $modelCount++;
                }
            });

        $this->closeSitemap($handle);
        $this->info(" -> {$modelCount} URL (Model Detail)");

        // =========================================================
        // 6. パーツカテゴリページ (sitemap-parts.xml)
        // =========================================================
        $this->info('パーツカテゴリサイトマップを生成中...');
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
        $this->info('ランキングサイトマップを生成中...');
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

        // 車種別ランキングページ（先月販売3台以上のモデルのみ）
        $lms = \Carbon\Carbon::now()->subMonth()->startOfMonth();
        $lme = \Carbon\Carbon::now()->subMonth()->endOfMonth();
        Listing::cappedSold($lms, $lme)->excludeBulkSold()
            ->whereNotNull('bike_model_id')
            ->select('bike_model_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('bike_model_id')
            ->havingRaw('COUNT(*) >= 3')
            ->orderBy('bike_model_id')
            ->chunk(500, function ($rows) use ($handle, &$rankingCount) {
                foreach ($rows as $row) {
                    $this->writeUrl($handle, route('ranking.model_stats', $row->bike_model_id), date('Y-m-d'), 'weekly', '0.5');
                    $rankingCount++;
                }
            });

        $this->closeSitemap($handle);
        $this->info(" -> {$rankingCount} URL (Rankings)");

        // =========================================================
        // 6.8. ツーリングガイドサイトマップ (sitemap-touring.xml)
        // =========================================================
        $this->info('ツーリングガイドサイトマップを生成中...');
        $touringFileName = 'sitemap-touring.xml';
        $handle = $this->openSitemap($touringFileName);
        $sitemapFiles[] = $touringFileName;
        $touringCount = 0;

        $this->writeUrl($handle, url('/touring'), date('Y-m-d'), 'weekly', '0.7');
        $touringCount++;

        \App\Models\TouringGuide::published()
            ->orderByDesc('published_at')
            ->chunk(500, function ($guides) use ($handle, &$touringCount) {
                foreach ($guides as $guide) {
                    $this->writeUrl(
                        $handle,
                        url('/touring/'.$guide->slug),
                        $guide->updated_at->toDateString(),
                        'monthly',
                        '0.6'
                    );
                    $touringCount++;
                }
            });

        // ツーリングスポット — 都道府県一覧ページ
        $spotPrefectures = \App\Models\TouringSpot::select('prefecture')->distinct()->pluck('prefecture');
        foreach ($spotPrefectures as $pref) {
            $slug = \App\Models\TouringSpot::slugFromPrefectureName($pref);
            if ($slug) {
                $this->writeUrl($handle, url("/touring/{$slug}"), date('Y-m-d'), 'weekly', '0.6');
                $touringCount++;
            }
        }

        // ツーリングスポット — 個別ページ
        \App\Models\TouringSpot::orderBy('prefecture')->orderBy('name')
            ->chunk(500, function ($spots) use ($handle, &$touringCount) {
                foreach ($spots as $spot) {
                    $prefSlug = \App\Models\TouringSpot::slugFromPrefectureName($spot->prefecture);
                    if ($prefSlug) {
                        $this->writeUrl(
                            $handle,
                            url("/touring/{$prefSlug}/{$spot->slug}"),
                            $spot->updated_at->toDateString(),
                            'monthly',
                            '0.5'
                        );
                        $touringCount++;
                    }
                }
            });

        $this->closeSitemap($handle);
        $this->info(" -> {$touringCount} URL (Touring Guides + Spots)");

        // =========================================================
        // 6.9. チェーン店サイトマップ (sitemap-chains.xml)
        // =========================================================
        $this->info('チェーン店サイトマップを生成中...');
        $chainFileName = 'sitemap-chains.xml';
        $handle = $this->openSitemap($chainFileName);
        $sitemapFiles[] = $chainFileName;
        $chainCount = 0;

        $chains = config('bike.chains', []);
        foreach (array_keys($chains) as $chainSlug) {
            $this->writeUrl($handle, route('shops.chain', $chainSlug), date('Y-m-d'), 'weekly', '0.6');
            $chainCount++;
        }

        $this->closeSitemap($handle);
        $this->info(" -> {$chainCount} URL (Chains)");

        // =========================================================
        // 6.10. オリジナルニュース記事の通常サイトマップ (sitemap-news-articles.xml)
        //  全オリジナル記事＋一覧ページを通常indexing用に収録。
        //  ※ Googleニュース用の news:news 付きは別途 sitemap-news.xml（4.6.4）。
        // =========================================================
        $this->info('オリジナルニュース記事サイトマップを生成中...');
        $newsFileName = 'sitemap-news-articles.xml';
        $handle = $this->openSitemap($newsFileName);
        $sitemapFiles[] = $newsFileName;
        $newsCount = 0;

        // ニュース一覧ページ
        $this->writeUrl($handle, route('news.index'), date('Y-m-d'), 'daily', '0.7');
        $newsCount++;

        // MotoHubオリジナル記事のみ
        \App\Models\BikeNews::original()
            ->orderByDesc('published_at')
            ->chunk(500, function ($articles) use ($handle, &$newsCount) {
                foreach ($articles as $article) {
                    $this->writeUrl(
                        $handle,
                        route('news.show', $article->id),
                        $article->updated_at->toDateString(),
                        'weekly',
                        '0.6'
                    );
                    $newsCount++;
                }
            });

        $this->closeSitemap($handle);
        $this->info(" -> {$newsCount} URL (News Articles・全オリジナル)");

        // =========================================================
        // 6.11. 在庫あり車両詳細サイトマップ (sitemap-listings-1.xml)
        //   目的: Bing向けに車両詳細 /bikes/{数字} を発見させる（Googleはrobots.txtで
        //   /bikes/0..9 を遮断済みのため、このURLはBing用。Google側はサイトマップに
        //   載っても robots ブロックでクロールせず「ブロック済み」警告になるだけで実害なし）。
        //   段階リリース: bargain_score 降順の上限 N 件のみ。売り切れ(is_sold_out=true)は対象外。
        //   先頭の glob クリーンアップ(sitemap-listings-*.xml)で毎回再生成される。
        // =========================================================
        $this->info('在庫あり車両詳細サイトマップを生成中...');
        $listingFileName = 'sitemap-listings-1.xml';
        $handle = $this->openSitemap($listingFileName);
        $sitemapFiles[] = $listingFileName;
        $listingCount = 0;

        // 上限N件(段階リリース)。idx_active_bargain(is_sold_out, bargain_score)を利用。
        $listingSitemapLimit = 2000;
        Listing::where('is_sold_out', false)
            ->whereNotNull('bargain_score')
            ->orderByDesc('bargain_score')
            ->limit($listingSitemapLimit)
            ->get(['id', 'updated_at'])
            ->each(function ($listing) use ($handle, &$listingCount) {
                $this->writeUrl(
                    $handle,
                    route('bikes.show', $listing->id),
                    $listing->updated_at->toDateString(),
                    'daily',
                    '0.5'
                );
                $listingCount++;
            });

        $this->closeSitemap($handle);
        $this->info(" -> {$listingCount} URL (In-stock Listings, bargain_score上位{$listingSitemapLimit})");

        // =========================================================
        // 7. サイトマップインデックス (目次) の生成
        // =========================================================
        $this->info('インデックスファイル (sitemap.xml) を生成中...');
        $this->generateIndexFile($sitemapFiles);

        // =========================================================
        // 8. IndexNow通知（差分送信）
        // =========================================================
        $this->submitIndexNow();

        $duration = round(microtime(true) - $startTime, 2);
        $this->info("全ての処理が完了しました！ ({$duration}秒)");
    }

    private function pingGoogle(): void
    {
        // GoogleのPing送信機能は廃止されたため、メソッド内は空にしておくか、削除してもOKです
    }

    /**
     * ブログサイトマップからURLを収集してcollectedUrlsに追加
     */
    private function collectBlogSitemapUrls(): void
    {
        $blogSitemapPath = storage_path('app/public/'.config('blog.sitemap.path', 'sitemap-blog.xml'));

        if (! file_exists($blogSitemapPath)) {
            return;
        }

        $xml = @simplexml_load_file($blogSitemapPath);
        if (! $xml) {
            return;
        }

        foreach ($xml->url as $entry) {
            $loc = (string) $entry->loc;
            $lastmod = (string) $entry->lastmod;
            if ($loc) {
                $this->collectedUrls[$loc] = $lastmod ?: date('Y-m-d');
            }
        }
    }

    /**
     * IndexNow差分送信
     */
    private function submitIndexNow(): void
    {
        $indexNowKey = config('services.indexnow.key');
        if (! $indexNowKey) {
            return;
        }

        // ブログサイトマップのURLも収集
        $this->collectBlogSitemapUrls();

        $currentUrls = $this->collectedUrls;
        $snapshotPath = storage_path('app/'.self::INDEXNOW_SNAPSHOT_FILE);

        // 前回スナップショットを読み込み
        $previousUrls = [];
        if (file_exists($snapshotPath)) {
            $previousUrls = json_decode(file_get_contents($snapshotPath), true) ?: [];
        }

        // 差分検出: 新規URL or lastmodが変わったURL
        $changedUrls = [];
        foreach ($currentUrls as $url => $lastmod) {
            if (! isset($previousUrls[$url]) || $previousUrls[$url] !== $lastmod) {
                $changedUrls[] = $url;
            }
        }

        if (empty($changedUrls)) {
            $this->info('IndexNow: 差分なし — スキップ');
            // スナップショットは更新（削除されたURLを反映）
            file_put_contents($snapshotPath, json_encode($currentUrls));

            return;
        }

        $this->info("IndexNow: {$this->formatCount(count($changedUrls))}件の差分URLを送信中...");
        $batches = array_chunk($changedUrls, self::INDEXNOW_BATCH_SIZE);
        $totalSent = 0;
        $totalFailed = 0;

        foreach ($batches as $i => $batch) {
            try {
                $response = Http::timeout(30)->post('https://api.indexnow.org/indexnow', [
                    'host' => 'motohub.jp',
                    'key' => $indexNowKey,
                    'keyLocation' => "https://motohub.jp/{$indexNowKey}.txt",
                    'urlList' => $batch,
                ]);

                if ($response->successful() || $response->status() === 202) {
                    $totalSent += count($batch);
                    $this->info('  バッチ'.($i + 1).'/'.count($batches).': '.count($batch)."件送信OK (status: {$response->status()})");
                } else {
                    $totalFailed += count($batch);
                    $this->warn('  バッチ'.($i + 1).": FAIL (status: {$response->status()})");
                }
            } catch (\Throwable $e) {
                $totalFailed += count($batch);
                $this->warn('  バッチ'.($i + 1).": ERROR ({$e->getMessage()})");
            }

            // レート制限対策: バッチ間に1秒待機
            if ($i < count($batches) - 1) {
                sleep(1);
            }
        }

        // スナップショット更新
        file_put_contents($snapshotPath, json_encode($currentUrls));

        $this->info("IndexNow完了: 送信={$this->formatCount($totalSent)} 失敗={$this->formatCount($totalFailed)} (全収集URL: {$this->formatCount(count($currentUrls))}件)");
    }

    private function formatCount(int $count): string
    {
        return number_format($count);
    }

    private function openSitemap(string $filename)
    {
        $path = public_path($filename);
        $handle = fopen($path, 'w');
        fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL);
        fwrite($handle, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL);

        return $handle;
    }

    private function writeUrl($handle, $loc, $lastmod, $freq, $priority)
    {
        // IndexNow差分検出用にURL収集
        $this->collectedUrls[$loc] = $lastmod;

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

        fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL);
        fwrite($handle, '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL);

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
