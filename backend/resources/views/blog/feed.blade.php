@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>MotoHub Blog</title>
        <link>{{ url('/blog') }}</link>
        <description>MotoHubのブログ - バイクに関する最新情報</description>
        <language>ja</language>
        <lastBuildDate>{{ $posts->first()?->published_at?->toRfc2822String() ?? now()->toRfc2822String() }}</lastBuildDate>
        <atom:link href="{{ url('/blog/feed') }}" rel="self" type="application/rss+xml" />

        @foreach($posts as $post)
        <item>
            <title>{{ e($post->title) }}</title>
            <link>{{ url('/blog/' . $post->slug) }}</link>
            <description>{{ e($post->excerpt ? \App\Models\BlogPost::stripMarkdown($post->excerpt) : Str::limit(\App\Models\BlogPost::stripMarkdown($post->body), 200)) }}</description>
            <pubDate>{{ $post->published_at->toRfc2822String() }}</pubDate>
            <guid isPermaLink="true">{{ url('/blog/' . $post->slug) }}</guid>
            @if($post->author)
            <author>{{ e($post->author->name) }}</author>
            @endif
            @if($post->eyecatch_image)
            <enclosure url="{{ url('/storage/' . $post->eyecatch_image) }}" length="0" type="image/{{ Str::endsWith($post->eyecatch_image, '.jpg') || Str::endsWith($post->eyecatch_image, '.jpeg') ? 'jpeg' : 'png' }}" />
            @endif
        </item>
        @endforeach
    </channel>
</rss>
