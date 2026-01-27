<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. 現在 bike_models で使われているカテゴリ名を重複なしで取得
        $existingCategories = DB::table('bike_models')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');

        foreach ($existingCategories as $catName) {
            // カテゴリ名の揺らぎを吸収するためのマッピング（任意）
            // 必要なければ自動生成の slug だけでもOKですが、URLを綺麗にするために定義推奨
            $slug = $this->getSlugFor($catName);

            // 2. categories テーブルに保存（なければ作成、あればID取得）
            $categoryId = DB::table('categories')->insertGetId([
                'name' => $catName,
                'slug' => $slug,
                'sort_order' => 100, // とりあえず後ろの方に
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. bike_models テーブルの category_id を更新して紐付け
            DB::table('bike_models')
                ->where('category', $catName)
                ->update(['category_id' => $categoryId]);
            
            $this->command->info("Migrated Category: {$catName} -> ID: {$categoryId}");
        }
    }

    /**
     * 日本語カテゴリ名から英語スラグへの変換マップ
     */
    private function getSlugFor(string $name): string
    {
        // よくあるものは手動でマッピング
        $map = [
            'ネイキッド' => 'naked',
            'スーパースポーツ' => 'sports',
            'アメリカン' => 'american',
            'スクーター' => 'scooter',
            'オフロード' => 'offroad',
            'ツアラー' => 'tourer',
            'クラシック' => 'classic',
            'ミニバイク' => 'minibike',
            '電動バイク' => 'electric',
            'ビジネス' => 'business',
            'その他' => 'others',
        ];

        // マップにあればそれを使う
        if (isset($map[$name])) {
            return $map[$name];
        }
        
        // 部分一致（"原付スクーター" -> "scooter" など）
        if (str_contains($name, 'スクーター')) return 'scooter';
        if (str_contains($name, 'アメリカン')) return 'american';

        // マップになければ、UUIDなどを使って一旦ユニークにする（後で手動修正）
        return 'cat-' . Str::random(8);
    }
}