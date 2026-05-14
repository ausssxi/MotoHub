{{--
    構造化データ: LocalBusiness
    店舗詳細ページ（shops/show）専用

    使い方:
    <x-jsonld.local-business :shop="$shop" :stockCount="$pagination['total'] ?? 0" />
--}}
@props(['shop', 'stockCount' => 0, 'description' => ''])

@php
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'MotorcycleDealer',
        'name' => $shop->name,
        'description' => $description ?: (($shop->prefecture ? $shop->prefecture . 'にある' : '') . '「' . $shop->name . '」のバイク在庫情報。' . ($stockCount > 0 ? "現在{$stockCount}台販売中。" : '')),
        'address' => [
            '@type' => 'PostalAddress',
            'addressRegion' => $shop->prefecture ?? '',
            'streetAddress' => $shop->address ?? '',
            'addressCountry' => 'JP',
        ],
    ];

    if ($shop->phone) {
        $schema['telephone'] = $shop->phone;
    }

    if ($shop->website_url) {
        $schema['url'] = $shop->website_url;
    }

    if ($shop->business_hours) {
        $schema['openingHours'] = $shop->business_hours;
    }

    if ($shop->latitude && $shop->longitude) {
        $schema['geo'] = [
            '@type' => 'GeoCoordinates',
            'latitude' => (float)$shop->latitude,
            'longitude' => (float)$shop->longitude,
        ];
    }

    $imageUrl = $shop->display_image_url ?? null;
    if ($imageUrl) {
        $schema['image'] = $imageUrl;
    }

    $schema['priceRange'] = '¥';

    if ($shop->rating && $shop->rating > 0) {
        $schema['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => number_format((float)$shop->rating, 1),
            'bestRating' => '5',
            'worstRating' => '1',
        ];
    }
@endphp

<script type="application/ld+json">
    {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
