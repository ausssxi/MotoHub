<x-layout>
    <x-slot:title>{{ $shop->name }}の在庫一覧 | MotoHub</x-slot:title>
    
    <x-slot:styles>
        <link rel="stylesheet" href="{{ asset('css/bike-search.css') }}">
    </x-slot:styles>

    <x-slot:scripts>
        <script src="{{ asset('js/search/sidebar.js') }}"></script>
        <script src="{{ asset('js/compare/manager.js') }}"></script>
        <script src="{{ asset('js/compare/ui.js') }}"></script>
        <script src="{{ asset('js/search/save_condition.js') }}"></script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- パンくず --}}
            <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('bikes.prefectures') }}" class="hover:text-gray-600 transition-colors">ショップを探す</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $shop->name }}</span></li>
                </ol>
            </nav>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                {{-- 左カラム: 店舗情報 --}}
                <div class="lg:col-span-4 space-y-6">
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 sticky top-24">
                        <div class="text-center mb-6">
                            <div class="w-24 h-24 rounded-full bg-gray-100 mx-auto mb-4 overflow-hidden border-2 border-white shadow-sm flex items-center justify-center">
                                @if($shop->image_url)
                                    <img src="{{ $shop->image_url }}" alt="{{ $shop->name }}" class="w-full h-full object-cover">
                                @else
                                    <i data-lucide="store" class="w-10 h-10 text-gray-300"></i>
                                @endif
                            </div>
                            <h1 class="text-xl font-black text-gray-900 leading-tight mb-2">{{ $shop->name }}</h1>
                            <span class="inline-block bg-blue-50 text-blue-600 text-[10px] font-bold px-2 py-1 rounded-full border border-blue-100">
                                {{ $shop->prefecture }}
                            </span>
                        </div>

                        <div class="space-y-4 text-sm">
                            <div class="flex items-start gap-3">
                                <i data-lucide="map-pin" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                                <p class="font-bold text-gray-600">{{ $shop->address }}</p>
                            </div>
                            
                            {{-- ★追加: 地図で見るボタン --}}
                            @if($shop->latitude && $shop->longitude)
                            <a href="{{ route('shops.map', ['lat' => $shop->latitude, 'lng' => $shop->longitude, 'shop_id' => $shop->id]) }}" 
                               class="block w-full bg-white border-2 border-blue-600 text-blue-600 hover:bg-blue-50 font-black text-center py-3 rounded-xl transition-all flex items-center justify-center gap-2 group">
                                <i data-lucide="map" class="w-4 h-4 group-hover:scale-110 transition-transform"></i>
                                地図で場所を確認する
                            </a>
                            @endif

                            <div class="border-t border-gray-100 my-4"></div>

                            <div class="flex items-start gap-3">
                                <i data-lucide="phone" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                                <p class="font-bold text-gray-600">{{ $shop->phone ?? '-' }}</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <i data-lucide="clock" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                                <p class="font-bold text-gray-600">{{ $shop->business_hours ?? '-' }}</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <i data-lucide="calendar-off" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                                <p class="font-bold text-gray-600">{{ $shop->closed_days ?? '-' }}</p>
                            </div>
                        </div>

                        @if($shop->url)
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <a href="{{ $shop->url }}" target="_blank" rel="nofollow" class="flex items-center justify-center gap-2 text-xs font-bold text-gray-400 hover:text-blue-600 transition-colors">
                                公式サイト・詳細ページ <i data-lucide="external-link" class="w-3 h-3"></i>
                            </a>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- 右カラム: 在庫リスト --}}
                <div class="lg:col-span-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-black text-gray-900 flex items-center gap-2">
                            <i data-lucide="bike" class="w-5 h-5 text-blue-500"></i>
                            在庫車両
                            <span class="text-sm font-bold text-gray-400 ml-1">({{ number_format($pagination['total']) }}台)</span>
                        </h2>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                        @forelse ($items as $listing)
                            {{-- search.blade.php と同じカードデザイン --}}
                            <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-all duration-300 flex flex-col group border border-gray-100 relative cursor-pointer bike-card">
                                <a href="{{ route('bikes.show', $listing['id']) }}" class="absolute inset-0 z-20"></a>
                                
                                <div class="aspect-[4/3] relative overflow-hidden bg-gray-50">
                                    @if(!empty($listing['images']) && isset($listing['images'][0]))
                                        <img src="{{ $listing['images'][0] }}" 
                                             onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop'; this.classList.add('grayscale', 'opacity-50');"
                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    @else
                                        <img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop" 
                                             class="w-full h-full object-cover grayscale opacity-50 group-hover:scale-105 transition-transform duration-500">
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <i data-lucide="image-off" class="w-8 h-8 text-white/50"></i>
                                        </div>
                                    @endif

                                    @if($listing['bargain_score'] > 5)
                                    <div class="absolute bottom-0 left-0 bg-red-600 text-white text-[10px] font-black px-2 py-1.5 rounded-tr-xl shadow-lg z-20 flex items-center gap-1">
                                        <i data-lucide="trending-down" class="w-3.5 h-3.5"></i>
                                        相場より約{{ round($listing['bargain_score']) }}%お得！
                                    </div>
                                    @endif

                                    {{-- ボタン類 --}}
                                    <button class="compare-btn absolute top-3 left-3 z-30 w-10 h-10 bg-white/90 backdrop-blur rounded-full flex items-center justify-center text-gray-400 border border-gray-100 shadow-sm transition-all hover:scale-110 active:scale-95" data-id="{{ $listing['id'] }}">
                                        <i data-lucide="layers" class="w-5 h-5"></i>
                                    </button>
                                    <button class="wishlist-btn absolute top-3 right-3 z-30 w-10 h-10 bg-white/90 backdrop-blur rounded-full flex items-center justify-center text-gray-400 shadow-sm border border-gray-100" data-id="{{ $listing['id'] }}">
                                        <i data-lucide="heart" class="w-5 h-5"></i>
                                    </button>
                                    {{-- 掲載元サイトのバッジ（ロジックをバックエンドに移行） --}}
                                    <div class="absolute bottom-3 right-3 z-20 bg-black/50 backdrop-blur-sm px-2 py-1 rounded-lg flex items-center gap-1.5 border border-white/10 shadow-sm">
                                        @if(isset($listing['source_icon_key']) && $listing['source_icon_key'] !== 'default')
                                            <img src="{{ asset('images/sites/' . $listing['source_icon_key'] . '.png') }}" class="w-3 h-3 rounded-sm brightness-110" alt="{{ $listing['source'] ?? '外部サイト' }}">
                                        @else
                                            <i data-lucide="external-link" class="w-3 h-3 text-white/80"></i>
                                        @endif
                                        <span class="text-[8px] font-black text-white/90">{{ $listing['source'] ?? $listing['site_name'] ?? '外部サイト' }}</span>
                                    </div>
                                </div>

                                <div class="p-4 flex-grow flex flex-col">
                                    <h3 class="text-sm font-black text-gray-800 mb-2 line-clamp-2 leading-tight group-hover:text-blue-600 transition-colors">{{ $listing['name'] }}</h3>
                                    
                                    <div class="grid grid-cols-2 gap-y-1 text-[10px] font-bold text-gray-400 mb-3">
                                        <div>{{ $listing['model_year'] }}</div>
                                        <div>{{ $listing['mileage'] }}</div>
                                    </div>

                                    <div class="mt-auto pt-3 border-t border-gray-50 flex justify-between items-end">
                                        <div>
                                            <span class="text-[8px] text-gray-400 font-bold block">支払総額</span>
                                            <span class="text-lg font-black text-red-500">{{ $listing['total_price'] }}<span class="text-xs ml-0.5">万円</span></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full py-16 text-center text-gray-400 font-bold text-sm bg-white rounded-2xl border border-dashed border-gray-200">
                                現在、在庫はありません。
                            </div>
                        @endforelse
                    </div>

                    {{-- ページネーション --}}
                    @if($pagination['last_page'] > 1)
                    <div class="mt-12 flex justify-center">
                        <div class="flex gap-2">
                            @if($pagination['prev_url'])
                                <a href="{{ $pagination['prev_url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg bg-white border border-gray-200 hover:border-black transition-all"><i data-lucide="chevron-left" class="w-4 h-4"></i></a>
                            @endif
                            @foreach($pagination['pages'] as $page)
                                @if($page['is_dot']) <span class="px-1 text-gray-300">...</span>
                                @else <a href="{{ $page['url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg font-black text-sm transition-all {{ $page['is_active'] ? 'bg-black text-white shadow-lg' : 'bg-white border border-gray-200 text-gray-400 hover:border-black' }}">{{ $page['label'] }}</a>
                                @endif
                            @endforeach
                            @if($pagination['next_url'])
                                <a href="{{ $pagination['next_url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg bg-white border border-gray-200 hover:border-black transition-all"><i data-lucide="chevron-right" class="w-4 h-4"></i></a>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layout>