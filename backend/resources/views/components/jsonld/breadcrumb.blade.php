@props(['listing'])

@php
    $itemList = [];
    $position = 1;

    // 1. Home
    $itemList[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => 'HOME',
        'item' => route('bikes.index')
    ];

    // 2. メーカー
    if (!empty($listing->manufacturer_id)) {
        $itemList[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $listing->maker,
            'item' => route('bikes.search', ['manufacturer_id' => $listing->manufacturer_id])
        ];
    }

    // 3. 車種
    if (!empty($listing->bike_model_id) && !empty($listing->bike_model_name)) {
        $itemList[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $listing->bike_model_name,
            'item' => route('bikes.search', [
                'manufacturer_id' => $listing->manufacturer_id,
                'bike_model_id' => $listing->bike_model_id
            ])
        ];
    }

    // 4. 現在のページ（車両名）
    $itemList[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => $listing->name,
        // 最後のアイテムには item (URL) を含めないのがGoogleの推奨ですが、
        // 含めても問題はありません。ここではカノニカルURLとして含めます。
        'item' => url()->current()
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