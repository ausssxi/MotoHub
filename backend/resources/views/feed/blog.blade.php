{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>MotoHub ブログ</title>
        <link>{{ url('/blog') }}</link>
        <description>MotoHubブログ - バイクに関するコラム・ガイド・レビュー</description>
        <language>ja</language>
        <lastBuildDate>{{ $posts->first()?->published_at?->toRfc2822String() ?? now()->toRfc2822String() }}</lastBuildDate>
        <atom:link href="{{ url('/feed/blog') }}" rel="self" type="application/rss+xml" />

        @foreach($posts as $post)
        <item>
            <title>{{ e($post->title) }}</title>
            <link>{{ url('/blog/' . $post->slug) }}</link>
            <description>{{ e($post->excerpt ? \App\Models\BlogPost::stripMarkdown($post->excerpt) : \Illuminate\Support\Str::limit(\App\Models\BlogPost::stripMarkdown($post->body), 200)) }}</description>
            <pubDate>{{ $post->published_at->toRfc2822String() }}</pubDate>
            <guid isPermaLink="true">{{ url('/blog/' . $post->slug) }}</guid>
            <author>{{ e($post->author?->name ?? 'MotoHub') }}</author>
            <category>ブログ</category>
        </item>
        @endforeach
    </channel>
</rss>
