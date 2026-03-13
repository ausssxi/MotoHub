@props(['parking' => null, 'currentName' => null])

@php
    $itemList = [];
    $position = 1;

    $itemList[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => 'ホーム',
        'item' => url('/')
    ];

    $itemList[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => 'バイク駐車場マップ',
        'item' => route('parking.index')
    ];

    // 都道府県（show で prefecture がある場合）
    if ($parking && !empty($parking->prefecture)) {
        $itemList[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $parking->prefecture,
            'item' => route('parking.area', $parking->prefecture)
        ];
    }

    // 最後の階層（URL省略がGoogle推奨）
    $lastName = $parking ? $parking->name : ($currentName ?? '');
    if ($lastName) {
        $itemList[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $lastName
        ];
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $itemList
    ];
@endphp

<script type="application/ld+json">
    {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
