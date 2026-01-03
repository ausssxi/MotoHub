<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoHub - 中古バイクをまとめて検索</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Noto+Sans+JP:wght@400;700;900&display=swap');
        
        body {
            font-family: 'Inter', 'Noto Sans JP', sans-serif;
            background-color: #ffffff;
            color: #111827;
            -webkit-tap-highlight-color: transparent;
        }

        .hero-bg {
            background-color: #ffffff;
            background-image: linear-gradient(#fcfcfc 1px, transparent 1px), linear-gradient(90deg, #fcfcfc 1px, transparent 1px);
            background-size: 25px 25px;
        }

        .search-container {
            border: 1px solid #eeeeee;
            transition: all 0.2s ease;
        }

        .search-container:focus-within {
            border-color: #d1d5db;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        }

        .bike-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        @media (hover: hover) {
            .bike-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.08);
            }
            .bike-card:hover .bike-img {
                transform: scale(1.05);
            }
        }

        @media screen and (max-width: 768px) {
            input[type="text"] {
                font-size: 16px;
            }
        }

        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <nav class="bg-white border-b border-gray-50 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center">
            <a href="{{ route('bikes.index') }}" class="text-xl font-black tracking-tighter text-black">
                MOTOHUB
            </a>
        </div>
    </nav>

    <main class="flex-grow">
        <section class="hero-bg py-10 sm:py-16 px-4 relative border-b border-gray-50">
            <div class="max-w-3xl mx-auto text-center relative z-10">
                <h1 class="text-xl sm:text-3xl font-black text-black mb-3 tracking-tight whitespace-nowrap">
                    中古バイクをまとめて検索
                </h1>
                
                <p class="text-gray-400 text-[13px] sm:text-sm mb-5">
                    有名中古バイク販売サイトをまとめて一括検索！
                </p>

                <form action="{{ route('bikes.index') }}" method="GET" class="max-w-2xl mx-auto">
                    <div class="search-container flex items-center bg-white rounded-xl p-1 sm:p-1.5 shadow-sm">
                        <div class="flex-shrink-0 pl-2 sm:pl-3 text-gray-300">
                            <i data-lucide="search" class="w-4 h-4 sm:w-5 h-5"></i>
                        </div>
                        <input 
                            type="text" 
                            name="keyword"
                            placeholder="車種・キーワード" 
                            class="w-full px-2 sm:px-3 py-2 text-sm sm:text-base text-gray-800 focus:outline-none placeholder-gray-300 font-medium bg-transparent"
                            value="{{ $keyword }}"
                        >
                        <button type="submit" class="bg-gray-500 hover:bg-gray-600 active:scale-95 text-white font-bold px-4 sm:px-8 py-2 rounded-lg transition-all duration-200 text-sm flex-shrink-0">
                            検索
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <div class="max-w-7xl mx-auto px-4 py-6 sm:py-10">
            <div class="flex flex-col gap-4 mb-8">
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">
                    Search Results: <span class="text-black text-sm">{{ count($bikes) }} Items</span>
                </div>
                
                <div class="flex overflow-x-auto hide-scrollbar gap-2 pb-2 sm:pb-0">
                    <button class="whitespace-nowrap px-4 py-2 text-[11px] font-bold rounded-lg bg-black text-white shadow-sm transition-all">新着順</button>
                    <button class="whitespace-nowrap px-4 py-2 text-[11px] font-bold rounded-lg border border-gray-100 text-gray-400 hover:text-black hover:border-gray-300 transition-all bg-white">価格(安)</button>
                    <button class="whitespace-nowrap px-4 py-2 text-[11px] font-bold rounded-lg border border-gray-100 text-gray-400 hover:text-black hover:border-gray-300 transition-all bg-white">価格(高)</button>
                    <button class="whitespace-nowrap px-4 py-2 text-[11px] font-bold rounded-lg border border-gray-100 text-gray-400 hover:text-black hover:border-gray-300 transition-all bg-white">走行距離(少)</button>
                    <button class="whitespace-nowrap px-4 py-2 text-[11px] font-bold rounded-lg border border-gray-100 text-gray-400 hover:text-black hover:border-gray-300 transition-all bg-white">走行距離(多)</button>
                </div>
            </div>

            <div id="results-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-10">
                @foreach ($bikes as $bike)
                <div class="bike-card flex flex-col bg-white border border-gray-100 rounded-2xl overflow-hidden">
                    {{-- 画像セクション --}}
                    <div class="aspect-[4/3] bg-gray-50 relative group overflow-hidden">
                        <div class="absolute top-3 left-3 z-10">
                            @if ($bike['source_id'] === 'goo')
                                <div class="h-5 px-1.5 rounded bg-[#e60012] flex items-center justify-center text-[8px] text-white font-black shadow-sm">GOO</div>
                            @elseif ($bike['source_id'] === 'bds')
                                <div class="h-5 px-1.5 rounded bg-[#f39800] flex items-center justify-center text-[8px] text-white font-black shadow-sm">BDS</div>
                            @endif
                        </div>

                        {{-- 画像がある場合は表示、なければプレースホルダー --}}
                        @if (!empty($bike['images']) && isset($bike['images'][0]))
                            <img 
                                src="{{ $bike['images'][0] }}" 
                                alt="{{ $bike['name'] }}"
                                class="bike-img w-full h-full object-cover transition-transform duration-500"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                            >
                            <div class="hidden w-full h-full items-center justify-center text-gray-200">
                                <i data-lucide="image-off" class="w-10 h-10"></i>
                            </div>
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-200 group-hover:text-gray-300 transition-colors">
                                <i data-lucide="image" class="w-12 h-12"></i>
                            </div>
                        @endif
                    </div>
                    
                    <div class="p-4 flex flex-col flex-grow">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 mb-0.5">{{ $bike['maker'] }}</p>
                                <h3 class="text-sm font-bold text-black leading-tight line-clamp-2">{{ $bike['name'] }}</h3>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-x-3 gap-y-1 mb-4 text-[11px] text-gray-500 font-medium">
                            <span class="flex items-center gap-1"><i data-lucide="calendar" class="w-3 h-3"></i>{{ $bike['year'] }}</span>
                            <span class="flex items-center gap-1"><i data-lucide="gauge" class="w-3 h-3"></i>{{ $bike['mileage'] }}</span>
                            <span class="flex items-center gap-1"><i data-lucide="zap" class="w-3 h-3"></i>{{ $bike['displacement'] }}</span>
                        </div>

                        <div class="bg-gray-50 p-3 rounded-xl mb-4">
                            <div class="flex justify-between items-baseline mb-1">
                                <span class="text-[10px] font-bold text-gray-400 uppercase">Total</span>
                                <div class="text-black">
                                    <span class="text-xl font-black italic">{{ $bike['total_price'] }}</span>
                                    <span class="text-[10px] font-bold ml-0.5">万円</span>
                                </div>
                            </div>
                            <div class="flex justify-between items-baseline border-t border-gray-200 pt-1 mt-1">
                                <span class="text-[10px] font-bold text-gray-400 uppercase">Base</span>
                                <div class="text-gray-600">
                                    <span class="text-sm font-bold">{{ $bike['base_price'] }}</span>
                                    <span class="text-[9px] font-bold ml-0.5">万円</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-auto pt-4 border-t border-gray-50">
                            <div class="flex items-start gap-2 mb-2">
                                <i data-lucide="store" class="w-3.5 h-3.5 text-gray-300 mt-0.5"></i>
                                <div>
                                    <p class="text-[11px] font-bold text-gray-700 leading-none mb-1">{{ $bike['store_name'] }}</p>
                                    <p class="text-[10px] text-gray-400">{{ $bike['store_address'] }}</p>
                                </div>
                            </div>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-[9px] font-black text-gray-300 uppercase tracking-tighter">Source: {{ $bike['source'] }}</span>
                                <a href="{{ $bike['url'] }}" target="_blank" class="text-[10px] font-bold text-gray-500 hover:text-black flex items-center gap-1">
                                    詳細を見る <i data-lucide="chevron-right" class="w-3 h-3"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="mt-16 text-center">
                <button class="px-12 py-3 border border-gray-200 rounded-xl text-[11px] font-black text-gray-400 hover:bg-gray-50 hover:text-black hover:border-gray-400 transition-all uppercase tracking-widest">
                    Load More Results
                </button>
            </div>
        </div>
    </main>

    <footer class="bg-white py-12 px-4 border-t border-gray-50">
        <div class="max-w-7xl mx-auto text-center">
            <div class="flex flex-col sm:flex-row justify-center items-center gap-y-5 sm:gap-x-10 text-[11px] font-bold text-gray-400 mb-10">
                <a href="#" class="hover:text-black transition-colors uppercase tracking-widest">Privacy Policy</a>
                <a href="#" class="hover:text-black transition-colors uppercase tracking-widest">Contact Form</a>
                <a href="#" class="hover:text-black transition-colors uppercase tracking-widest">Operator Info</a>
            </div>
            <p class="text-[9px] text-gray-300 tracking-[0.2em] font-medium uppercase">&copy; 2026 MOTOHUB</p>
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
