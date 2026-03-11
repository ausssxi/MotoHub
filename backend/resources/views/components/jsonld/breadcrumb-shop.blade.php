@props(['shop'])

@php
    $itemList = [];
    $position = 1;

    // 1. Home
    $itemList[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => 'トップ',
        'item' => url('/')
    ];

    // 2. ショップを探す
    $itemList[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => 'ショップを探す',
        'item' => route('bikes.prefectures')
    ];

    // 3. 都道府県（あれば）
    if (!empty($shop->prefecture)) {
        $itemList[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $shop->prefecture,
            'item' => route('bikes.search', ['prefecture' => $shop->prefecture])
        ];
    }

    // 4. 店舗名
    $itemList[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => $shop->name
    ];

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $itemList
    ];
@endphp

<script type="application/ld+json">
    {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
