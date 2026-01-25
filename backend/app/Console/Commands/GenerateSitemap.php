<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use App\Models\BikeModel;
use Illuminate\Support\Facades\File;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';
    protected $description = 'サイトマップを分割生成し、インデックスファイルを作成します';

    // 1ファイルあたりのURL上限（安全のため45,000件に設定）
    private const MAX_URLS_PER_FILE = 45000;

    public function handle(): void
    {
        ini_set('memory_limit', '512M');
        $this->info("サイトマップの分割生成を開始します...");
        $startTime = microtime(true);

        // 生成されたサブサイトマップのファイル名を記録する配列
        $sitemapFiles = [];

        // =========================================================
        // 1. メインサイトマップ (固定ページ + 車種検索結果)
        // =========================================================
        $mainFileName = 'sitemap-main.xml';
        $this->info("メインサイトマップ ({$mainFileName}) を生成中...");
        
        $handle = $this->openSitemap($mainFileName);
        $count = 0;

        // 固定ページ
        $staticPages = [
            ['route' => 'bikes.index',   'priority' => '1.0', 'freq' => 'daily'],
            ['route' => 'bikes.search',  'priority' => '0.9', 'freq' => 'daily'],
            ['route' => 'bikes.models',  'priority' => '0.9', 'freq' => 'weekly'],
            ['route' => 'bikes.compare', 'priority' => '0.5', 'freq' => 'daily'],
            ['route' => 'wishlist',      'priority' => '0.5', 'freq' => 'monthly'],
            ['route' => 'pages.about',   'priority' => '0.3', 'freq' => 'monthly'],
            ['route' => 'pages.contact', 'priority' => '0.3', 'freq' => 'monthly'],
            ['route' => 'pages.privacy-policy', 'priority' => '0.1', 'freq' => 'yearly'],
            ['route' => 'pages.terms',   'priority' => '0.1', 'freq' => 'yearly'],
        ];

        foreach ($staticPages as $page) {
            $this->writeUrl($handle, route($page['route']), date('Y-m-d'), $page['freq'], $page['priority']);
            $count++;
        }

        // 車種別ページ (検索結果)
        // ここで車種が5万件を超えることは稀なのでメインに入れますが、多すぎる場合はここも分割が必要です
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
        $this->info(" -> {$count} URL");


        // =========================================================
        // 2. 車両詳細ページ (50,000件ごとにファイルを分割)
        // =========================================================
        $this->info("車両詳細サイトマップを生成中...");
        
        $listingQuery = Listing::where('is_sold_out', false)
            ->select('id', 'updated_at')
            ->orderBy('updated_at', 'desc');

        $totalListings = $listingQuery->count();
        $fileIndex = 1;
        $currentUrlCount = 0;
        
        // 最初のファイルを開く
        $currentFileName = "sitemap-listings-{$fileIndex}.xml";
        $handle = $this->openSitemap($currentFileName);
        $sitemapFiles[] = $currentFileName;

        $listingQuery->chunk(1000, function ($listings) use (&$handle, &$currentUrlCount, &$fileIndex, &$sitemapFiles) {
            foreach ($listings as $listing) {
                // 上限に達したらファイルを閉じて、次を作る
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
            }
        });

        $this->closeSitemap($handle); // 最後のファイルを閉じる
        $this->info("  -> 分割: sitemap-listings-{$fileIndex}.xml 完了");


        // =========================================================
        // 3. サイトマップインデックス (目次) の生成
        // =========================================================
        $this->info("インデックスファイル (sitemap.xml) を生成中...");
        $this->generateIndexFile($sitemapFiles);


        $duration = round(microtime(true) - $startTime, 2);
        $this->info("全ての処理が完了しました！ ({$duration}秒)");
    }

    /**
     * ファイルを開き、XMLヘッダーを書き込む
     */
    private function openSitemap(string $filename)
    {
        $path = public_path($filename);
        $handle = fopen($path, 'w');
        fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);
        fwrite($handle, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL);
        return $handle;
    }

    /**
     * URL要素を1つ書き込む
     */
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

    /**
     * XMLフッターを書き込んでファイルを閉じる
     */
    private function closeSitemap($handle)
    {
        fwrite($handle, '</urlset>');
        fclose($handle);
    }

    /**
     * インデックスファイル (sitemap.xml) を生成する
     * これがGoogleに提出する親ファイルになります
     */
    private function generateIndexFile(array $files)
    {
        $indexPath = public_path('sitemap.xml');
        $handle = fopen($indexPath, 'w');

        fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);
        fwrite($handle, '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL);

        foreach ($files as $file) {
            // ファイルの最終更新日時を取得
            $lastmod = date('Y-m-d\TH:i:sP', filemtime(public_path($file)));
            $loc = url($file); // https://motohub.jp/sitemap-main.xml 等

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