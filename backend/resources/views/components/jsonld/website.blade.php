{{--
    構造化データ: Organization + WebSite
    配置場所: layout.blade.php の </head> 直前

    ※ JSON-LD の配列は下の PHP ブロックで組み立て、script 内では変数を json_encode するだけにしている。
      こうしないと「@ 始まりの JSON-LD キー」が Blade ディレクティブとして解釈され、構造化データが壊れる。
      具体的な理由は下の PHP ブロック内コメントを参照。
--}}
@php
    // 出力側で配列リテラルを直接 json_encode すると、生出力構文 {!! ... !!} の式の内側も
    // Blade がディレクティブを走査するため、'@context' が Laravel の @context ディレクティブとして
    // コンパイルされ、出力JSONのキーがコンパイル済みPHPに化ける
    //（本番トップの Organization / WebSite が解析不能になっていた）。
    // 対策として、ここで変数に組み立ててから、出力側は変数を渡すだけにする。
    $ldWebsite = [
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
                    'https://note.com/motohub',
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
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($ldWebsite, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
