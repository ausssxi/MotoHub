<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use Meilisearch\Client;

class SyncMeilisearchSettings extends Command
{
    protected $signature = 'meilisearch:sync-settings';
    protected $description = 'Meilisearchのフィルタ、ソート、同義語辞書、ランキングルールを同期します';

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

        // 1. 絞り込み(Filter)のルールを再定義
        $index->updateFilterableAttributes([
            'manufacturer_id', 'bike_model_id', 'category_id', 'prefecture', 
            'total_price', 'mileage', 'model_year', 'displacement', 
            'is_new', 'has_repair_history', 'is_sold_out', 'tag_slugs'
        ]);

        // 2. 並び替え(Sort)のルールを再定義
        // ★カスタムランキングに使用するため、favorite_count と view_count_today を追加
        $index->updateSortableAttributes([
            'created_at', 'total_price', 'mileage', 'model_year', 'bargain_score',
            'favorite_count', 'view_count_today'
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

        // 5. カスタムランキング（Ranking Rules）の最適化
        // キーワードの一致度(words〜exactness)の次に、魅力的な車両を優先して表示するルールを追加
        $index->updateRankingRules([
            'words',
            'typo',
            'proximity',
            'attribute',
            'sort',
            'exactness',
            'bargain_score:desc',    // お買い得度スコアが高いものを優先
            'favorite_count:desc',   // お気に入り数が多いものを優先
            'view_count_today:desc', // 今日よく見られているものを優先
            'created_at:desc',       // それでも同じ評価なら新着を優先
        ]);
        $this->info('✅ カスタムランキング（関連度アルゴリズム）を最適化しました');

        $this->info('🎉 すべてのアップデートが完了しました！');
    }
}