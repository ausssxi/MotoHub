<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoHub - バイクをまとめて検索</title>
    
    <!-- Scripts -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/bike-search.css') }}?v={{ time() }}">

</head>
<body class="min-h-screen flex flex-col">

    <nav class="nav-header">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
            <a href="{{ route('bikes.index') }}" class="logo-link">
                <img src="{{ asset('favicon.svg') }}" alt="MotoHub Logo" class="logo-icon">
                <span class="logo-text">MOTOHUB</span>
            </a>
        </div>
    </nav>

    <main class="flex-grow">
        <!-- 検索セクション -->
        <section class="hero-bg py-10 sm:py-16 px-4 border-b border-gray-100 text-center">
            <div class="max-w-3xl mx-auto">
                <h1 class="text-xl sm:text-3xl font-black text-black mb-3 tracking-tight">中古バイクをまとめて検索</h1>
                <p class="text-gray-400 text-[13px] sm:text-sm mb-5">有名中古バイク販売サイトをまとめて一括検索！</p>
                
                <form action="{{ route('bikes.index') }}" method="GET" class="max-w-2xl mx-auto">
                    <div class="search-container flex items-center bg-white rounded-xl p-1 shadow-sm">
                        <div class="flex-shrink-0 pl-3 text-gray-300">
                            <i data-lucide="search" class="w-5 h-5"></i>
                        </div>
                        <input type="text" name="keyword" placeholder="車種・キーワード" class="w-full px-3 py-2 text-base focus:outline-none bg-transparent" value="{{ $keyword }}">
                        <button type="submit" class="bg-gray-800 hover:bg-black text-white font-bold px-10 py-2.5 rounded-lg transition-all whitespace-nowrap flex-shrink-0">
                            検索
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <div class="max-w-7xl mx-auto px-4 py-6">
            <div class="flex flex-col gap-4 mb-8">
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">
                    Search Results: <span class="text-black text-sm">{{ count($bikes) }} Items</span>
                </div>
            </div>

            <div id="results-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-10">
                @foreach ($bikes as $bike)
                <div class="bike-card flex flex-col bg-white rounded-2xl overflow-hidden shadow-sm">
                    <!-- 画像エリア -->
                    <div class="aspect-[4/3] bg-gray-50 relative overflow-hidden">
                        <div class="absolute top-3 left-3 z-10">
                            @php
                                $badgeColor = $bike['source_id'] === 'goobike' ? 'bg-red-600' : 'bg-orange-500';
                            @endphp
                            <div class="h-5 px-1.5 rounded {{ $badgeColor }} flex items-center justify-center text-[8px] text-white font-black uppercase shadow-md">
                                {{ $bike['source'] }}
                            </div>
                        </div>
                        @if(!empty($bike['images']) && isset($bike['images'][0]))
                            <img src="{{ $bike['images'][0] }}" class="bike-img w-full h-full object-cover" alt="{{ $bike['name'] }}" loading="lazy">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-200">
                                <i data-lucide="image" class="w-12 h-12"></i>
                            </div>
                        @endif
                    </div>
                    
                    <div class="p-4 flex flex-col flex-grow">
                        <p class="text-[10px] font-bold text-gray-400 mb-0.5">{{ $bike['maker'] }}</p>
                        <h3 class="text-sm font-bold text-black leading-tight line-clamp-2 mb-3">{{ $bike['name'] }}</h3>

                        <div class="flex flex-wrap gap-x-3 gap-y-1 mb-4 text-[11px] text-gray-500">
                            <span class="flex items-center gap-1"><i data-lucide="calendar" class="w-3 h-3"></i>{{ $bike['year'] }}</span>
                            <span class="flex items-center gap-1"><i data-lucide="gauge" class="w-3 h-3"></i>{{ $bike['mileage'] }}</span>
                            <span class="flex items-center gap-1"><i data-lucide="zap" class="w-3 h-3"></i>{{ $bike['displacement'] }}</span>
                        </div>

                        <div class="bg-gray-50 p-3 rounded-xl mb-4">
                            <div class="flex justify-between items-baseline">
                                <span class="text-[10px] font-bold text-gray-400 uppercase">Total</span>
                                <div class="text-black">
                                    <span class="text-xl font-black italic">{{ $bike['total_price'] }}</span>
                                    <span class="text-[10px] font-bold ml-0.5">万円</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-auto pt-4 border-t border-gray-50">
                            <p class="text-[11px] font-bold text-gray-700 mb-1 line-clamp-1">{{ $bike['store_name'] }}</p>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-[9px] font-black text-gray-300 uppercase italic">Source: {{ $bike['source'] }}</span>
                                <a href="{{ $bike['url'] }}" target="_blank" class="text-[10px] font-bold text-gray-500 hover:text-black flex items-center gap-1">
                                    詳細を見る <i data-lucide="external-link" class="w-3 h-3"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>