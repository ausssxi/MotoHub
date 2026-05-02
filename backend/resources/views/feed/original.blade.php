{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>MotoHub オリジナルコンテンツ</title>
        <link>{{ url('/') }}</link>
        <description>MotoHubオリジナル記事・ブログの統合フィード</description>
        <language>ja</language>
        <lastBuildDate>{{ $items->first()['pubDate']?->toRfc2822String() ?? now()->toRfc2822String() }}</lastBuildDate>
        <atom:link href="{{ url('/feed/original') }}" rel="self" type="application/rss+xml" />

        @foreach($items as $item)
        <item>
            <title>{{ e($item['title']) }}</title>
            <link>{{ $item['link'] }}</link>
            <description>{{ e($item['description']) }}</description>
            <pubDate>{{ $item['pubDate']->toRfc2822String() }}</pubDate>
            <guid isPermaLink="true">{{ $item['link'] }}</guid>
            <author>{{ e($item['author']) }}</author>
            <category>{{ e($item['category']) }}</category>
        </item>
        @endforeach
    </channel>
</rss>
