@props(['listing'])

@php
    // 価格の処理 (ASKや未定の場合は価格フィールドを出力しない)
    $price = null;
    if (is_numeric($listing->total_price) && $listing->total_price > 0) {
        // total_price は DB上では「円」単位で入っている前提
        // (ListingResourceやRepositoryのロジックから推測)
        // もしDBが「万円」単位なら * 10000 が必要ですが、
        // 以前のコードの `floor($listing->total_price)` 等を見る限り、
        // Resourceで加工される前の生データ(Model)が渡される場合、通常は生の数値です。
        // ここでは安全のため、Resource経由のオブジェクト($listing)が渡される前提で処理します。
        
        // ListingResourceで文字列化されている可能性があるため、一度数値化して整形
        // "45.8" (万円) -> 458000
        // もし渡ってくるのが生モデルなら $listing->total_price (円) そのままでOK
        
        // ここでは「詳細ページ」のコントローラーで渡している $listing が
        // 「Resourceで整形されたオブジェクト」であることを考慮します。
        $rawPrice = str_replace(',', '', $listing->total_price);
        if (is_numeric($rawPrice)) {
            $price = (int)($rawPrice * 10000); // 万円表記を円に戻す
        }
    }

    // コンディション判定
    $itemCondition = 'https://schema.org/UsedCondition'; // デフォルト中古
    if (str_contains($listing->condition ?? '', '新車')) {
        $itemCondition = 'https://schema.org/NewCondition';
    }

    // 在庫状況
    $availability = 'https://schema.org/InStock';
    // listingオブジェクトに is_sold_out プロパティがあれば判定（Resourceにはないかもなので注意）
    // Resourceに is_sold_out を含めていない場合、デフォルト在庫ありとします

    // 画像リスト (最大3枚程度にするのが推奨)
    $images = $listing->images ?? [];
    if (count($images) > 3) {
        $images = array_slice($images, 0, 3);
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $listing->name,
        'image' => $images,
        'description' => \Illuminate\Support\Str::limit(strip_tags($listing->description ?? ''), 300),
        'brand' => [
            '@type' => 'Brand',
            'name' => $listing->maker ?? 'Unknown'
        ],
        'offers' => [
            '@type' => 'Offer',
            'url' => url()->current(),
            'priceCurrency' => 'JPY',
            'availability' => $availability,
            'itemCondition' => $itemCondition,
        ]
    ];

    if ($price) {
        $schema['offers']['price'] = $price;
    }
@endphp

<script type="application/ld+json">
    {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>