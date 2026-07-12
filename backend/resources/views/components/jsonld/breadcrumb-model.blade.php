@props(['model'])

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

    // 2. 車種一覧
    $itemList[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => '車種一覧',
        'item' => route('bikes.models')
    ];

    // 3. メーカー（クリーンなメーカーハブへ集約。slug無しは従来の検索URLにフォールバック）
    if ($model->manufacturer) {
        $itemList[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $model->manufacturer->name,
            'item' => $model->manufacturer->slug
                ? route('bikes.manufacturer_hub', ['makerSlug' => $model->manufacturer->slug])
                : route('bikes.search', ['manufacturer_id' => $model->manufacturer_id])
        ];
    }

    // 4. 車種名（最後はURL省略がGoogle推奨）
    $itemList[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => $model->name
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
