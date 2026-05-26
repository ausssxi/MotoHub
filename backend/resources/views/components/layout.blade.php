<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'MotoHub - 中古・新車バイク一括検索' }}</title>
    
    {{-- SEO用メタデータ --}}
    <meta name="description" content="{{ $metaDescription ?? '日本最大級のバイク検索・比較プラットフォーム。GooBike、BDS、Webikeから一括検索！' }}">
    <meta name="auth-check" content="{{ Auth::check() ? 'true' : 'false' }}">

    {{-- canonical URL（重複コンテンツ防止） --}}
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">

    {{-- RSS auto-discovery（ブログランキング連携用） --}}
    <link rel="alternate" type="application/rss+xml" title="MotoHub Blog" href="https://www.motohub.jp/blog/feed" />

    {{-- robots meta（ページ単位でのクロール制御） --}}
    @if(isset($robotsMeta) && str_contains(strtolower($robotsMeta), 'noindex'))
    <meta name="robots" content="{{ $robotsMeta }}">
    @else
    <meta name="robots" content="max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    @endif

    {{-- 日付メタタグ (Ahrefs コンテンツ監査対応) --}}
    @if(isset($publishedTime))
    <meta property="article:published_time" content="{{ $publishedTime }}" />
    @endif
    @if(isset($modifiedTime))
    <meta property="article:modified_time" content="{{ $modifiedTime }}" />
    @endif

    {{-- OGP設定 (SNSシェア用) --}}
    <meta property="og:title" content="{{ $title ?? 'MotoHub' }}" />
    <meta property="og:description" content="{{ $metaDescription ?? '日本最大級のバイク検索・比較プラットフォーム。GooBike、BDS、Webikeから一括検索！' }}" />
    <meta property="og:type" content="{{ isset($publishedTime) ? 'article' : 'website' }}" />
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}" />
    <meta property="og:site_name" content="MotoHub" />
    <meta property="og:locale" content="ja_JP" />
    <meta property="og:image" content="{{ $ogImage ?? asset('images/twitter_template.png') }}" />

    {{-- Twitter Card設定 --}}
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $title ?? 'MotoHub' }}" />
    <meta name="twitter:description" content="{{ $metaDescription ?? '日本最大級のバイク検索・比較プラットフォーム。' }}" />
    <meta name="twitter:image" content="{{ $ogImage ?? asset('images/twitter_template.png') }}" />
    
    {{-- CSRFトークン（Ajax通信に必須） --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- ★修正: アナリティクスのIDが空になって消えるのを防ぐため、IDを直接指定するか確実に出力させる --}}
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('app.ga_id', 'G-2SMHVZK9WE') }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ config('app.ga_id', 'G-2SMHVZK9WE') }}');
    </script>

    {{-- Google Fontsの爆速・非同期読み込み --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700;900&display=swap" rel="stylesheet" media="print" onload="this.media='all'">

    {{-- Tailwind CSS --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- サードパーティの重いJSに「defer(遅延)」をつけて画面描画を優先させる --}}
    <script src="https://unpkg.com/lucide@0.469.0" defer></script>
    <script src="{{ asset('js/push-manager.js') }}?v={{ filemtime(public_path('js/push-manager.js')) }}" defer></script>
    
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="/manifest.json">
    <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
    
    {{-- ページごとの独自のCSS --}}
    {{ $styles ?? '' }}

    {{-- AdSense用ID (JS内で使用) --}}
    <meta name="adsense-id" content="{{ config('app.adsense_id', 'ca-pub-3690883624273126') }}">

    <style>
        [x-cloak] { display: none !important; }
        .footer-link { transition: all 0.2s ease; }
        .footer-link:hover { color: #000; text-decoration: underline; }
        
        /* お気に入りボタンのアニメーション補助 */
        .wishlist-btn i { transition: transform 0.2s ease, fill 0.2s ease; }
        .wishlist-btn:active i { transform: scale(1.2); }
        
        /* 横スクロールバー非表示用 (Tailwindのscrollbar-hideクラス用) */
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

        /* スライドアップアニメーション（ボトムナビ等で使用） */
        @keyframes slideUp {
            from { transform: translateY(100%); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
    </style>
    <x-jsonld.website />
</head>
<body class="bg-white text-gray-900 font-sans min-h-screen flex flex-col pb-[60px] md:pb-0" data-logged-in="{{ Auth::check() ? 'true' : 'false' }}">

    {{-- ナビゲーション（ヘッダー） --}}
    {{ $navigation ?? '' }}

    {{-- メインコンテンツ --}}
    <main class="flex-grow">
        {{ $slot }}
    </main>

    {{-- フッター --}}
    <x-footer />

    {{-- モバイル下部タブナビ --}}
    <x-bottom-nav />

    {{-- 独自スクリプト --}}
    <script src="{{ asset('js/wishlist/manager.js') }}?v={{ filemtime(public_path('js/wishlist/manager.js')) }}" defer></script>
    <script src="{{ asset('js/wishlist/page.js') }}?v={{ filemtime(public_path('js/wishlist/page.js')) }}" defer></script>
    <script src="{{ asset('js/history/manager.js') }}?v={{ filemtime(public_path('js/history/manager.js')) }}" defer></script>
    <script src="{{ asset('js/search/interaction.js') }}?v={{ filemtime(public_path('js/search/interaction.js')) }}" defer></script>
    
    {{-- 各ページから渡されるスクリプト --}}
    {{ $scripts ?? '' }}
    
    <script>
        // 画像読み込みエラー時のグローバルハンドラ
        function handleImageError(img) {
            img.onerror = null;
            img.src = 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop';
            img.style.filter = 'grayscale(100%)';
            img.style.opacity = '0.5';
        }

        document.addEventListener('DOMContentLoaded', () => {
            const isLoggedIn = document.body.dataset.loggedIn === 'true';
            
            if (typeof WishlistManager !== 'undefined') WishlistManager.init(isLoggedIn);
            if (typeof HistoryManager !== 'undefined') HistoryManager.init(isLoggedIn);
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });

        // ==========================================
        // ★修正: JSのエラー（関数名の不一致）を解消しました
        // ==========================================
        let loadedAdSense = false;
        const loadAdSenseScript = () => {
            if (loadedAdSense) return;
            loadedAdSense = true;

            const adsenseId = document.querySelector('meta[name="adsense-id"]')?.content;

            // Google AdSense の読み込み
            if (adsenseId) {
                const adsScript = document.createElement('script');
                adsScript.src = `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${adsenseId}`;
                adsScript.async = true;
                adsScript.crossOrigin = "anonymous";
                document.head.appendChild(adsScript);
            }
        };

        // ユーザーアクション（スクロール、マウス移動、タップ）で発火
        window.addEventListener('scroll', loadAdSenseScript, { once: true, passive: true });
        window.addEventListener('mousemove', loadAdSenseScript, { once: true, passive: true });
        window.addEventListener('touchstart', loadAdSenseScript, { once: true, passive: true });
        
        // 保険: ユーザーが何もしなくても3秒後には自動で読み込む
        setTimeout(loadAdSenseScript, 3000);
    </script>
    {{-- 登録促進プロモーション（未ログイン時のみ） --}}
    @guest
        <link rel="stylesheet" href="{{ asset('css/registration-promo.css') }}?v={{ filemtime(public_path('css/registration-promo.css')) }}">
        <script src="{{ asset('js/promo/registration-promo.js') }}?v={{ filemtime(public_path('js/promo/registration-promo.js')) }}" defer></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof RegistrationPromo !== 'undefined') {
                    RegistrationPromo.init(false);
                }
            });
        </script>
        <script src="{{ asset('js/promo/return-trigger.js') }}?v={{ filemtime(public_path('js/promo/return-trigger.js')) }}" defer></script>
    @endguest
</body>
</html>