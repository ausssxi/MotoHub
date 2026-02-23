<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use Meilisearch\Client;

class SyncMeilisearchSettings extends Command
{
    protected $signature = 'meilisearch:sync-settings';
    protected $description = 'Meilisearchのフィルタ、ソート、同義語辞書を同期します';

    public function handle()
    {
        $this->info('🧠 Meilisearch の脳をアップデートしています...');

        $host = env('MEILISEARCH_HOST', 'http://localhost:7700');
        $key = env('MEILISEARCH_KEY');

        if (!$key) {
            $this->error('MEILISEARCH_KEY が .env に設定されていません。');
            return;
        }

        $client = new Client($host, $key);
        $indexName = (new Listing())->searchableAs();
        $index = $client->index($indexName);

        // 1. 絞り込み(Filter)のルールを再定義（エラー防止）
        $index->updateFilterableAttributes([
            'manufacturer_id', 'bike_model_id', 'category_id', 'prefecture', 
            'total_price', 'mileage', 'model_year', 'displacement', 
            'is_new', 'has_repair_history', 'is_sold_out', 'tag_slugs'
        ]);

        // 2. 並び替え(Sort)のルールを再定義
        $index->updateSortableAttributes([
            'created_at', 'total_price', 'mileage', 'model_year', 'bargain_score'
        ]);

        // 3. 検索対象カラムの優先順位（タイトルを最優先にする）
        $index->updateSearchableAttributes([
            'title',
            'prefecture',
            'tag_slugs'
        ]);

        // 4. 同義語（Synonyms）辞書の登録
        $synonyms = config('bike_synonyms', []);
        if (!empty($synonyms)) {
            $index->updateSynonyms($synonyms);
            $this->info('✅ 同義語(Synonyms)辞書を学習させました（' . count($synonyms) . 'パターンの揺らぎに対応）');
        }

        $this->info('🎉 すべてのアップデートが完了しました！');
    }
}