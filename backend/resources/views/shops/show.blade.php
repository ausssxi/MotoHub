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
                            {{-- ★修正: 長い直書きコードを削除し、共通コンポーネントを呼び出す形にリファクタリング --}}
                            {{-- これにより、カードのデザイン変更が1箇所で済み、最初の4枚高速読み込みも自動適用されます --}}
                            @include('bikes.partials.bike_card', ['listing' => $listing, 'isFirstView' => $loop->index < 4])
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