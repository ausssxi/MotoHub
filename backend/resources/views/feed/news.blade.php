@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>MotoHub オリジナルニュース</title>
        <link>{{ url('/news') }}</link>
        <description>MotoHubのオリジナル記事 - バイク中古相場分析・市場レポート</description>
        <language>ja</language>
        <lastBuildDate>{{ $items->first()?->published_at?->toRfc2822String() ?? now()->toRfc2822String() }}</lastBuildDate>
        <atom:link href="{{ url('/feed/news') }}" rel="self" type="application/rss+xml" />

        @foreach($items as $item)
        <item>
            <title>{{ e($item->title) }}</title>
            <link>{{ route('news.show', $item->id) }}</link>
            <description>{{ e(mb_substr(strip_tags($item->content ?? ''), 0, 200)) }}</description>
            <pubDate>{{ $item->published_at->toRfc2822String() }}</pubDate>
            <guid isPermaLink="true">{{ route('news.show', $item->id) }}</guid>
            <author>MotoHub</author>
            <category>バイク相場</category>
            <category>中古バイク</category>
        </item>
        @endforeach
    </channel>
</rss>
