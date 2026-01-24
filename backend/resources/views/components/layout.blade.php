<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'MotoHub - 中古・新車バイク一括検索' }}</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    
    {{-- ページごとの独自のCSS --}}
    {{ $styles ?? '' }}

    {{-- Google AdSense - 本番環境かつID設定時のみ有効化 --}}
    @if(app()->isProduction() && config('app.adsense_id'))
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ config('app.adsense_id') }}"
             crossorigin="anonymous"></script>
    @endif

    {{-- Google Analytics (GA4) --}}
    @if(app()->isProduction() && config('app.ga_id'))
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('app.ga_id') }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ config('app.ga_id') }}');
        </script>
    @endif

    <style>
        .footer-link { transition: all 0.2s ease; }
        .footer-link:hover { color: #000; text-decoration: underline; }
        
        /* お気に入りボタンのアニメーション補助 */
        .wishlist-btn i { transition: transform 0.2s ease, fill 0.2s ease; }
        .wishlist-btn:active i { transform: scale(1.2); }
    </style>
</head>
<body class="bg-white text-gray-900 font-sans min-h-screen flex flex-col">

    {{-- ナビゲーション（ヘッダー） --}}
    {{ $navigation }}

    {{-- メインコンテンツ --}}
    <main class="flex-grow">
        {{ $slot }}
    </main>

    {{-- フッター --}}
    <x-footer />

    {{-- 
        お気に入り機能のコアロジックを読み込み
        Lucideの後に読み込むことで、JS内でのアイコン描画を確実にします
    --}}
    <script src="{{ asset('js/wishlist/manager.js') }}"></script>
    <script src="{{ asset('js/history/manager.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('js/search/interaction.js') }}"></script>
    {{ $scripts ?? '' }}
    <script>
        // ページ読み込み時にアイコンを初期化
        lucide.createIcons();
    </script>
</body>
</html>