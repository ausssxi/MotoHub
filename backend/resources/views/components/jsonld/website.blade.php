{{--
    構造化データ: Organization + WebSite
    配置場所: layout.blade.php の </head> 直前
    
    ※Bladeの誤作動を防ぐため、PHP配列からJSONに変換して出力しています。
--}}
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            '@id' => url('/') . '/#organization',
            'name' => 'MotoHub',
            'url' => url('/'),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => asset('images/twitter_card.png'),
                'width' => 512,
                'height' => 512
            ],
            'description' => '日本最大級のバイク検索・比較プラットフォーム。GooBike、BDS、Webikeから一括検索！',
            'founder' => [
                '@type' => 'Person',
                'name' => '内田厚'
            ],
            'sameAs' => [
                'https://x.com/motohub_jp',
                'https://www.instagram.com/motohub.jp',
                'https://www.youtube.com/@motohub_jp',
                'https://www.tiktok.com/@motohub10',
                'https://note.com/motuhub',
                'https://qiita.com/ausssxi0'
            ]
        ],
        [
            '@type' => 'WebSite',
            '@id' => url('/') . '/#website',
            'url' => url('/'),
            'name' => 'MotoHub',
            'description' => '日本最大級のバイク検索・比較プラットフォーム',
            'publisher' => [
                '@id' => url('/') . '/#organization'
            ],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => url('/bikes/search') . '?keyword={search_term_string}'
                ],
                'query-input' => 'required name=search_term_string'
            ]
        ]
    ]
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>