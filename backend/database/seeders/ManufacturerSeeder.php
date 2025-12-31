<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ManufacturerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $manufacturers = [
            // --- 日本 (Domestic) ---
            ['name' => 'ホンダ', 'name_kana' => 'ホンダ', 'country' => '日本'],
            ['name' => 'ヤマハ', 'name_kana' => 'ヤマハ', 'country' => '日本'],
            ['name' => 'スズキ', 'name_kana' => 'スズキ', 'country' => '日本'],
            ['name' => 'カワサキ', 'name_kana' => 'カワサキ', 'country' => '日本'],

            // --- アメリカ (USA) ---
            ['name' => 'ハーレーダビッドソン', 'name_kana' => 'ハーレーダビッドソン', 'country' => 'アメリカ'],
            ['name' => 'インディアン', 'name_kana' => 'インディアン', 'country' => 'アメリカ'],
            ['name' => 'ヴィクトリー', 'name_kana' => 'ヴィクトリー', 'country' => 'アメリカ'],
            ['name' => 'ビューエル', 'name_kana' => 'ビューエル', 'country' => 'アメリカ'],
            ['name' => 'クリーブランドサイクルワークス', 'name_kana' => 'クリーブランドサイクルワークス', 'country' => 'アメリカ'],
            ['name' => 'ボスホス', 'name_kana' => 'ボスホス', 'country' => 'アメリカ'],
            ['name' => 'タイタン', 'name_kana' => 'タイタン', 'country' => 'アメリカ'],

            // --- イギリス (UK) ---
            ['name' => 'トライアンフ', 'name_kana' => 'トライアンフ', 'country' => 'イギリス'],
            ['name' => 'ロイヤルエンフィールド', 'name_kana' => 'ロイヤルエンフィールド', 'country' => 'イギリス'],
            ['name' => 'ノートン', 'name_kana' => 'ノートン', 'country' => 'イギリス'],
            ['name' => 'BSA', 'name_kana' => 'ビーエスエー', 'country' => 'イギリス'],
            ['name' => 'メティッセ', 'name_kana' => 'メティッセ', 'country' => 'イギリス'],
            ['name' => 'CCM', 'name_kana' => 'シーシーエム', 'country' => 'イギリス'],

            // --- ドイツ (Germany) ---
            ['name' => 'BMW', 'name_kana' => 'ビーエムダブリュー', 'country' => 'ドイツ'],
            ['name' => 'ゾンダップ', 'name_kana' => 'ゾンダップ', 'country' => 'ドイツ'],

            // --- イタリア (Italy) ---
            ['name' => 'ドゥカティ', 'name_kana' => 'ドゥカティ', 'country' => 'イタリア'],
            ['name' => 'アプリリア', 'name_kana' => 'アプリリア', 'country' => 'イタリア'],
            ['name' => 'モトグッツィ', 'name_kana' => 'モトグッツィ', 'country' => 'イタリア'],
            ['name' => 'ベスパ', 'name_kana' => 'ベスパ', 'country' => 'イタリア'],
            ['name' => 'MVアグスタ', 'name_kana' => 'エムブイアグスタ', 'country' => 'イタリア'],
            ['name' => 'ビモータ', 'name_kana' => 'ビモータ', 'country' => 'イタリア'],
            ['name' => 'ベネリ', 'name_kana' => 'ベネリ', 'country' => 'イタリア'],
            ['name' => 'カジバ', 'name_kana' => 'カジバ', 'country' => 'イタリア'],
            ['name' => 'マグニ', 'name_kana' => 'マグニ', 'country' => 'イタリア'],
            ['name' => 'モンディアル', 'name_kana' => 'モンディアル', 'country' => 'イタリア'],
            ['name' => 'ピアジオ', 'name_kana' => 'ピアジオ', 'country' => 'イタリア'],
            ['name' => 'ジレラ', 'name_kana' => 'ジレラ', 'country' => 'イタリア'],
            ['name' => 'マラグーティ', 'name_kana' => 'マラグーティ', 'country' => 'イタリア'],
            ['name' => 'イタルジェット', 'name_kana' => 'イタルジェット', 'country' => 'イタリア'],
            ['name' => 'ファンティック', 'name_kana' => 'ファンティック', 'country' => 'イタリア'],
            ['name' => 'SWM', 'name_kana' => 'エスダブリューエム', 'country' => 'イタリア'],

            // --- オーストリア (Austria) ---
            ['name' => 'KTM', 'name_kana' => 'ケーティーエム', 'country' => 'オーストリア'],
            ['name' => 'ハスクバーナ', 'name_kana' => 'ハスクバーナ', 'country' => 'オーストリア'],

            // --- フランス (France) ---
            ['name' => 'プジョー', 'name_kana' => 'プジョー', 'country' => 'フランス'],
            ['name' => 'シェルコ', 'name_kana' => 'シェルコ', 'country' => 'フランス'],
            ['name' => 'スコルパ', 'name_kana' => 'スコルパ', 'country' => 'フランス'],

            // --- スペイン (Spain) ---
            ['name' => 'ガスガス', 'name_kana' => 'ガスガス', 'country' => 'スペイン'],
            ['name' => 'デルビ', 'name_kana' => 'デルビ', 'country' => 'スペイン'],
            ['name' => 'モンテッサ', 'name_kana' => 'モンテッサ', 'country' => 'スペイン'],
            ['name' => 'リエジュ', 'name_kana' => 'リエジュ', 'country' => 'スペイン'],

            // --- その他アジア・他 (Other) ---
            ['name' => 'キムコ', 'name_kana' => 'キムコ', 'country' => '台湾'],
            ['name' => 'SYM', 'name_kana' => 'エスワイエム', 'country' => '台湾'],
            ['name' => 'PGO', 'name_kana' => 'ピージーオー', 'country' => '台湾'],
            ['name' => 'ハートフォード', 'name_kana' => 'ハートフォード', 'country' => '台湾'],
            ['name' => 'ヒョースン', 'name_kana' => 'ヒョースン', 'country' => '韓国'],
            ['name' => 'CF MOTO', 'name_kana' => 'シーエフモト', 'country' => '中国'],
            ['name' => 'ブリクストン', 'name_kana' => 'ブリクストン', 'country' => 'オーストリア'],
            ['name' => 'ウラル', 'name_kana' => 'ウラル', 'country' => 'ロシア'],
        ];

        foreach ($manufacturers as $manufacturer) {
            DB::table('manufacturers')->updateOrInsert(
                ['name' => $manufacturer['name']],
                array_merge($manufacturer, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}