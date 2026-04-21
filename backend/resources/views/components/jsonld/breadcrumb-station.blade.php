@props(['station'])

@php
    $itemList = [
        [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'ホーム',
            'item' => url('/')
        ],
        [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => 'バイク駐車場マップ',
            'item' => route('parking.index')
        ],
        [
            '@type' => 'ListItem',
            'position' => 3,
            'name' => '駅から探す',
            'item' => route('parking.station.index')
        ],
        [
            '@type' => 'ListItem',
            'position' => 4,
            'name' => $station->name . '駅'
        ],
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
