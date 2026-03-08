{{--
    構造化データ: ParkingFacility
    駐車場詳細ページ（parking/show）専用

    使い方:
    <x-jsonld.parking :parking="$parking" />
--}}
@props(['parking'])

@php
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'ParkingFacility',
        'name' => $parking->name,
        'description' => ($parking->prefecture ? $parking->prefecture . 'にある' : '') . '「' . $parking->name . '」のバイク駐車場情報。' . $parking->getPriceDisplay() . '。',
        'address' => [
            '@type' => 'PostalAddress',
            'addressRegion' => $parking->prefecture ?? '',
            'streetAddress' => $parking->address ?? '',
            'addressCountry' => 'JP',
        ],
    ];

    if ($parking->latitude && $parking->longitude) {
        $schema['geo'] = [
            '@type' => 'GeoCoordinates',
            'latitude' => (float) $parking->latitude,
            'longitude' => (float) $parking->longitude,
        ];
    }

    if ($parking->available_24h) {
        $schema['openingHours'] = 'Mo-Su 00:00-23:59';
    }

    if ($parking->avg_rating > 0) {
        $schema['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => number_format((float) $parking->avg_rating, 1),
            'reviewCount' => $parking->reviews_count,
            'bestRating' => '5',
            'worstRating' => '1',
        ];
    }

    if (!$parking->is_free) {
        $priceSpec = [];
        if ($parking->price_per_hour) {
            $priceSpec[] = [
                '@type' => 'UnitPriceSpecification',
                'price' => $parking->price_per_hour,
                'priceCurrency' => 'JPY',
                'unitText' => '時間',
            ];
        }
        if (!empty($priceSpec)) {
            $schema['priceSpecification'] = $priceSpec;
        }
    }
@endphp

<script type="application/ld+json">
    {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
