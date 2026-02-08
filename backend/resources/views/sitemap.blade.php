<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use App\Models\BikeModel;
use App\Models\Shop;
use App\Models\Manufacturer;
use App\Models\Category;
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
            ['route' => 'bikes.index',       'priority' => '1.0', 'freq' => 'daily'],
            ['route' => 'bikes.prefectures', 'priority' => '0.9', 'freq' => 'monthly'],
            ['route' => 'bikes.search',      'priority' => '0.9', 'freq' => 'daily'],
            ['route' => 'bikes.models',      'priority' => '0.9', 'freq' => 'weekly'],
            ['route' => 'bikes.compare',     'priority' => '0.5', 'freq' => 'daily'],
            ['route' => 'wishlist',          'priority' => '0.5', 'freq' => 'monthly'],
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

        $this->closeSitemap($handle);
        $sitemapFiles[] = $mainFileName;
        $this->info(" -> {$count} URL (Main)");


        // =========================================================
        // 2. SEOランディングページ (sitemap-landings.xml)
        // =========================================================
        $this->info("SEOランディングサイトマップを生成中...");
        $landingFileName = 'sitemap-landings.xml';
        $handle = $this->openSitemap($landingFileName);
        $sitemapFiles[] = $landingFileName;
        $landingCount = 0;

        $manufacturers = Manufacturer::all();
        $categories = Category::all();

        foreach ($allPrefectures as $pref) {
            foreach ($manufacturers as $maker) {
                $this->writeUrl(
                    $handle,
                    route('landing', ['prefecture' => $pref, 'slug' => $maker->name]),
                    date('Y-m-d'),
                    'weekly',
                    '0.7'
                );
                $landingCount++;
            }

            foreach ($categories as $cat) {
                $this->writeUrl(
                    $handle,
                    route('landing', ['prefecture' => $pref, 'slug' => $cat->name]),
                    date('Y-m-d'),
                    'weekly',
                    '0.7'
                );
                $landingCount++;
            }
        }

        $this->closeSitemap($handle);
        $this->info(" -> {$landingCount} URL (Landings)");


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
        // 4. ★追加: 車種別カタログページ (sitemap-models.xml)
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
                        '0.8' // 検索結果より優先度高め
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

        // GoogleへのPing送信
        $this->pingGoogle();
    }

    private function pingGoogle(): void
    {
        if (!app()->isProduction()) {
            $this->info("GoogleへのPing送信をスキップしました (ローカル環境のため)");
            return;
        }

        $sitemapUrl = url('sitemap.xml');
        $googleUrl = "http://www.google.com/ping?sitemap={$sitemapUrl}";

        $this->info("Googleへサイトマップ更新を通知中...: {$sitemapUrl}");

        try {
            $response = Http::get($googleUrl);
            if ($response->successful()) {
                $this->info("✅ GoogleへのPing送信に成功しました。");
            } else {
                $this->error("❌ GoogleへのPing送信に失敗しました: " . $response->status());
            }
        } catch (\Exception $e) {
            $this->error("❌ GoogleへのPing送信中にエラーが発生しました: " . $e->getMessage());
        }
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