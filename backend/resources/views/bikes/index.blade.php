<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoHub - 中古・新車バイクをまとめて検索</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <style>
        .hero-gradient { background: linear-gradient(180deg, #f9fafb 0%, #ffffff 100%); }
        .pref-link:hover { color: #000; background-color: #f3f4f6; }
    </style>
</head>
<body class="bg-white text-gray-900 font-sans">

    <!-- Header -->
    <nav class="border-b border-gray-100 py-4">
        <div class="max-w-7xl mx-auto px-4 flex justify-between items-center">
            <a href="{{ route('bikes.index') }}" class="flex items-center gap-2">
                <img src="{{ asset('favicon.svg') }}" alt="MotoHub" class="w-8 h-8">
                <span class="text-xl font-black tracking-tighter">MotoHub</span>
            </a>
        </div>
    </nav>

    <main>
        <!-- 1. 検索セクション -->
        <section class="hero-gradient py-16 sm:py-24 px-4 border-b border-gray-50">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-xl sm:text-3xl font-black text-black mb-3 tracking-tight">＼ バイク登録台数 No.1！ ／</h1>
                <p class="text-gray-400 text-[13px] sm:text-sm mb-5">MotoHubは中古・新車バイクを<br>まとめて一括検索できるサービスです</p>
                
                <form action="{{ route('bikes.search') }}" method="GET" class="relative max-w-2xl mx-auto mt-8">
                    <div class="flex items-center bg-white rounded-2xl p-2 shadow-2xl border border-gray-100">
                        <div class="flex-shrink-0 pl-4 text-gray-400">
                            <i data-lucide="search" class="w-6 h-6"></i>
                        </div>
                        <input type="text" name="keyword" placeholder="ホンダ レブル250, 400cc..." class="w-full px-4 py-3 text-lg focus:outline-none bg-transparent">
                        <button type="submit" class="bg-black hover:bg-gray-800 text-white font-bold px-8 py-3.5 rounded-xl transition-all whitespace-nowrap">
                            検索する
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- 2. おすすめ車種セクション (16個のコンパクトタグ) -->
        <section class="max-w-7xl mx-auto px-4 py-16">
            <div class="flex items-end justify-between mb-8 border-b border-gray-100 pb-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <i data-lucide="sparkles" class="w-5 h-5 text-orange-500"></i>
                        <h2 class="text-xl font-black uppercase tracking-widest">おすすめモデル</h2>
                    </div>
                    <p class="text-xs text-gray-400 ml-7">登録台数が多い人気の16車種</p>
                </div>
                <a href="{{ route('bikes.index') }}" class="group flex items-center text-sm font-bold text-blue-600 hover:text-blue-700 transition-colors">
                    すべて見る
                    <i data-lucide="chevron-right" class="w-4 h-4 ml-1 transform group-hover:translate-x-1 transition-transform"></i>
                </a>
            </div>
            
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                {{-- 変数名を $popularBikes に統一 --}}
                @foreach($popularBikes as $bike)
                <a href="{{ route('bikes.search', ['keyword' => $bike->name]) }}" 
                   class="group flex items-center bg-white p-3 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-blue-200 hover:-translate-y-0.5 transition-all duration-200">
                    
                    <!-- 画像 -->
                    <div class="w-14 h-14 rounded-xl bg-gray-50 overflow-hidden flex-shrink-0 mr-4 border border-gray-50">
                        @if($bike->image_url)
                            <img 
                                src="{{ $bike->image_url }}" 
                                alt="{{ $bike->name }}" 
                                class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-500"
                                onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,%3Csvg xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22 width%3D%2224%22 height%3D%2224%22 viewBox%3D%220 0 24 24%22 fill%3D%22none%3D%22 stroke%3D%22%23ccc%22 stroke-width%3D%222%22 stroke-linecap%3D%22round%22 stroke-linejoin%3D%22round%22%3E%3Cpath d%3D%22M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2%22%2F%3E%3Ccircle cx%3D%227%22 cy%3D%2217%22 r%3D%222%22%2F%3E%3Cpath d%3D%22M9 17h6%22%2F%3E%3Ccircle cx%3D%2217%22 cy%3D%2217%22 r%3D%222%22%2F%3E%3C%2Fsvg%3E';this.className='w-full h-full object-center p-3 opacity-30';"
                            >
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                <i data-lucide="bike" class="w-6 h-6"></i>
                            </div>
                        @endif
                    </div>

                    <!-- テキスト -->
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-bold text-gray-800 truncate group-hover:text-blue-600 transition-colors">
                            {{ $bike->name }}
                        </h3>
                        <div class="flex items-center mt-1">
                            <span class="text-[11px] font-medium text-gray-400 bg-gray-50 px-1.5 py-0.5 rounded-md border border-gray-100">
                                <span class="text-blue-500 font-bold mr-0.5">{{ number_format($bike->listings_count ?? 0) }}</span>台
                            </span>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
        </section>

        <!-- 3. 都道府県リンクセクション -->
        <section class="bg-gray-50 py-16">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex items-center gap-2 mb-8 text-gray-500">
                    <i data-lucide="map-pin" class="w-5 h-5"></i>
                    <h2 class="text-xl font-black uppercase tracking-widest">Search by Area</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
                    @foreach($regions as $regionName => $prefs)
                    <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100">
                        <h3 class="text-sm font-black text-gray-400 mb-4 border-b border-gray-50 pb-2">{{ $regionName }}</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach($prefs as $pref)
                            <a href="{{ route('bikes.search', ['prefecture' => $pref]) }}" class="pref-link px-3 py-1.5 rounded-lg text-sm text-gray-600 font-medium transition-colors">
                                {{ $pref }}
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>
    </main>

    <footer class="py-10 text-center border-t border-gray-100 mt-20">
        <p class="text-xs text-gray-400 font-bold tracking-widest uppercase">© 2026 MotoHub - All Rights Reserved.</p>
    </footer>

    <script>lucide.createIcons();</script>
</body>
</html>