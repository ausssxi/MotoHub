{{--
    構造化データ: Product + AggregateOffer
    車種詳細ページ（model_detail）専用
    ※ AggregateRating は撤去（自作レビューの spammy structured data リスク回避）。
    
    ※ 既存の product.blade.php は個別車両（show）用の Offer スキーマ。
    ※ こちらは車種全体の AggregateOffer で「価格帯」をGoogle検索に表示させる。
    
    使い方（model_detail.blade.php 内）:
    <x-jsonld.model-product :model="$model" :stats="$stats" :reviewStats="$reviewStats ?? null" />
--}}
@props(['model', 'stats', 'reviewStats' => null])

@php
    // statsが配列で来るのでオブジェクトに変換
    $s = is_array($stats) ? (object)$stats : $stats;
    $rs = is_array($reviewStats) ? (object)$reviewStats : $reviewStats;

    // 在庫数が0の場合はスキーマを出力しない
    if (($s->count ?? 0) <= 0) return;

    // 価格（円単位の _raw を使用）
    $lowPrice  = (int)($s->min_raw ?? 0);
    $highPrice = (int)($s->max_raw ?? 0);

    // 価格が不正なら出力しない
    if ($lowPrice <= 0 || $highPrice <= 0) return;

    // 説明文の組み立て
    $makerName = $model->manufacturer?->name ?? '';
    $catName   = $model->category?->name ?? 'バイク';
    $dispText  = $model->displacement ? "（{$model->displacement}cc）" : '';
    $desc = "{$makerName} {$model->name}{$dispText}の中古・新車バイク在庫情報。全{$s->count}台の価格・スペックをMotoHubで比較。";

    // 画像
    $image = $model->image_url ?? null;

    // スキーマ構築
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $model->name,
        'description' => $desc,
        'brand' => [
            '@type' => 'Brand',
            'name' => $makerName ?: '不明',
        ],
        'category' => $catName,
        'offers' => [
            '@type' => 'AggregateOffer',
            'priceCurrency' => 'JPY',
            'lowPrice' => $lowPrice,
            'highPrice' => $highPrice,
            'offerCount' => (int)($s->count ?? 0),
            'availability' => 'https://schema.org/InStock',
            'url' => route('bikes.search', ['bike_model_id' => $model->id]),
        ],
    ];

    if ($image) {
        $schema['image'] = $image;
    }

    if ($model->displacement) {
        $schema['additionalProperty'] = [[
            '@type' => 'PropertyValue',
            'name' => '排気量',
            'value' => (string)$model->displacement,
            'unitText' => 'cc',
        ]];
    }

    // ※ AggregateRating の構造化データは撤去（reviews が事実上自作＝spammy structured data /
    //   自己宣伝レビュー違反リスク。画面上の★表示はUXとして残し、schemaのみ外す）。
    //   $rs（reviewStats）は他の用途で渡されるが schema には載せない。
@endphp

<script type="application/ld+json">
    {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>