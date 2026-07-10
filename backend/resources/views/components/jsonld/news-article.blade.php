{{--
    構造化データ: NewsArticle（オリジナルニュース記事専用）
    使い方（news/show.blade.php 内）: <x-jsonld.news-article :news="$newsItem" />

    ⚠️ オリジナル記事（source='MotoHub'）のみ出力。RSS 転載記事には出さない
    （他社記事に自社のニュース構造化データを付けるのは不適切）。コンポーネント内で自己ガード。
--}}
@props(['news'])

@php
    // RSS 転載記事には出力しない（source で分岐）
    $emit = ($news->source ?? null) === \App\Models\BikeNews::SOURCE_ORIGINAL;
@endphp

@if($emit)
@php
    $url = route('news.show', $news->id);
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'NewsArticle',
        // headline は Google 推奨の110字以内に収める
        'headline' => \Illuminate\Support\Str::limit((string) $news->title, 110, ''),
        'datePublished' => optional($news->published_at)->toAtomString(),
        'dateModified' => optional($news->updated_at ?? $news->published_at)->toAtomString(),
        'author' => ['@type' => 'Organization', 'name' => 'MotoHub'],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'MotoHub',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => asset('images/twitter_card.png'), // 既存 Organization と同一ロゴ
                'width' => 512,
                'height' => 512,
            ],
        ],
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
    ];
    if (! empty($news->thumbnail_url)) {
        $data['image'] = [$news->thumbnail_url]; // 絶対URL・配列
    }
@endphp
<script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
