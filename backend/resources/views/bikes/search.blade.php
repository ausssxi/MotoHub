<x-layout>
    {{-- 1. タイトルの設定 --}}
    <x-slot:title>
        @if($keyword) 「{{ $keyword }}」の検索結果 @else 車両一覧 @endif - MotoHub
    </x-slot:title>

    {{-- 2. 共通ナビゲーションコンポーネントの使用 --}}
    <x-slot:navigation>
        <x-navigation 
            :totalListingsCount="$totalListingsCount" 
            :showSearch="true" 
            :keyword="$keyword" 
        />
    </x-slot:navigation>

    {{-- 3. メインコンテンツ --}}
    <div class="bg-gray-50 min-h-[calc(100vh-64px)] py-8">
        <div class="max-w-7xl mx-auto px-4">
            
            <!-- 結果情報ヘッダー -->
            <div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-black text-black">
                        @if($keyword) 「{{ $keyword }}」の検索結果 @else 車両一覧 @endif
                    </h2>
                    {{-- 総件数を $totalSearchCount、現在の表示件数を count($listings) で表示 --}}
                    <p class="text-xs font-bold text-gray-400 mt-1 tracking-wider">
                        全 {{ number_format($totalSearchCount) }} 件中 1〜{{ number_format(count($listings)) }} 件を表示
                    </p>
                </div>
                
                <div class="flex gap-2">
                    <select class="bg-white border border-gray-200 rounded-lg px-3 py-2 text-xs font-bold focus:outline-none shadow-sm cursor-pointer">
                        <option>新着順</option>
                        <option>価格の安い順</option>
                        <option>価格の高い順</option>
                    </select>
                </div>
            </div>

            <!-- 検索結果グリッド -->
            <div id="results-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse ($listings as $listing)
                <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col group border border-gray-100">
                    <!-- 画像エリア -->
                    <div class="aspect-[4/3] relative overflow-hidden bg-gray-100">
                        
                        @if(!empty($listing['images']) && isset($listing['images'][0]))
                            <img src="{{ $listing['images'][0] }}" 
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" 
                                 alt="{{ $listing['name'] }}">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-200">
                                <i data-lucide="image" class="w-12 h-12"></i>
                            </div>
                        @endif
                    </div>
                    
                    <!-- 情報エリア -->
                    <div class="p-5 flex-grow flex flex-col">
                        <span class="text-[10px] font-black text-gray-300 uppercase tracking-tighter mb-1">
                            {{ $listing['maker'] ?? 'OTHER' }}
                        </span>
                        <h3 class="text-sm font-bold text-black mb-4 line-clamp-2 h-10 group-hover:text-blue-600 transition-colors">
                            {{ $listing['name'] }}
                        </h3>

                        <div class="flex items-center gap-4 text-[11px] text-gray-500 mb-6">
                            <div class="flex items-center gap-1"><i data-lucide="calendar" class="w-3.5 h-3.5 text-gray-300"></i>{{ $listing['year'] }}</div>
                            <div class="flex items-center gap-1"><i data-lucide="gauge" class="w-3.5 h-3.5 text-gray-300"></i>{{ $listing['mileage'] }}</div>
                            <div class="flex items-center gap-1"><i data-lucide="zap" class="w-3.5 h-3.5 text-gray-300"></i>{{ $listing['displacement'] }}</div>
                        </div>

                        <!-- 価格バッジ -->
                        <div class="bg-gray-50 p-4 rounded-xl mt-auto border border-gray-100 group-hover:bg-blue-50/50 group-hover:border-blue-100 transition-colors">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-black text-gray-400 uppercase italic tracking-tighter">Total Price</span>
                                <div class="text-black">
                                    <span class="text-2xl font-black italic">{{ $listing['total_price'] }}</span>
                                    <span class="text-xs font-bold ml-0.5">万円</span>
                                </div>
                            </div>
                        </div>

                        <!-- 店舗・リンク -->
                        <div class="mt-4 pt-4 border-t border-gray-50 flex items-center justify-between">
                            <div class="flex items-center gap-1.5 overflow-hidden">
                                <div class="w-5 h-5 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="map-pin" class="w-3 h-3 text-gray-400"></i>
                                </div>
                                <span class="text-[10px] font-bold text-gray-600 truncate max-w-[120px]">
                                    {{ $listing['store_name'] }}
                                </span>
                            </div>
                            <a href="{{ $listing['url'] }}" target="_blank" class="text-[11px] font-black text-blue-600 hover:text-blue-700 flex items-center gap-1 transition-colors">
                                VIEW INFO <i data-lucide="arrow-up-right" class="w-3 h-3"></i>
                            </a>
                        </div>
                    </div>
                </div>
                @empty
                <!-- 検索結果なし -->
                <div class="col-span-full py-24 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i data-lucide="search-x" class="w-10 h-10 text-gray-300"></i>
                    </div>
                    <h3 class="text-lg font-bold text-black mb-2">一致する車両が見つかりませんでした</h3>
                    <p class="text-gray-400 text-sm mb-8">キーワードを変えて再度お試しください。</p>
                    <a href="{{ route('bikes.index') }}" class="inline-flex items-center gap-2 bg-black text-white px-6 py-3 rounded-xl font-bold text-sm hover:bg-gray-800 transition-all">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i> トップに戻って探す
                    </a>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</x-layout>