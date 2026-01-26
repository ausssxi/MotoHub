<x-layout>
    <x-slot:title>
        {{ $shop->name }} の在庫一覧 | MotoHub
    </x-slot:title>

    <x-slot:scripts>
        <script src="{{ asset('js/compare/manager.js') }}"></script>
        <script src="{{ asset('js/compare/ui.js') }}"></script>
        <script src="{{ asset('js/wishlist/manager.js') }}"></script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    {{-- パンくずリスト --}}
    <div class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <nav class="flex text-xs font-bold text-gray-400" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $shop->name }}</span></li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="bg-gray-50 min-h-screen py-8 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="lg:grid lg:grid-cols-12 lg:gap-8">
                
                {{-- 左カラム: 店舗情報 --}}
                <div class="lg:col-span-4 space-y-6 mb-8 lg:mb-0">
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 sticky top-24">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-16 h-16 rounded-full flex items-center justify-center shrink-0 overflow-hidden border border-gray-100">
                                @if($shop->display_image_url)
                                    <img src="{{ $shop->display_image_url }}" alt="{{ $shop->name }}" class="w-full h-full object-cover">
                                @else
                                    <i data-lucide="store" class="w-8 h-8 text-gray-400"></i>
                                @endif
                            </div>
                            <h1 class="text-xl font-black text-gray-900 leading-tight">
                                {{ $shop->name }}
                            </h1>
                        </div>

                        <div class="space-y-4 text-sm font-bold text-gray-600">
                            <div class="flex items-start gap-3">
                                <i data-lucide="map-pin" class="w-4 h-4 text-gray-400 mt-1 shrink-0"></i>
                                <div>
                                    <span class="block text-xs text-gray-400 mb-0.5">住所</span>
                                    <p>{{ $shop->address ?? '未登録' }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i data-lucide="phone" class="w-4 h-4 text-gray-400 mt-1 shrink-0"></i>
                                <div>
                                    <span class="block text-xs text-gray-400 mb-0.5">電話番号</span>
                                    <p class="font-black text-lg">{{ $shop->phone ?? '-' }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i data-lucide="clock" class="w-4 h-4 text-gray-400 mt-1 shrink-0"></i>
                                <div>
                                    <span class="block text-xs text-gray-400 mb-0.5">営業時間</span>
                                    <p>{{ $shop->business_hours ?? '未登録' }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i data-lucide="calendar-off" class="w-4 h-4 text-gray-400 mt-1 shrink-0"></i>
                                <div>
                                    <span class="block text-xs text-gray-400 mb-0.5">定休日</span>
                                    <p>{{ $shop->regular_holiday ?? '未登録' }}</p>
                                </div>
                            </div>
                        </div>

                        @if(!empty($shop->phone))
                        <div class="mt-8 pt-6 border-t border-gray-100">
                            <a href="tel:{{ str_replace('-', '', $shop->phone) }}" class="block w-full bg-blue-600 hover:bg-blue-500 text-white font-black text-center py-3 rounded-xl shadow-lg shadow-blue-200 transition-all flex items-center justify-center gap-2">
                                <i data-lucide="phone" class="w-4 h-4"></i>
                                電話で問い合わせる
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
                            <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-all duration-300 flex flex-col group border border-gray-100 relative cursor-pointer bike-card">
                                <a href="{{ route('bikes.show', $listing['id']) }}" class="absolute inset-0 z-20"></a>
                                
                                <div class="aspect-[4/3] relative overflow-hidden bg-gray-50">
                                    @if(!empty($listing['images']) && isset($listing['images'][0]))
                                        <img src="{{ $listing['images'][0] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="">
                                    @else
                                        <div class="flex items-center justify-center h-full text-gray-300">
                                            <i data-lucide="image-off" class="w-12 h-12"></i>
                                        </div>
                                    @endif

                                    {{-- アクションボタン --}}
                                    <button class="compare-btn absolute top-2 left-2 z-30 w-8 h-8 bg-white/90 backdrop-blur rounded-full flex items-center justify-center text-gray-400 border border-gray-100 shadow-sm hover:scale-110 active:scale-95" data-id="{{ $listing['id'] }}">
                                        <i data-lucide="layers" class="w-4 h-4"></i>
                                    </button>
                                    <button class="wishlist-btn absolute top-2 right-2 z-30 w-8 h-8 bg-white/90 backdrop-blur rounded-full flex items-center justify-center text-gray-400 shadow-sm border border-gray-100 hover:scale-110 active:scale-95" data-id="{{ $listing['id'] }}">
                                        <i data-lucide="heart" class="w-4 h-4"></i>
                                    </button>
                                </div>

                                <div class="p-4 flex-grow flex flex-col">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="text-[9px] font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded uppercase">{{ $listing['maker'] }}</span>
                                    </div>
                                    <h3 class="text-sm font-black text-gray-800 mb-3 line-clamp-2 leading-tight group-hover:text-blue-600 transition-colors">{{ $listing['name'] }}</h3>
                                    
                                    <div class="grid grid-cols-2 gap-y-1 gap-x-2 text-[10px] font-bold text-gray-400 mb-4">
                                        <div class="flex items-center gap-1"><i data-lucide="calendar" class="w-3 h-3 text-gray-300"></i><span>{{ $listing['model_year'] }}</span></div>
                                        <div class="flex items-center gap-1"><i data-lucide="gauge" class="w-3 h-3 text-gray-300"></i><span>{{ $listing['mileage'] }}</span></div>
                                    </div>

                                    <div class="mt-auto pt-3 border-t border-gray-50 flex justify-between items-end">
                                        <span class="text-[9px] font-black text-gray-400 uppercase tracking-tighter">総額</span>
                                        <div class="text-red-500 font-black italic">
                                            <span class="text-xl tracking-tighter">{{ $listing['total_price'] }}</span><span class="text-[10px] ml-0.5">万円</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full py-20 text-center bg-white rounded-3xl border border-gray-100">
                                <i data-lucide="bike" class="w-12 h-12 text-gray-200 mx-auto mb-4"></i>
                                <p class="text-gray-400 font-bold text-sm">現在、在庫車両はありません</p>
                            </div>
                        @endforelse
                    </div>

                    {{-- ページネーション --}}
                    @if($pagination['last_page'] > 1)
                    <div class="mt-12 flex justify-center">
                        <nav class="flex gap-2">
                            @if($pagination['prev_url'])
                            <a href="{{ $pagination['prev_url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg bg-white border border-gray-200 hover:border-black transition-all"><i data-lucide="chevron-left" class="w-4 h-4"></i></a>
                            @endif
                            @foreach($pagination['pages'] as $page)
                                @if($page['is_dot']) <span class="px-2 py-2 text-gray-300">...</span>
                                @else <a href="{{ $page['url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg font-bold text-xs transition-all {{ $page['is_active'] ? 'bg-black text-white' : 'bg-white border border-gray-200 text-gray-400 hover:border-black' }}">{{ $page['label'] }}</a>
                                @endif
                            @endforeach
                            @if($pagination['next_url'])
                            <a href="{{ $pagination['next_url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg bg-white border border-gray-200 hover:border-black transition-all"><i data-lucide="chevron-right" class="w-4 h-4"></i></a>
                            @endif
                        </nav>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layout>