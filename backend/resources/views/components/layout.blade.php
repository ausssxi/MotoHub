<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- $title slot を使ってページごとにタイトルを変えられるようにします --}}
    <title>{{ $title ?? 'MotoHub - バイク一括検索' }}</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    
    {{-- ページごとの独自のCSS（indexのグラデーションなど）を受け取ります --}}
    {{ $styles ?? '' }}
</head>
<body class="bg-white text-gray-900 font-sans min-h-screen">

    {{-- ナビゲーション（ヘッダー） --}}
    {{ $navigation }}

    <main>
        {{ $slot }}
    </main>

    <footer class="py-10 text-center border-t border-gray-100 mt-20">
        <p class="text-xs text-gray-400 font-bold tracking-widest uppercase">© {{ date('Y') }} MotoHub - All Rights Reserved.</p>
    </footer>

    <script>lucide.createIcons();</script>
</body>
</html>