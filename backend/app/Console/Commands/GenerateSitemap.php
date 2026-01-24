<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use App\Models\BikeModel;
use Illuminate\Support\Facades\File;

class GenerateSitemap extends Command
{
    /**
     * コンソールコマンドの名前
     */
    protected $signature = 'sitemap:generate';

    /**
     * コマンドの説明
     */
    protected $description = 'サイトマップ(sitemap.xml)を生成してpublicディレクトリに保存します';

    /**
     * コマンドの実行処理
     */
    public function handle(): void
    {
        $this->info('サイトマップの生成を開始します...');
        $startTime = microtime(true);

        $urls = [];

        // ---------------------------------------------------------
        // 1. 固定ページの追加
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
            $urls[] = [
                'loc' => route($page['route']),
                'lastmod' => date('Y-m-d'),
                'changefreq' => $page['freq'],
                'priority' => $page['priority'],
            ];
        }

        // ---------------------------------------------------------
        // 2. 車種ごとの検索結果ページ (SEO対策)
        // 例: /bikes/search?keyword=CB400SF
        // ---------------------------------------------------------
        $this->info('車種別ページを追加中...');
        BikeModel::chunk(500, function ($models) use (&$urls) {
            foreach ($models as $model) {
                $urls[] = [
                    'loc' => route('bikes.search', ['keyword' => $model->name]),
                    'lastmod' => date('Y-m-d'),
                    'changefreq' => 'daily', // 在庫は日々変わるのでdaily
                    'priority' => '0.8',
                ];
            }
        });

        // ---------------------------------------------------------
        // 3. 車両詳細ページ (最重要)
        // 例: /bikes/12345
        // ---------------------------------------------------------
        $this->info('車両詳細ページを追加中...');
        $listingCount = 0;
        
        // メモリ節約のため必要なカラムだけ取得して分割処理
        Listing::where('is_sold_out', false)
            ->select('id', 'updated_at')
            ->orderBy('updated_at', 'desc')
            ->chunk(1000, function ($listings) use (&$urls, &$listingCount) {
                foreach ($listings as $listing) {
                    $urls[] = [
                        'loc' => route('bikes.show', $listing->id),
                        'lastmod' => $listing->updated_at->format('Y-m-d'),
                        'changefreq' => 'weekly',
                        'priority' => '0.6',
                    ];
                    $listingCount++;
                }
                $this->comment("  -> {$listingCount} 件処理完了...");
            });

        // ---------------------------------------------------------
        // 4. XML書き出し
        // ---------------------------------------------------------
        $content = view('sitemap', ['urls' => $urls])->render();
        
        $path = public_path('sitemap.xml');
        File::put($path, $content);

        $duration = round(microtime(true) - $startTime, 2);
        $this->info("完了！");
        $this->info("- 出力先: {$path}");
        $this->info("- URL数: " . count($urls));
        $this->info("- 所要時間: {$duration}秒");
    }
}