@props(['prefecture' => null, 'city' => null])

@php
    $itemList = [];
    $position = 1;

    $itemList[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => 'ホーム',
        'item' => url('/'),
    ];

    $itemList[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => 'バイク整備・修理店',
        'item' => route('shops.repair.index'),
    ];

    if ($prefecture) {
        if ($city) {
            $itemList[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $prefecture,
                'item' => route('shops.repair.prefecture', $prefecture),
            ];
            $itemList[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $city,
            ];
        } else {
            $itemList[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $prefecture,
            ];
        }
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $itemList,
    ];
@endphp

<script type="application/ld+json">
    {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
