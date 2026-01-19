<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use App\Models\BikeModel;

class GenerateSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sitemap:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sitemap = Sitemap::create()
        ->add(Url::create('/')) // トップページ
        ->add(Url::create('/models')); // 車種一覧ページ

    // 各車種の「検索結果ページ」をサイトマップに追加（SEO対策）
    // これにより Google に各バイクの存在を知らせることができます
    BikeModel::all()->each(function (BikeModel $bike) use ($sitemap) {
        $sitemap->add(
            Url::create(route('bikes.search', ['keyword' => $bike->name]))
                ->setPriority(0.8) // 優先度を少し高めに設定
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
        );
    });

        $sitemap->writeToFile(public_path('sitemap.xml'));
    }
}
