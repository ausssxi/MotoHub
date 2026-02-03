<x-layout>
    <x-slot:title>
        {{ $listing->name }} | MotoHub
    </x-slot:title>

    {{-- ★追加: 構造化データ (JSON-LD) --}}
    {{-- これによりGoogle検索結果でリッチに表示されるようになります --}}
    <x-json-ld.product :listing="$listing" />
    <x-json-ld.breadcrumb :listing="$listing" />

    {{-- 比較機能・チャート・ローン計算用のスクリプト --}}
    <x-slot:scripts>
        <script src="{{ asset('js/compare/manager.js') }}"></script>
        <script src="{{ asset('js/compare/ui.js') }}"></script>
        <script src="{{ asset('js/bikes/stats.js') }}"></script>
        <script src="{{ asset('js/bikes/loan-simulator.js') }}"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 leading-tight mb-3">{{ $listing->name }}</h1>
                                
                                {{-- ★修正: メーカー、コンディション、都道府県を色分けバッジで表示 --}}
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-[10px] font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded uppercase">{{ $listing->maker }}</span>
                                    <span class="text-[10px] font-black text-green-600 bg-green-50 px-2 py-0.5 rounded uppercase">{{ $listing->condition }}</span>
                                    <span class="text-[10px] font-black text-purple-600 bg-purple-50 px-2 py-0.5 rounded uppercase">{{ $listing->prefecture }}</span>
                                </div>
                            </div>
                            
                            {{-- お気に入り・比較ボタン --}}
                            <div class="flex items-center gap-2">
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
                    @if($listing->model_year && is_numeric($listing->total_price))
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

                    @if(is_numeric($listing->total_price))
                    <div id="loan-simulator" data-total-price="{{ $listing->total_price }}" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mt-8">
                        <div class="flex items-center gap-2 mb-6">
                            <div class="p-2 bg-green-50 rounded-lg text-green-600">
                                <i data-lucide="calculator" class="w-5 h-5"></i>
                            </div>
                            <h3 class="text-lg font-black text-gray-900">ローンシミュレーション</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            {{-- 入力エリア --}}
                            <div class="space-y-6">
                                {{-- 頭金 --}}
                                <div>
                                    <div class="flex justify-between mb-2">
                                        <label class="text-xs font-bold text-gray-500">頭金</label>
                                        <span class="text-xs font-black text-gray-900"><span id="disp-down-payment">0</span>万円</span>
                                    </div>
                                    <input type="range" id="loan-down-payment" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-green-600" 
                                        min="0" max="{{ floor($listing->total_price) }}" step="1" value="0">
                                </div>

                                {{-- 支払回数 --}}
                                <div>
                                    <label class="text-xs font-bold text-gray-500 block mb-2">支払回数</label>
                                    <div class="relative">
                                        <select id="loan-installments" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm font-bold rounded-xl focus:ring-green-500 focus:border-green-500 block p-3 appearance-none">
                                            <option value="6">6回 (半年)</option>
                                            <option value="12">12回 (1年)</option>
                                            <option value="24">24回 (2年)</option>
                                            <option value="36" selected>36回 (3年)</option>
                                            <option value="48">48回 (4年)</option>
                                            <option value="60">60回 (5年)</option>
                                            <option value="72">72回 (6年)</option>
                                            <option value="84">84回 (7年)</option>
                                        </select>
                                        <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                                    </div>
                                </div>

                                {{-- 金利 --}}
                                <div>
                                    <label class="text-xs font-bold text-gray-500 block mb-2">実質年率 (%)</label>
                                    <input type="number" id="loan-rate" value="5.9" step="0.1" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm font-bold rounded-xl focus:ring-green-500 focus:border-green-500 block p-3">
                                </div>
                                <input type="hidden" id="loan-bonus" value="0">
                            </div>

                            {{-- 結果表示エリア --}}
                            <div class="bg-green-50 rounded-2xl p-6 flex flex-col justify-center items-center text-center border border-green-100">
                                <p class="text-xs font-bold text-green-600 mb-1">月々のお支払い目安</p>
                                <div class="text-4xl font-black text-green-700 tracking-tight mb-2">
                                    <span id="disp-monthly-payment">0</span><span class="text-sm ml-1">円</span>
                                </div>
                                
                                <div class="w-full border-t border-green-200/50 my-4"></div>
                                
                                <div class="w-full flex justify-between text-xs font-bold text-gray-600 mb-1">
                                    <span>ローン元金</span>
                                    <span><span id="disp-loan-amount">0</span>万円</span>
                                </div>
                                <div class="w-full flex justify-between text-xs font-bold text-gray-600">
                                    <span>支払総額(目安)</span>
                                    <span>約<span id="disp-total-payment">0</span>万円</span>
                                </div>
                            </div>
                        </div>
                        
                        <p class="text-[10px] text-gray-400 mt-4 text-right">
                            ※シミュレーション結果は概算です。実際の契約内容や金利により異なります。
                        </p>
                    </div>
                    @endif

                    {{-- 3. 販売店情報 --}}
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <h3 class="text-lg font-black text-gray-900 mb-6 flex items-center gap-2">
                            <i data-lucide="store" class="w-5 h-5 text-gray-400"></i>
                            販売店情報
                        </h3>
                        <div class="flex items-start gap-6">
                            <div class="w-16 h-16 rounded-full shrink-0 overflow-hidden flex items-center justify-center border border-gray-100">
                                @if(!empty($listing->shop_image))
                                    <img src="{{ $listing->shop_image }}" alt="{{ $listing->shop_name }}" class="w-full h-full object-cover">
                                @else
                                    <i data-lucide="map-pin" class="w-8 h-8 text-gray-300"></i>
                                @endif
                            </div>
                            <div>
                                <div class="font-black text-xl text-gray-900 mb-1">
                                    @if(isset($listing->shop_id))
                                        <a href="{{ route('shops.show', $listing->shop_id) }}" class="hover:text-blue-600 hover:underline decoration-2 underline-offset-4 transition-colors">
                                            {{ $listing->shop_name }}
                                        </a>
                                    @else
                                        {{ $listing->shop_name }}
                                    @endif
                                </div>
                                <div class="text-sm font-bold text-gray-500 space-y-1">
                                    {{-- ★修正: スマホ画面のみリスト内に住所を表示 --}}
                                    <p>{{ $listing->shop_address ?? '住所情報なし' }}</p>
                                    <p>TEL: {{ $listing->shop_tel ?? '-' }}</p>
                                    <p>営業時間: {{ $listing->shop_hours ?? '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 4. 類似車両（同じ車種の他の車両） --}}
                    @if(!empty($relatedListings) && count($relatedListings) > 0)
                    <div class="pt-8 border-t border-gray-200">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center gap-2">
                                <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                                    <i data-lucide="layers" class="w-5 h-5"></i>
                                </div>
                                <h3 class="text-lg font-black text-gray-900">この車種の他の車両</h3>
                            </div>
                            
                            <a href="{{ route('bikes.search', ['bike_model_id' => $listing->bike_model_id]) }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors">
                                すべて見る <i data-lucide="chevron-right" class="w-4 h-4 inline-block align-text-bottom"></i>
                            </a>
                        </div>
                        
                        <div class="flex gap-4 overflow-x-auto pb-4 snap-x snap-mandatory scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
                            @foreach($relatedListings as $related)
                            <a href="{{ route('bikes.show', $related['id']) }}" class="snap-start shrink-0 w-40 sm:w-48 bg-white border border-gray-100 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all group block">
                                <div class="aspect-[4/3] bg-gray-50 relative overflow-hidden">
                                    @if(!empty($related['images']) && isset($related['images'][0]))
                                        <img src="{{ $related['images'][0] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="">
                                    @else
                                        <div class="flex items-center justify-center h-full text-gray-300">
                                            <i data-lucide="image-off" class="w-8 h-8"></i>
                                        </div>
                                    @endif
                                    
                                    <div class="absolute bottom-2 right-2 bg-black/60 backdrop-blur-sm text-white px-2 py-0.5 rounded text-[10px] font-black">
                                        {{ $related['total_price'] }}万円
                                    </div>
                                </div>
                                <div class="p-3">
                                    <div class="text-[10px] font-bold text-gray-400 mb-0.5 flex items-center gap-1">
                                        <span class="bg-gray-100 px-1.5 rounded">{{ $related['model_year'] }}</span>
                                        <span>{{ $related['mileage'] }}</span>
                                    </div>
                                    <h4 class="text-xs font-black text-gray-800 leading-tight line-clamp-2 mb-2 h-[2.5em] group-hover:text-blue-600 transition-colors">
                                        {{ $related['name'] }}
                                    </h4>
                                    <div class="flex items-end justify-between border-t border-gray-100 pt-2">
                                        <div class="text-[10px] text-gray-400 truncate w-full">{{ $related['prefecture'] }}</div>
                                    </div>
                                </div>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- 5. 最近見た車両 --}}
                    <div id="history-widget-container" class="pt-8">
                        <div id="history-widget" class="hidden"></div>
                    </div>

                    {{-- ★追加: 関連条件へのSEOリンク集 --}}
                    @if(!empty($seoLinks))
                    <div class="pt-12 mt-4">
                        <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">
                            関連する検索条件
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach($seoLinks as $link)
                                <a href="{{ $link['url'] }}" class="text-xs font-bold text-gray-600 bg-gray-100 hover:bg-blue-50 hover:text-blue-600 px-3 py-2 rounded-lg transition-colors">
                                    {{ $link['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

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