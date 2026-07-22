<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 車種×作業（型番）ページ設定
|--------------------------------------------------------------------------
| 記事リンク・診断スラッグなどページ側のメタ。適合の「事実」はDB（CSV由来）にのみ持つ。
| task を増やすときはここと routes/web.php の whereIn を拡張する。
*/

return [
    'tasks' => [
        'battery' => [
            'label' => 'バッテリー',
            // 既存の交換手順記事URL（内田が確定）。空なら手順セクションの記事リンクを出さない。
            'article_url' => env('FITMENTS_BATTERY_ARTICLE_URL', ''),
            'trouble_symptom' => 'battery',
        ],
        'plug' => [
            'label' => 'プラグ',
            'article_url' => env('FITMENTS_PLUG_ARTICLE_URL', ''), // 空→手順の記事リンク非表示
            'trouble_symptom' => 'engine-wont-start',              // プラグ原因はこの症状から到達
        ],
        'oil' => [
            'label' => 'エンジンオイル',
            'article_url' => env('FITMENTS_OIL_ARTICLE_URL', '/blog/gentsuki-oil-change'), // 既存記事が受け皿
            'trouble_symptom' => null,                             // 予防メンテのため診断接続なし
        ],
    ],

    /*
    | エンジンオイルの一般目安（verified な車種別データが無い車種のフォールバック）。
    | ★数字は創作せず「一般的な整備目安」の幅で提示し、必ず注記＋出典＋最終確認日を出す（断定しない）。
    |   オイル量は排気量だけでは決まらない（気筒数・湿式/乾式）ため幅を広めに。交換時期は各社取説で広く共通のレンジ。
    | keyword は用品アフィリ（ProductSearchService）用。粘度非依存なので排気量帯で数種類に収束＝キャッシュが効く。
    */
    'oil_general' => [
        'source_label' => 'エンジンオイルの一般的な交換目安（整備一般基準）',
        'checked_at' => '2026-07',
        'note' => '※一般的な目安です。正確なオイル量・交換時期は車種・年式により異なるため、取扱説明書や整備解説でご確認ください。',
        'bands' => [
            ['max' => 125,  'label' => '〜125cc',    'capacity' => '約0.8〜1.2L', 'interval' => '3,000〜5,000km または半年〜1年', 'keyword' => 'バイク エンジンオイル 原付 125cc'],
            ['max' => 250,  'label' => '126〜250cc', 'capacity' => '約1.0〜2.0L', 'interval' => '3,000〜5,000km または半年〜1年', 'keyword' => 'バイク エンジンオイル 250cc'],
            ['max' => 400,  'label' => '251〜400cc', 'capacity' => '約2.0〜3.0L', 'interval' => '3,000〜5,000km または半年〜1年', 'keyword' => 'バイク エンジンオイル 400cc'],
            ['max' => null, 'label' => '401cc〜',    'capacity' => '約3.0〜4.0L', 'interval' => '3,000〜6,000km または1年',       'keyword' => 'バイク エンジンオイル 大型'],
        ],
    ],
];
