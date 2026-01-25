<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use App\Models\BikeModel;
use Illuminate\Support\Facades\URL;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';
    protected $description = 'サイトマップ(sitemap.xml)を生成してpublicディレクトリに保存します';

    public function handle(): void
    {
        // 念のためメモリ制限を少し緩和（必須ではありませんが安全のため）
        ini_set('memory_limit', '256M');

        $this->info("サイトマップの生成を開始します...");
        $startTime = microtime(true);

        $path = public_path('sitemap.xml');
        
        // ★変更点: ファイルを書き込みモードで開き、直接書き込んでいく方式に変更
        $handle = fopen($path, 'w');

        if ($handle === false) {
            $this->error("ファイルを開けませんでした: {$path}");
            return;
        }

        // XMLヘッダー書き込み
        fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);
        fwrite($handle, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL);

        $totalCount = 0;

        // URL書き込み用ヘルパー関数（メモリを食わないよう都度書き出し）
        $writeUrl = function ($loc, $lastmod, $freq, $priority) use ($handle, &$totalCount) {
            // XMLエスケープ処理
            $loc = htmlspecialchars($loc, ENT_XML1, 'UTF-8');
            
            $xml = "    <url>\n";
            $xml .= "        <loc>{$loc}</loc>\n";
            $xml .= "        <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "        <changefreq>{$freq}</changefreq>\n";
            $xml .= "        <priority>{$priority}</priority>\n";
            $xml .= "    </url>\n";
            
            fwrite($handle, $xml);
            $totalCount++;
        };

        // ---------------------------------------------------------
        // 2. 固定ページ
        // ---------------------------------------------------------
        $this->info('固定ページを追加中...');
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
            $writeUrl(
                route($page['route']),
                date('Y-m-d'),
                $page['freq'],
                $page['priority']
            );
        }

        // ---------------------------------------------------------
        // 3. 車種別ページ (検索結果)
        // ---------------------------------------------------------
        $this->info('車種別ページを追加中...');
        // chunkを使ってメモリ消費を抑えつつ処理
        BikeModel::select('name')->chunk(500, function ($models) use ($writeUrl) {
            foreach ($models as $model) {
                $writeUrl(
                    route('bikes.search', ['keyword' => $model->name]),
                    date('Y-m-d'),
                    'daily',
                    '0.8'
                );
            }
        });

        // ---------------------------------------------------------
        // 4. 車両詳細ページ
        // ---------------------------------------------------------
        $this->info('車両詳細ページを追加中...');
        $listingProcessCount = 0;
        
        Listing::where('is_sold_out', false)
            ->select('id', 'updated_at')
            ->orderBy('updated_at', 'desc')
            ->chunk(1000, function ($listings) use ($writeUrl, &$listingProcessCount) {
                foreach ($listings as $listing) {
                    $writeUrl(
                        route('bikes.show', $listing->id),
                        $listing->updated_at->format('Y-m-d'),
                        'weekly',
                        '0.6'
                    );
                    $listingProcessCount++;
                }
                $this->comment("  -> {$listingProcessCount} 件処理完了...");
            });

        // XMLフッター書き込み
        fwrite($handle, '</urlset>');
        fclose($handle);

        $duration = round(microtime(true) - $startTime, 2);
        $this->info("完了！");
        $this->info("- 出力先: {$path}");
        $this->info("- 合計URL数: {$totalCount}");
        $this->info("- 所要時間: {$duration}秒");
    }
}