<x-layout>
    <x-slot:title>
        {{ $listing->name }} | MotoHub
    </x-slot:title>

    {{-- 比較機能用のスクリプトを読み込み --}}
    <x-slot:scripts>
        <script src="{{ asset('js/compare/manager.js') }}"></script>
        <script src="{{ asset('js/compare/ui.js') }}"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="{{ asset('js/bikes/stats.js') }}"></script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    {{-- パンくずリスト --}}
    <div class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <nav class="flex text-xs font-bold text-gray-400" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 whitespace-nowrap overflow-x-auto scrollbar-hide">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    
                    {{-- メーカー --}}
                    @if($listing->manufacturer_id)
                        <li><span class="text-gray-300">＞</span></li>
                        <li>
                            <a href="{{ route('bikes.search', ['manufacturer_id' => $listing->manufacturer_id]) }}" class="hover:text-gray-600 transition-colors">
                                {{ $listing->maker }}
                            </a>
                        </li>
                    @endif

                    {{-- 車種 --}}
                    @if($listing->bike_model_id && $listing->bike_model_name)
                        <li><span class="text-gray-300">＞</span></li>
                        <li>
                            <a href="{{ route('bikes.search', ['manufacturer_id' => $listing->manufacturer_id, 'bike_model_id' => $listing->bike_model_id]) }}" class="hover:text-gray-600 transition-colors">
                                {{ $listing->bike_model_name }}
                            </a>
                        </li>
                    @endif

                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $listing->name }}</span></li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="bg-gray-50 min-h-screen py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="lg:grid lg:grid-cols-12 lg:gap-8">
                {{-- メインカラム（左側：画像・詳細） --}}
                <div class="lg:col-span-8 space-y-8">
                    
                    {{-- 1. 画像ギャラリー --}}
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                        {{-- メイン画像エリア --}}
                        <div class="aspect-[4/3] bg-gray-100 relative group overflow-hidden">
                            @if(!empty($listing->images) && count($listing->images) > 0)
                                {{-- A. 背景用（拡大・ぼかし） --}}
                                <div class="absolute inset-0 z-0">
                                    <img src="{{ $listing->images[0] }}" class="w-full h-full object-cover blur-2xl opacity-50 scale-110" aria-hidden="true">
                                </div>
                                
                                {{-- B. 表示用（元サイズ維持・中央配置） --}}
                                <div class="absolute inset-0 z-10 flex items-center justify-center p-1">
                                    <img src="{{ $listing->images[0] }}" alt="{{ $listing->name }}" class="max-w-full max-h-full object-contain shadow-sm">
                                </div>
                            @else
                                <div class="flex items-center justify-center h-full text-gray-300">
                                    <i data-lucide="image-off" class="w-12 h-12"></i>
                                </div>
                            @endif
                            
                            {{-- 画像枚数バッジ --}}
                            <div class="absolute bottom-4 right-4 z-20 bg-black/70 text-white px-3 py-1 rounded-full text-xs font-bold backdrop-blur-sm">
                                <i data-lucide="camera" class="w-3 h-3 inline mr-1"></i>
                                {{ count($listing->images ?? []) }}枚
                            </div>
                        </div>

                        {{-- サムネイルリスト（横スクロール） --}}
                        @if(!empty($listing->images) && count($listing->images) > 1)
                        <div class="flex gap-2 p-4 overflow-x-auto scrollbar-hide bg-white border-t border-gray-100">
                            @foreach($listing->images as $img)
                                <button class="shrink-0 w-20 h-20 rounded-lg overflow-hidden border-2 border-transparent hover:border-blue-600 transition-all bg-gray-50">
                                    <img src="{{ $img }}" class="w-full h-full object-cover">
                                </button>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- 2. 車両基本情報 --}}
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
                            <div>
                                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 leading-tight mb-2">{{ $listing->name }}</h1>
                                <div class="flex items-center gap-3 text-sm font-bold text-gray-500">
                                    <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded">{{ $listing->maker }}</span>
                                    {{-- ID表示を削除しました --}}
                                </div>
                            </div>
                            
                            {{-- お気に入り・比較ボタン --}}
                            <div class="flex items-center gap-2">
                                {{-- 修正: クラス名を compare-toggle-btn から compare-btn に変更 --}}
                                <button class="compare-btn w-12 h-12 rounded-full border border-gray-200 flex items-center justify-center text-gray-400 hover:bg-gray-50 transition-colors" data-id="{{ $listing->id }}">
                                    <i data-lucide="layers" class="w-5 h-5"></i>
                                </button>
                                <button class="wishlist-btn w-12 h-12 rounded-full border border-gray-200 flex items-center justify-center text-gray-400 hover:bg-gray-50 transition-colors" data-id="{{ $listing->id }}">
                                    <i data-lucide="heart" class="w-5 h-5"></i>
                                </button>
                            </div>
                        </div>

                        {{-- スペックグリッド --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
                            <div class="bg-gray-50 rounded-2xl p-4 text-center">
                                <div class="text-xs font-bold text-gray-400 mb-1">年式</div>
                                <div class="text-lg font-black text-gray-900">{{ $listing->model_year }}</div>
                            </div>
                            <div class="bg-gray-50 rounded-2xl p-4 text-center">
                                <div class="text-xs font-bold text-gray-400 mb-1">走行距離</div>
                                <div class="text-lg font-black text-gray-900">{{ $listing->mileage }}</div>
                            </div>
                            <div class="bg-gray-50 rounded-2xl p-4 text-center">
                                <div class="text-xs font-bold text-gray-400 mb-1">排気量</div>
                                <div class="text-lg font-black text-gray-900">{{ $listing->displacement }}</div>
                            </div>
                            <div class="bg-gray-50 rounded-2xl p-4 text-center">
                                <div class="text-xs font-bold text-gray-400 mb-1">修復歴</div>
                                <div class="text-lg font-black text-gray-900">{{ $listing->repair_history }}</div>
                            </div>
                        </div>

                        {{-- 説明文エリア --}}
                        <div class="prose prose-sm max-w-none text-gray-600">
                            <h3 class="text-lg font-black text-gray-900 mb-3">車両の状態・コメント</h3>
                            
                            @if(!empty($listing->description))
                                <div class="whitespace-pre-wrap leading-relaxed">
                                    {{ $listing->description }}
                                </div>
                            @else
                                <div class="leading-normal text-sm">
                                    <p class="mb-3">
                                        ご覧いただきありがとうございます。<br>
                                        <span class="font-bold text-gray-800">{{ $listing->maker }} {{ $listing->name }}</span> の掲載車両です。
                                    </p>

                                    <div class="bg-gray-50 rounded-xl p-4 mb-4 border border-gray-100">
                                        <ul class="space-y-1">
                                            <li class="flex gap-2">
                                                <span class="text-gray-400 text-xs w-16 pt-0.5">年式</span>
                                                <span class="font-bold text-gray-800">{{ $listing->model_year }}</span>
                                            </li>
                                            <li class="flex gap-2">
                                                <span class="text-gray-400 text-xs w-16 pt-0.5">走行距離</span>
                                                <span class="font-bold text-gray-800">{{ $listing->mileage }}</span>
                                            </li>
                                            <li class="flex gap-2">
                                                <span class="text-gray-400 text-xs w-16 pt-0.5">支払総額</span>
                                                <span class="font-black text-red-500">{{ $listing->total_price }} 万円</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <p class="mb-4">
                                        本車両は「<span class="font-bold">{{ $listing->shop_name }}</span>」にて販売中です。<br>
                                        車両の状態や見積もりの詳細については、ページ内の「在庫確認・見積もり」ボタンから販売店へ直接お問い合わせください。
                                    </p>
                                    
                                    <p class="text-xs text-gray-400 pt-4 border-t border-dashed border-gray-200">
                                        ※このコメントは車両データから自動生成されています。詳細は販売店にご確認ください。
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- ★相場分析チャート --}}
                    @if($listing->model_year && $listing->total_price)
                    {{-- 
                        JSにデータを渡すためのIDとデータ属性を追加
                        id="price-stats-container"
                        data-model-id: 車種ID
                        data-total-price: 車両価格
                    --}}
                    <div id="price-stats-container" 
                         data-model-id="{{ $listing->bike_model_id ?? '' }}" 
                         data-total-price="{{ $listing->total_price ?? 0 }}"
                         class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 overflow-hidden">
                         
                        <div class="flex items-center gap-2 mb-6">
                            <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                                <i data-lucide="bar-chart-2" class="w-5 h-5"></i>
                            </div>
                            <h3 class="text-lg font-black text-gray-900">市場価格分析</h3>
                        </div>

                        <!-- 統計サマリー -->
                        <div id="price-stats-loading" class="text-center py-10">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                            <p class="text-xs text-gray-400 font-bold mt-3">市場データを分析中...</p>
                        </div>

                        <div id="price-stats-content" class="hidden">
                            {{-- スマホ対策：パディングを小さく、文字サイズを調整 --}}
                            <div class="grid grid-cols-3 gap-2 sm:gap-4 mb-8">
                                <div class="bg-gray-50 rounded-xl p-2 sm:p-4 text-center border border-gray-100">
                                    <div class="text-[10px] font-bold text-gray-400 mb-1">相場平均</div>
                                    <div class="text-sm sm:text-xl font-black text-gray-800">
                                        <span id="stat-avg">---</span><span class="text-[10px] sm:text-xs ml-0.5">万円</span>
                                    </div>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-2 sm:p-4 text-center border border-gray-100">
                                    <div class="text-[10px] font-bold text-gray-400 mb-1">最安値</div>
                                    <div class="text-sm sm:text-xl font-black text-blue-600">
                                        <span id="stat-min">---</span><span class="text-[10px] sm:text-xs ml-0.5">万円</span>
                                    </div>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-2 sm:p-4 text-center border border-gray-100">
                                    <div class="text-[10px] font-bold text-gray-400 mb-1">最高値</div>
                                    <div class="text-sm sm:text-xl font-black text-red-500">
                                        <span id="stat-max">---</span><span class="text-[10px] sm:text-xs ml-0.5">万円</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- チャートキャンバス -->
                            <div class="relative h-64 w-full">
                                <canvas id="priceChart"></canvas>
                            </div>

                            <p class="text-[10px] text-gray-400 mt-4 text-right">
                                ※MotoHubに掲載中の「{{ $listing->name }}」全車両のデータから算出
                            </p>
                        </div>
                    </div>
                    @endif

                    {{-- 3. 販売店情報 --}}
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <h3 class="text-lg font-black text-gray-900 mb-6 flex items-center gap-2">
                            <i data-lucide="store" class="w-5 h-5 text-gray-400"></i>
                            販売店情報
                        </h3>
                        <div class="flex items-start gap-6">
                            <div class="w-16 h-16 bg-gray-100 rounded-full shrink-0 overflow-hidden flex items-center justify-center">
                                <i data-lucide="map-pin" class="w-8 h-8 text-gray-300"></i>
                            </div>
                            <div>
                                <div class="font-black text-xl text-gray-900 mb-2">{{ $listing->shop_name }}</div>
                                <div class="text-sm font-bold text-gray-500 space-y-1">
                                    <p>{{ $listing->shop_address ?? '住所情報なし' }}</p>
                                    <p>TEL: {{ $listing->shop_tel ?? '-' }}</p>
                                    <p>営業時間: {{ $listing->shop_hours ?? '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 4. 最近見た車両 --}}
                    <div id="history-widget-container" class="pt-8">
                        <div id="history-widget" class="hidden"></div>
                    </div>

                </div>

                {{-- サイドバー（右側：価格・CV・追従） --}}
                <div class="lg:col-span-4 mt-8 lg:mt-0">
                    <div class="sticky top-6 space-y-4">
                        <div class="bg-white rounded-3xl shadow-xl shadow-blue-900/5 border border-blue-100 p-6 sm:p-8">
                            <div class="text-center mb-6">
                                <div class="text-sm font-bold text-gray-400 mb-1">支払総額</div>
                                <div class="text-4xl font-black text-red-500 tracking-tight">
                                    {{ $listing->total_price }}
                                    <span class="text-sm text-gray-500 font-bold ml-1">万円</span>
                                </div>
                                @if($listing->price && $listing->price !== '-')
                                <div class="text-xs font-bold text-gray-400 mt-2">
                                    (車両本体価格: {{ $listing->price }}万円)
                                </div>
                                @endif
                            </div>

                            <div class="space-y-3">
                                <a href="{{ $listing->url }}" target="_blank" class="block w-full bg-red-600 hover:bg-red-500 text-white font-black text-center py-4 rounded-xl shadow-lg shadow-red-500/30 transition-all hover:-translate-y-1">
                                    在庫確認・見積もり
                                    <span class="block text-[10px] font-medium opacity-80 mt-0.5">（外部サイトへ移動します）</span>
                                </a>
                                
                                @if(!empty($listing->shop_tel) && $listing->shop_tel !== '-')
                                    <a href="tel:{{ str_replace('-', '', $listing->shop_tel) }}" class="block w-full bg-white border-2 border-gray-100 hover:border-blue-600 text-gray-700 hover:text-blue-600 font-bold text-center py-3 rounded-xl transition-all group">
                                        <span class="flex items-center justify-center gap-2">
                                            <i data-lucide="phone" class="w-5 h-5 group-hover:text-blue-600 transition-colors"></i>
                                            電話で問い合わせる
                                        </span>
                                        <span class="block text-xs font-normal text-gray-400 mt-0.5 group-hover:text-blue-500">{{ $listing->shop_tel }}</span>
                                    </a>
                                @else
                                    <button class="block w-full bg-gray-50 border-2 border-gray-50 text-gray-300 font-bold text-center py-3 rounded-xl cursor-not-allowed" disabled>
                                        電話番号なし
                                    </button>
                                @endif
                            </div>
                            
                            <div class="mt-6 pt-6 border-t border-gray-100 text-center">
                                <div class="text-[10px] font-bold text-gray-400">
                                    ※ 見積もり依頼は無料です。<br>
                                    MotoHubを見たとお伝えください。
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- スマホ用固定フッターCV --}}
    <div class="fixed bottom-0 left-0 w-full bg-white/90 backdrop-blur-md border-t border-gray-200 p-4 lg:hidden z-50 safe-area-bottom">
        <div class="flex gap-3">
            <div class="flex-1">
                <div class="text-[10px] font-bold text-gray-400">支払総額</div>
                <div class="text-xl font-black text-red-500">{{ $listing->total_price }}<span class="text-xs text-gray-500 ml-0.5">万円</span></div>
            </div>
            <a href="{{ $listing->url }}" target="_blank" class="flex-1 bg-red-600 text-white font-black flex items-center justify-center rounded-lg shadow-lg">
                見積もり依頼
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.HistoryManager) {
                const listingId = parseInt('{{ $listing->id }}');
                if (!isNaN(listingId)) {
                    HistoryManager.push(listingId);
                    HistoryManager.render('history-widget');
                }
            }
        });
    </script>
</x-layout>