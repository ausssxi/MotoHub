<!DOCTYPE html>
<html lang="ja">
<head>
    <!-- Google Analytics (GA4) -->
    @if(config('app.ga_id'))
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('app.ga_id') }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ config('app.ga_id') }}');
        </script>
    @endif
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- $title slot を使ってページごとにタイトルを変えられるようにします --}}
    <title>{{ $title ?? 'MotoHub - バイク一括検索' }}</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    
    {{-- ページごとの独自のCSSを受け取ります --}}
    {{ $styles ?? '' }}

    <style>
        /* フッターリンクの共通ホバースタイル */
        .footer-link {
            transition: all 0.2s ease;
        }
        .footer-link:hover {
            color: #000;
            text-decoration: underline;
        }
    </style>
</head>
<body class="bg-white text-gray-900 font-sans min-h-screen flex flex-col">

    {{-- ナビゲーション（ヘッダー） --}}
    {{ $navigation }}

    {{-- メインコンテンツ --}}
    <main class="flex-grow">
        {{ $slot }}
    </main>

    {{-- フッターをコンポーネントとして呼び出し --}}
    <x-footer />

    <script>lucide.createIcons();</script>
</body>
</html>