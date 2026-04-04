@props(['parking' => null, 'prefecture' => null, 'city' => null, 'currentName' => null])

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

    // エリアインデックス
    $pref = $prefecture ?? ($parking->prefecture ?? null);
    $cityName = $city ?? ($parking->city ?? null);

    if ($pref) {
        $itemList[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => 'エリアから探す',
            'item' => route('parking.area.index')
        ];

        // 都道府県
        $itemList[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $pref,
            'item' => route('parking.area.prefecture', $pref)
        ];
    }

    // 市区町村
    if ($pref && $cityName) {
        $itemList[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $cityName,
            'item' => route('parking.area.city', [$pref, $cityName])
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
