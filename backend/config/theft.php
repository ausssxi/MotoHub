<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 盗難コンテンツ（/theft ハブ・県別ページ盗難ブロック）のメタ設定
|--------------------------------------------------------------------------
| ★統計値そのものは database/data/theft_stats.json（一次情報から整形）に保持。ここはメタ＋アフィリのみ。
| ★出典・対象年・最終確認日を必ず表示側に出す。数字は創作しない（統計はそのまま）。
| データ取得・整形・差し替え手順は docs/theft-data.md 参照。
*/

return [
    'slug' => 'theft',

    // 出典表示（第9表 街頭犯罪等 都道府県別・オートバイ盗内訳）。
    'source_label' => "警察庁『犯罪統計』第9表 街頭犯罪等 都道府県別（e-Stat）",
    'source_url' => 'https://www.e-stat.go.jp/stat-search/files?tstat=000001103615',

    // 対象年（最新確定年）と最終確認（YYYY-MM）。年次差し替え時に更新。
    'data_year' => 2025,
    'checked_at' => '2026-07',

    // 表示指標。初版は実数件数（count）。人口10万人あたり(per100k)はPhase2。
    'metric' => 'count',

    // 静的データの配置。
    'data_path' => 'database/data/theft_stats.json',

    // アフィリエイト（ZuttoRide盗難保険・自社直アフィリ）。★承認後に発行URLを env に入れる。
    // url 未設定の間は CTA 自体を非表示（偽ボタンを置かない）。insurance.affiliate と同型。
    'affiliate' => [
        'url' => env('ZUTTORIDE_AFFILIATE_URL', ''),
        'provider' => env('ZUTTORIDE_AFFILIATE_PROVIDER', 'ZuttoRide'),
        // 事実ベースの文言のみ（誇大・不安を過度に煽らない）。
        'headline' => 'バイクの盗難保険を検討する',
        'sub' => '盗難・いたずら等に備える専用保険。補償内容・保険料は条件で変わるため、公式サイトでご確認ください。',
    ],
];
