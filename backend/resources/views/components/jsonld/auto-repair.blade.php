{{--
    構造化データ: AutoRepair（バイク整備・修理店）
    店舗詳細ページ（shops/show）で shop_type=repair_only のとき専用。

    基本型は schema.org 標準の AutoRepair。MotoHubはバイク専門のため
    additionalType / knowsAbout でバイク整備であることを明示する。

    使い方:
    <x-jsonld.auto-repair :shop="$shop" :description="$description" />
--}}
@props(['shop', 'description' => ''])

@php
    $tags = $shop->service_tags ?? [];

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'AutoRepair',
        // バイク専門であることのシグナル（schema.org標準外の特化型を補足）
        'additionalType' => 'MotorcycleRepairShop',
        'knowsAbout' => array_values(array_filter(array_unique(array_merge(
            ['バイク整備', 'バイク修理', '二輪車整備'],
            is_array($tags) ? $tags : []
        )))),
        'name' => $shop->name,
        'description' => $description ?: (($shop->prefecture ? $shop->prefecture : '') . ($shop->city ?? '') . 'のバイク整備・修理店「' . $shop->name . '」。'),
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
            'latitude' => (float) $shop->latitude,
            'longitude' => (float) $shop->longitude,
        ];
    }

    $imageUrl = $shop->display_image_url ?? null;
    if ($imageUrl) {
        $schema['image'] = $imageUrl;
    }

    $schema['priceRange'] = '¥';

    // 対応サービスを OfferCatalog として明示（車検受付/タイヤ交換 等）
    if (is_array($tags) && count($tags) > 0) {
        $schema['makesOffer'] = array_map(fn ($t) => [
            '@type' => 'Offer',
            'itemOffered' => ['@type' => 'Service', 'name' => $t],
        ], array_values($tags));
    }

    if ($shop->rating && $shop->rating > 0) {
        $schema['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => number_format((float) $shop->rating, 1),
            'bestRating' => '5',
            'worstRating' => '1',
        ];
    }
@endphp

<script type="application/ld+json">
    {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
