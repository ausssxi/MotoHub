<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>検索結果 - MotoHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- Navigation -->
    <nav class="bg-white border-b border-gray-100 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2">
                <img src="{{ asset('favicon.svg') }}" alt="MotoHub" class="w-6 h-6">
                <span class="text-lg font-black tracking-tighter">MotoHub</span>
            </a>
            
            <form action="{{ route('bikes.search') }}" method="GET" class="hidden sm:flex flex-grow max-w-md mx-8">
                <div class="relative w-full">
                    <input type="text" name="keyword" value="{{ $keyword ?? '' }}" placeholder="車種を再検索..." 
                        class="w-full bg-gray-100 border-none rounded-full px-4 py-2 text-sm focus:ring-2 focus:ring-black transition-all">
                </div>
            </form>

            <div class="flex items-center gap-4 text-gray-400">
                <i data-lucide="filter" class="w-5 h-5 cursor-pointer hover:text-black"></i>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <!-- Results Info -->
        <div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-black">
                    @if($keyword) 「{{ $keyword }}」の検索結果 @else 車両一覧 @endif
                </h2>
                <p class="text-xs font-bold text-gray-400 uppercase mt-1">FOUND {{ count($bikes) }} VEHICLES</p>
            </div>
            
            <div class="flex gap-2">
                <select class="bg-white border border-gray-200 rounded-lg px-3 py-1.5 text-xs font-bold focus:outline-none">
                    <option>新着順</option>
                    <option>価格の安い順</option>
                    <option>価格の高い順</option>
                </select>
            </div>
        </div>

        <!-- Results Grid -->
        <div id="results-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse ($bikes as $bike)
            <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-shadow duration-300 flex flex-col group">
                <!-- 画像エリア -->
                <div class="aspect-[4/3] relative overflow-hidden bg-gray-100">
                    <div class="absolute top-3 left-3 z-10 flex gap-2">
                        @php $badgeColor = $bike['source_id'] === 'goobike' ? 'bg-red-600' : 'bg-orange-500'; @endphp
                        <span class="px-2 py-0.5 rounded text-[9px] font-black text-white uppercase shadow-sm {{ $badgeColor }}">
                            {{ $bike['source'] }}
                        </span>
                    </div>
                    @if(!empty($bike['images']) && isset($bike['images'][0]))
                        <img src="{{ $bike['images'][0] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="{{ $bike['name'] }}">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-gray-200"><i data-lucide="image" class="w-12 h-12"></i></div>
                    @endif
                </div>
                
                <div class="p-5 flex-grow flex flex-col">
                    <span class="text-[10px] font-black text-gray-300 uppercase tracking-tighter mb-1">{{ $bike['maker'] }}</span>
                    <h3 class="text-sm font-bold text-black mb-4 line-clamp-2 h-10">{{ $bike['name'] }}</h3>

                    <div class="flex items-center gap-4 text-[11px] text-gray-500 mb-6">
                        <div class="flex items-center gap-1"><i data-lucide="calendar" class="w-3.5 h-3.5"></i>{{ $bike['year'] }}</div>
                        <div class="flex items-center gap-1"><i data-lucide="gauge" class="w-3.5 h-3.5"></i>{{ $bike['mileage'] }}</div>
                        <div class="flex items-center gap-1"><i data-lucide="zap" class="w-3.5 h-3.5"></i>{{ $bike['displacement'] }}</div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-xl mt-auto">
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-black text-gray-400 uppercase italic">Total Price</span>
                            <div class="text-black">
                                <span class="text-2xl font-black italic">{{ $bike['total_price'] }}</span>
                                <span class="text-xs font-bold ml-0.5">万円</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-50 flex items-center justify-between">
                        <div class="flex items-center gap-1.5 overflow-hidden">
                            <div class="w-5 h-5 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i data-lucide="map-pin" class="w-3 h-3 text-gray-400"></i>
                            </div>
                            <span class="text-[10px] font-bold text-gray-600 truncate">{{ $bike['store_name'] }}</span>
                        </div>
                        <a href="{{ $bike['url'] }}" target="_blank" class="text-[11px] font-bold text-gray-400 hover:text-black flex items-center gap-1 transition-colors">
                            VIEW <i data-lucide="arrow-up-right" class="w-3 h-3"></i>
                        </a>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-span-full py-20 text-center">
                <i data-lucide="frown" class="w-12 h-12 text-gray-200 mx-auto mb-4"></i>
                <p class="text-gray-400 font-bold">一致する車両が見つかりませんでした。</p>
                <a href="/" class="text-black text-sm font-bold underline mt-4 block">トップに戻って再検索</a>
            </div>
            @endforelse
        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>