<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use App\Services\Bike\TagExtractionService;

class ExtractTagsCommand extends Command
{
    protected $signature = 'tags:extract';
    protected $description = '既存の全車両データからタイトルと説明文をもとにタグを抽出し、DBに保存します';

    public function handle(TagExtractionService $tagService)
    {
        $this->info('タグの抽出処理を開始します...');

        $count = 0;
        
        // chunkを使うことで、12万件あってもメモリ不足にならずに少しずつ処理できます
        Listing::chunk(500, function ($listings) use ($tagService, &$count) {
            foreach ($listings as $listing) {
                $tagService->extractAndSyncTags($listing);
                $count++;
            }
            $this->info("{$count} 件の車両のタグ抽出が完了しました。");
        });

        $this->info('すべての車両のタグ抽出が完了しました！');
    }
}