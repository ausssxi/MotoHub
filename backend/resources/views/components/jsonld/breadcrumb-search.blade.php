@props(['filters' => [], 'pageTitle' => ''])

@php
    $itemList = [];
    $position = 1;

    $itemList[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => 'HOME',
        'item' => route('bikes.index')
    ];

    $itemList[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => $pageTitle ?: '検索結果',
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
