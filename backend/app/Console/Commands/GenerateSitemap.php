<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use App\Models\BikeModel;
use App\Models\Shop;
use App\Models\Manufacturer;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';
    protected $description = 'サイトマップを分割生成し、インデックスファイルを作成してGoogleに通知します';

    // 1ファイルあたりのURL上限
    private const MAX_URLS_PER_FILE = 45000;

    public function handle(): void
    {
        ini_set('memory_limit', '512M');
        $this->info("サイトマップの分割生成を開始します...");
        $startTime = microtime(true);

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

        // タグ別の検索結果ページ
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

        // 1. メーカー・カテゴリ・排気量の組み合わせ
        foreach ($allPrefectures as $pref) {
            foreach ($manufacturers as $maker) {
                $writeLandingUrl(
                    route('bikes.landing', ['prefecture' => $pref, 'slug' => $maker->name]),
                    date('Y-m-d'),
                    'weekly',
                    '0.7'
                );
            }

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

        // 2. 車種名(モデル)との掛け合わせ
        // 件数が多いためChunk処理
        BikeModel::select('name', 'updated_at')->chunk(500, function ($models) use ($allPrefectures, $writeLandingUrl) {
            foreach ($models as $model) {
                foreach ($allPrefectures as $pref) {
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
        // 3. 店舗詳細サイトマップ (sitemap-shops.xml)
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

        $this->closeSitemap($handle);
        $this->info(" -> {$shopCount} URL (Shops)");


        // =========================================================
        // 4. 車種別カタログページ (sitemap-models.xml)
        // =========================================================
        $this->info("車種別カタログサイトマップを生成中...");
        $modelFileName = 'sitemap-models.xml';
        $handle = $this->openSitemap($modelFileName);
        $sitemapFiles[] = $modelFileName;
        $modelCount = 0;

        BikeModel::select('id', 'updated_at')
            ->orderBy('updated_at', 'desc')
            ->chunk(1000, function ($models) use ($handle, &$modelCount) {
                foreach ($models as $model) {
                    $this->writeUrl(
                        $handle,
                        route('bikes.model_detail', $model->id), // 相場情報ページ
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
        // 5. 車両詳細ページ (50,000件ごとにファイルを分割)
        // =========================================================
        $this->info("車両詳細サイトマップを生成中...");
        
        $listingQuery = Listing::where('is_sold_out', false)
            ->select('id', 'updated_at')
            ->orderBy('updated_at', 'desc');

        $fileIndex = 1;
        $currentUrlCount = 0;
        $totalListingsCount = 0;
        
        $currentFileName = "sitemap-listings-{$fileIndex}.xml";
        $handle = $this->openSitemap($currentFileName);
        $sitemapFiles[] = $currentFileName;

        $listingQuery->chunk(1000, function ($listings) use (&$handle, &$currentUrlCount, &$fileIndex, &$sitemapFiles, &$totalListingsCount) {
            foreach ($listings as $listing) {
                if ($currentUrlCount >= self::MAX_URLS_PER_FILE) {
                    $this->closeSitemap($handle);
                    $this->info("  -> 分割: sitemap-listings-{$fileIndex}.xml 完了");

                    $fileIndex++;
                    $currentUrlCount = 0;
                    
                    $nextFileName = "sitemap-listings-{$fileIndex}.xml";
                    $handle = $this->openSitemap($nextFileName);
                    $sitemapFiles[] = $nextFileName;
                }

                $this->writeUrl(
                    $handle,
                    route('bikes.show', $listing->id),
                    $listing->updated_at->format('Y-m-d'),
                    'weekly',
                    '0.6'
                );
                $currentUrlCount++;
                $totalListingsCount++;
            }
        });

        $this->closeSitemap($handle);
        $this->info(" -> {$totalListingsCount} URL (Listings)");


        // =========================================================
        // 6. サイトマップインデックス (目次) の生成
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