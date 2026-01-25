<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use App\Models\BikeModel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL; // 追加

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';
    protected $description = 'サイトマップ(sitemap.xml)を生成してpublicディレクトリに保存します';

    public function handle(): void
    {
        // 1. URLの強制設定 (localhostになるのを防ぐ)
        // .envの値を取得、もし取れなければ直接指定
        $appUrl = config('app.url');
        if (empty($appUrl) || str_contains($appUrl, 'localhost')) {
            $appUrl = 'https://motohub.jp';
        }

        URL::forceRootUrl($appUrl);
        URL::forceScheme('https');

        $this->info("サイトマップ生成開始 (Base URL: {$appUrl})");
        $startTime = microtime(true);

        $urls = [];

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
            $urls[] = [
                'loc' => route($page['route']),
                'lastmod' => date('Y-m-d'),
                'changefreq' => $page['freq'],
                'priority' => $page['priority'],
            ];
        }

        // ---------------------------------------------------------
        // 3. 車種別ページ (検索結果)
        // ---------------------------------------------------------
        $this->info('車種別ページを追加中...');
        BikeModel::chunk(500, function ($models) use (&$urls) {
            foreach ($models as $model) {
                // ここで route() を使うと forceRootUrl の効果で https://motohub.jp になります
                $urls[] = [
                    'loc' => route('bikes.search', ['keyword' => $model->name]),
                    'lastmod' => date('Y-m-d'),
                    'changefreq' => 'daily',
                    'priority' => '0.8',
                ];
            }
        });

        // ---------------------------------------------------------
        // 4. 車両詳細ページ (一番最後に追加されます)
        // ---------------------------------------------------------
        $this->info('車両詳細ページを追加中...');
        $listingCount = 0;
        
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
        // 5. 書き出し
        // ---------------------------------------------------------
        $content = view('sitemap', ['urls' => $urls])->render();
        $path = public_path('sitemap.xml');
        File::put($path, $content);

        $duration = round(microtime(true) - $startTime, 2);
        $this->info("完了！");
        $this->info("- 出力先: {$path}");
        $this->info("- 合計URL数: " . count($urls));
        $this->info("- 所要時間: {$duration}秒");
    }
}