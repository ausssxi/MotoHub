@props(['items' => []])

{{--
    汎用パンくず JSON-LD（BreadcrumbList）。
    :items に [['name' => '表示名', 'url' => '絶対URL'], ...] を渡す。
    url を省略した項目（＝現在ページ）は item を出さない（Google推奨）。
--}}
@php
    $itemList = [];
    $position = 1;
    foreach ($items as $it) {
        if (empty($it['name'])) {
            continue;
        }
        $entry = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $it['name'],
        ];
        if (! empty($it['url'])) {
            $entry['item'] = $it['url'];
        }
        $itemList[] = $entry;
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
