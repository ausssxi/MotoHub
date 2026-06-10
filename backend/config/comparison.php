<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| プログラマティック車種比較ページ（SeoCompare）生成ロジックの設定
|--------------------------------------------------------------------------
| comparison:generate-pairs コマンドが参照する品質ゲート。
| 全組合せ(NxN)は生成しない。薄い/重複/無関係ページはGoogleに弾かれるため、
| 「同クラス・実在庫十分・クリーンなスペック」の AND を満たすペアだけ生成する。
| config/diagnosis.php と同じ data-as-config パターン。
*/

return [

    // 各車種の active 在庫（excludeBulkSold 後）の下限。両車種が満たすペアのみ。
    'min_stock_each' => 3,

    // 初回バッチの生成上限（質優先・100〜300）。ランクの当落を見てから拡張する。
    'initial_batch_cap' => 200,

    // 各クラスから総当りする「在庫上位N車種」。NxN爆発を防ぐ要。
    'top_models_per_class' => 12,

    // 全て非NULLでなければスペック欠損として除外（クリーンなスペック保証）。
    // bike_models のカラム名。
    'required_specs' => ['displacement', 'weight', 'seat_height', 'max_power'],

    // ゴミモデル（「他車種」「海外メーカーその他」等）の bike_model_id を除外。
    'excluded_model_ids' => [],

    // displacement(cc) → クラス境界。config/bike.php licenses と整合。
    // 「同クラス」= 同一 cc-band AND 同一 category_id。
    'cc_bands' => [
        ['slug' => '50',      'min' => 0,   'max' => 50],
        ['slug' => '125',     'min' => 51,  'max' => 125],
        ['slug' => '250',     'min' => 126, 'max' => 250],
        ['slug' => '400',     'min' => 251, 'max' => 400],
        ['slug' => '750',     'min' => 401, 'max' => 750],
        ['slug' => 'over750', 'min' => 751, 'max' => 100000],
    ],

    // クラス跨ぎでも「実際に比較検討される」実ライバルだけ手動許可（例: レブル250×GB350）。
    // 各要素は2モデルの [id, id] または [slug, slug]。品質ゲート(在庫/スペック)は手動ペアにも適用。
    'manual_pairs' => [
        // ['rebel-250', 'gb350'],
    ],
];
