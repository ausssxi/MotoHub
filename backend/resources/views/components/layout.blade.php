<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'MotoHub - 中古・新車バイク一括検索' }}</title>
    
    {{-- SEO用メタデータ --}}
    <meta name="description" content="{{ $metaDescription ?? '日本最大級のバイク検索・比較プラットフォーム。GooBike、BDS、Webikeから一括検索！' }}">

    {{-- OGP設定 (SNSシェア用) --}}
    <meta property="og:title" content="{{ $title ?? 'MotoHub' }}" />
    <meta property="og:description" content="{{ $metaDescription ?? '日本最大級のバイク検索・比較プラットフォーム。GooBike、BDS、Webikeから一括検索！' }}" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:site_name" content="MotoHub" />
    <meta property="og:locale" content="ja_JP" />

    {{-- OGP画像: 各ページで指定があればそれ、なければデフォルト(twitter_card.png) --}}
    <meta property="og:image" content="{{ $ogImage ?? asset('images/twitter_card.png') }}" />

    {{-- Twitter Card設定 --}}
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $title ?? 'MotoHub' }}" />
    <meta name="twitter:description" content="{{ $metaDescription ?? '日本最大級のバイク検索・比較プラットフォーム。' }}" />
    <meta name="twitter:image" content="{{ $ogImage ?? asset('images/twitter_card.png') }}" />

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
        
        /* 横スクロールバー非表示用 (Tailwindのscrollbar-hideクラス用) */
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
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
        // 画像読み込みエラー時のグローバルハンドラ
        function handleImageError(img) {
            img.onerror = null;
            img.src = 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop';
            img.style.filter = 'grayscale(100%)';
            img.style.opacity = '0.5';
        }

        // ページ読み込み時にアイコンを初期化
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</body>
</html>