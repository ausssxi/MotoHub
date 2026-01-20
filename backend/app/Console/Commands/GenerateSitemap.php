<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use App\Models\BikeModel;
use Illuminate\Support\Facades\Log;

/**
 * Google 検索エンジン向けのサイトマップ (sitemap.xml) を生成するコマンド
 */
final class GenerateSitemap extends Command
{
    /**
     * コマンド名（php artisan sitemap:generate で実行可能）
     */
    protected $signature = 'sitemap:generate';

    /**
     * コマンドの説明
     */
    protected $description = 'Generate the sitemap.xml for SEO and AdSense purposes';

    /**
     * 生成ロジックの実行
     */
    public function handle(): int
    {
        $this->info('Starting sitemap generation...');

        try {
            // 1. サイトマップオブジェクトの作成
            $sitemap = Sitemap::create();

            // 2. 主要な固定ページの追加
            // トップページ
            $sitemap->add(Url::create('/')
                ->setPriority(1.0)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY));
            
            // 車種一覧ページ
            $sitemap->add(Url::create('/models')
                ->setPriority(0.9)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY));
            
            // ✨ お気に入りページ
            $sitemap->add(Url::create('/wishlist')
                ->setPriority(0.5)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY));

            // 固定ページ（AdSense審査に重要）
            $sitemap->add(Url::create(route('pages.about'))
                ->setPriority(0.3)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY));

            $sitemap->add(Url::create(route('pages.contact'))
                ->setPriority(0.3)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY));

            $sitemap->add(Url::create(route('pages.privacy-policy'))
                ->setPriority(0.1)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY));

            $sitemap->add(Url::create(route('pages.terms'))
                ->setPriority(0.1)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY));

            // 3. 車種ごとの「検索結果ページ」を動的に追加
            // これにより Google が個別の車種名でインデックスしやすくなります
            $this->info('Adding bike models to sitemap...');
            
            // 全車種を取得（数が多い場合は chunk を使うのが理想的です）
            BikeModel::all()->each(function (BikeModel $bike) use ($sitemap) {
                $sitemap->add(
                    Url::create(route('bikes.search', ['keyword' => $bike->name]))
                        ->setPriority(0.8)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                );
            });

            // 4. ファイルへ書き出し (public/sitemap.xml)
            $path = public_path('sitemap.xml');
            $sitemap->writeToFile($path);

            $this->info("Successfully generated sitemap at: {$path}");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to generate sitemap: " . $e->getMessage());
            Log::error("Sitemap Generation Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}