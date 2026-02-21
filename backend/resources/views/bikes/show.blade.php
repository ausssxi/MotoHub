<x-layout>
    <x-slot:title>
        {{ $listing->name }} | MotoHub
    </x-slot:title>

    <x-slot:metaDescription>
        {{ mb_substr(strip_tags($listing->description ?? "{$listing->maker} {$listing->name} の詳細ページです。販売店:{$listing->shop_name} 価格:{$listing->total_price}万円"), 0, 120) }}...
    </x-slot:metaDescription>

    <x-slot:scripts>
        <script src="{{ asset('js/compare/manager.js') }}"></script>
        <script src="{{ asset('js/compare/ui.js') }}"></script>
        <script src="{{ asset('js/bikes/loan-simulator.js') }}"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        <script>
            window.bikeModelStats = {!! json_encode($stats ?? [], JSON_HEX_TAG) !!};
        </script>
        <script src="{{ asset('js/bikes/model_detail.js') }}"></script>
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
                    
                    @if($listing->manufacturer_id)
                        <li><span class="text-gray-300">＞</span></li>
                        <li>
                            <a href="{{ route('bikes.search', ['manufacturer_id' => $listing->manufacturer_id]) }}" class="hover:text-gray-600 transition-colors">
                                {{ $listing->maker }}
                            </a>
                        </li>
                    @endif

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
    
    {{-- シームレス・ナビゲーション（JSで検索経由の場合のみ表示） --}}
    <div id="search-nav-bar" class="hidden bg-gray-900 border-b border-gray-800 shadow-md sticky top-[64px] z-[50]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex justify-between items-center text-xs font-bold">
            <a id="nav-back-list" href="#" class="flex items-center gap-1.5 text-gray-300 hover:text-white transition-colors">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> 
                <span class="hidden sm:inline">検索結果に戻る</span>
                <span class="sm:hidden">一覧へ</span>
            </a>
            <div class="flex items-center gap-6 sm:gap-8">
                <a id="nav-prev-bike" href="#" class="flex items-center gap-1.5 text-gray-600 pointer-events-none transition-colors">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i> 
                    <span class="hidden sm:inline">前の車両</span>
                    <span class="sm:hidden">前へ</span>
                </a>
                <a id="nav-next-bike" href="#" class="flex items-center gap-1.5 text-gray-600 pointer-events-none transition-colors">
                    <span class="hidden sm:inline">次の車両</span>
                    <span class="sm:hidden">次へ</span>
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
    </div>
    
    <div class="bg-gray-50 min-h-screen py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="lg:grid lg:grid-cols-12 lg:gap-8">
                {{-- メインカラム --}}
                <div class="lg:col-span-8 space-y-8">
                    
                    {{-- 1. 画像ギャラリー --}}
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="aspect-[4/3] bg-gray-100 relative group overflow-hidden">
                            @if(!empty($listing->images) && count($listing->images) > 0)
                                <div class="absolute inset-0 z-0">
                                    <img src="{{ $listing->images[0] }}" 
                                        onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop'; this.classList.add('grayscale', 'opacity-50');"
                                        class="w-full h-full object-cover blur-2xl opacity-50 scale-110" aria-hidden="true">
                                </div>
                                <div class="absolute inset-0 z-10 flex items-center justify-center p-1">
                                    <img src="{{ $listing->images[0] }}" alt="{{ $listing->name }}" 
                                        onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop'; this.classList.add('grayscale', 'opacity-50');"
                                        class="max-w-full max-h-full object-contain shadow-sm">
                                </div>
                            @else
                                <img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop" 
                                     class="w-full h-full object-cover grayscale opacity-50" alt="No Image">
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <i data-lucide="image-off" class="w-8 h-8 text-white/50"></i>
                                </div>
                            @endif
                            
                            <div class="absolute bottom-4 right-4 z-20 bg-black/70 text-white px-3 py-1 rounded-full text-xs font-bold backdrop-blur-sm">
                                <i data-lucide="camera" class="w-3 h-3 inline mr-1"></i>
                                {{ count($listing->images ?? []) }}枚
                            </div>
                        </div>

                        @if(!empty($listing->images) && count($listing->images) > 1)
                        <div class="flex gap-2 p-4 overflow-x-auto scrollbar-hide bg-white border-t border-gray-100">
                            @foreach($listing->images as $img)
                                <button class="shrink-0 w-20 h-20 rounded-lg overflow-hidden border-2 border-transparent hover:border-blue-600 transition-all bg-gray-50">
                                    <img src="{{ $img }}" 
                                        onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop'; this.classList.add('grayscale', 'opacity-50');"
                                        class="w-full h-full object-cover">
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
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-[10px] font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded uppercase">{{ $listing->maker }}</span>
                                    <span class="text-[10px] font-black text-orange-600 bg-orange-50 px-2 py-0.5 rounded uppercase">{{ $listing->category }}</span>
                                    <span class="text-[10px] font-black text-green-600 bg-green-50 px-2 py-0.5 rounded uppercase">{{ $listing->condition }}</span>
                                    <span class="text-[10px] font-black text-purple-600 bg-purple-50 px-2 py-0.5 rounded uppercase">{{ $listing->prefecture }}</span>
                                </div>
                                {{-- DBから取得したハッシュタグエリア --}}
                                @if($tags && $tags->count() > 0)
                                <div class="flex flex-wrap gap-2 mt-4">
                                    @foreach($tags as $tag)
                                        <a href="{{ route('bikes.search', ['tag' => $tag->slug]) }}" class="inline-flex items-center px-3 py-1 bg-gray-50 hover:bg-blue-50 text-gray-600 hover:text-blue-700 text-xs font-bold rounded-full transition-colors border border-gray-100 hover:border-blue-200 shadow-sm">
                                            <span class="text-blue-400 mr-0.5">#</span>{{ $tag->name }}
                                        </a>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <button class="compare-btn w-12 h-12 rounded-full border border-gray-200 flex items-center justify-center text-gray-400 hover:bg-gray-50 transition-colors" data-id="{{ $listing->id }}">
                                    <i data-lucide="layers" class="w-5 h-5"></i>
                                </button>
                                <button class="wishlist-btn w-12 h-12 rounded-full border border-gray-200 flex items-center justify-center text-gray-400 hover:bg-gray-50 transition-colors" data-id="{{ $listing->id }}">
                                    <i data-lucide="heart" class="w-5 h-5"></i>
                                </button>
                            </div>
                        </div>

                        @if(isset($stats['rank']) && $stats['rank'] !== 'unknown')
                        <div class="mb-6">
                            <div class="flex items-center gap-3 bg-gray-50 rounded-2xl p-4 border border-gray-100">
                                <div class="flex-shrink-0">
                                    @if($stats['rank'] === 'S')
                                        <div class="w-12 h-12 rounded-full bg-red-600 text-white flex items-center justify-center font-black text-xl shadow-lg shadow-red-200">S</div>
                                    @elseif($stats['rank'] === 'A')
                                        <div class="w-12 h-12 rounded-full bg-green-500 text-white flex items-center justify-center font-black text-xl shadow-lg shadow-green-200">A</div>
                                    @elseif($stats['rank'] === 'B')
                                        <div class="w-12 h-12 rounded-full bg-blue-500 text-white flex items-center justify-center font-black text-xl shadow-lg shadow-blue-200">B</div>
                                    @else
                                        <div class="w-12 h-12 rounded-full bg-gray-400 text-white flex items-center justify-center font-black text-xl">C</div>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">AI Market Price</p>
                                    <div class="text-sm font-bold text-gray-800">
                                        @if($stats['rank'] === 'S')
                                            <span class="text-red-600 text-base">激アツ！相場より {{ abs($stats['diff']) }}万円 お買い得</span>
                                        @elseif($stats['rank'] === 'A')
                                            <span class="text-green-600">お買い得！平均より {{ abs($stats['diff']) }}万円 安い</span>
                                        @elseif($stats['rank'] === 'B')
                                            <span class="text-blue-600">適正価格です（平均との差 {{ abs($stats['diff']) }}万円）</span>
                                        @else
                                            <span class="text-gray-600">こだわり車両（平均より高め）</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

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
                                            <li class="flex gap-2"><span class="text-gray-400 text-xs w-16 pt-0.5">年式</span><span class="font-bold text-gray-800">{{ $listing->model_year }}</span></li>
                                            <li class="flex gap-2"><span class="text-gray-400 text-xs w-16 pt-0.5">走行距離</span><span class="font-bold text-gray-800">{{ $listing->mileage }}</span></li>
                                            <li class="flex gap-2"><span class="text-gray-400 text-xs w-16 pt-0.5">支払総額</span><span class="font-black text-red-500">{{ $listing->total_price }} 万円</span></li>
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

                    {{-- 相場分析チャート --}}
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

                        <div id="price-stats-loading" class="text-center py-10">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                            <p class="text-xs text-gray-400 font-bold mt-3">市場データを分析中...</p>
                        </div>

                        <div id="price-stats-content" class="hidden">
                            <div class="grid grid-cols-3 gap-2 sm:gap-4 mb-8">
                                <div class="bg-gray-50 rounded-xl p-3 sm:p-4 text-center border border-gray-100">
                                    <div class="text-[10px] font-bold text-gray-400 mb-1">相場平均</div>
                                    <div class="text-base sm:text-xl font-black text-gray-800"><span id="stat-avg">---</span><span class="text-xs ml-0.5">万円</span></div>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-3 sm:p-4 text-center border border-gray-100">
                                    <div class="text-[10px] font-bold text-gray-400 mb-1">最安値</div>
                                    <div class="text-base sm:text-xl font-black text-blue-600"><span id="stat-min">---</span><span class="text-xs ml-0.5">万円</span></div>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-3 sm:p-4 text-center border border-gray-100">
                                    <div class="text-[10px] font-bold text-gray-400 mb-1">最高値</div>
                                    <div class="text-base sm:text-xl font-black text-red-500"><span id="stat-max">---</span><span class="text-xs ml-0.5">万円</span></div>
                                </div>
                            </div>
                            
                            <div class="relative h-64 w-full">
                                <canvas id="priceChart"></canvas>
                            </div>

                            <p class="text-[10px] text-gray-400 mt-4 text-right">※MotoHubに掲載中の「{{ $listing->name }}」全車両のデータから算出</p>
                            @if($listing->bike_model_id)
                            <div class="mt-8 pt-6 border-t border-gray-100 text-center">
                                <a href="{{ route('bikes.model_detail', $listing->bike_model_id) }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-blue-50 to-indigo-50 hover:from-blue-100 hover:to-indigo-100 text-blue-700 font-bold rounded-xl transition-all shadow-sm border border-blue-100 group">
                                    <i data-lucide="coins" class="w-4 h-4 text-yellow-500"></i>
                                    <span>この車種の買取相場・リセール情報を見る</span>
                                    <i data-lucide="chevron-right" class="w-4 h-4 text-blue-400 group-hover:translate-x-1 transition-transform"></i>
                                </a>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- オーナーレビューセクション（0件でも表示して投稿を促す） --}}
                    @if(isset($reviews))
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mt-8">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 mb-6">
                            <div class="flex items-center gap-2">
                                <div class="p-2 bg-yellow-50 rounded-lg text-yellow-600 shrink-0">
                                    <i data-lucide="message-square-quote" class="w-5 h-5"></i>
                                </div>
                                <h3 class="text-base sm:text-lg font-black text-gray-900 leading-tight">この車種のオーナーレビュー</h3>
                            </div>
                            <div class="self-end sm:self-auto border-t sm:border-t-0 border-gray-100 pt-2 sm:pt-0 w-full sm:w-auto text-right flex flex-wrap gap-3 justify-end items-center">
                                {{-- ★追加：レビューを書くボタン（モーダルを開く） --}}
                                <button type="button" onclick="openReviewModal()" class="inline-flex items-center text-xs font-bold bg-yellow-400 hover:bg-yellow-500 text-yellow-900 px-3 py-2 rounded-lg transition-colors shadow-sm active:scale-95">
                                    <i data-lucide="pen-line" class="w-3.5 h-3.5 mr-1"></i> レビューを書く
                                </button>
                                
                                @if($reviews->isNotEmpty())
                                <a href="{{ route('bikes.model_detail', $listing->bike_model_id) }}#reviews" class="inline-flex items-center text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors py-2">
                                    すべて見る <i data-lucide="chevron-right" class="w-4 h-4 ml-0.5"></i>
                                </a>
                                @endif
                            </div>
                        </div>

                        <div class="space-y-4" id="review-list-container">
                            @forelse($reviews as $review)
                                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex items-center gap-2">
                                            <div class="flex text-yellow-400">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <i data-lucide="star" class="w-3.5 h-3.5 {{ $i <= $review->rating ? 'fill-current' : 'text-gray-300' }}"></i>
                                                @endfor
                                            </div>
                                            <span class="text-xs font-black text-gray-800">{{ $review->title }}</span>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-600 leading-relaxed line-clamp-3 mb-3">
                                        {{ $review->body }}
                                    </p>
                                    <div class="flex justify-between items-center text-[10px] text-gray-400 font-bold">
                                        <span class="flex items-center gap-1">
                                            <i data-lucide="user" class="w-3 h-3"></i> {{ $review->nickname ?? '匿名ユーザー' }}
                                        </span>
                                        <span>{{ $review->created_at->format('Y年m月') }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-8 bg-gray-50 rounded-2xl border border-gray-100 border-dashed" id="no-review-msg">
                                    <i data-lucide="message-circle" class="w-8 h-8 text-gray-300 mx-auto mb-2"></i>
                                    <p class="text-xs font-bold text-gray-500 leading-relaxed">まだレビューがありません。<br>あなたが最初のレビューを書いてみませんか？</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                    @endif

                    {{-- ローンシミュレーター --}}
                    @if(is_numeric($listing->total_price))
                    <div id="loan-simulator" data-total-price="{{ $listing->total_price }}" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mt-8">
                        <div class="flex items-center gap-2 mb-6">
                            <div class="p-2 bg-green-50 rounded-lg text-green-600">
                                <i data-lucide="calculator" class="w-5 h-5"></i>
                            </div>
                            <h3 class="text-lg font-black text-gray-900">ローンシミュレーション</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-6">
                                <div>
                                    <div class="flex justify-between mb-2">
                                        <label class="text-xs font-bold text-gray-500">頭金</label>
                                        <span class="text-xs font-black text-gray-900"><span id="disp-down-payment">0</span>万円</span>
                                    </div>
                                    <input type="range" id="loan-down-payment" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-green-600" 
                                        min="0" max="{{ floor($listing->total_price) }}" step="1" value="0">
                                </div>
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
                                <div>
                                    <label class="text-xs font-bold text-gray-500 block mb-2">実質年率 (%)</label>
                                    <input type="number" id="loan-rate" value="5.9" step="0.1" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm font-bold rounded-xl focus:ring-green-500 focus:border-green-500 block p-3">
                                </div>
                                <input type="hidden" id="loan-bonus" value="0">
                            </div>
                            <div class="bg-green-50 rounded-2xl p-6 flex flex-col justify-center items-center text-center border border-green-100">
                                <p class="text-xs font-bold text-green-600 mb-1">月々のお支払い目安</p>
                                <div class="text-4xl font-black text-green-700 tracking-tight mb-2">
                                    <span id="disp-monthly-payment">0</span><span class="text-sm ml-1">円</span>
                                </div>
                                <div class="w-full border-t border-green-200/50 my-4"></div>
                                <div class="w-full flex justify-between text-xs font-bold text-gray-600 mb-1">
                                    <span>ローン元金</span><span><span id="disp-loan-amount">0</span>万円</span>
                                </div>
                                <div class="w-full flex justify-between text-xs font-bold text-gray-600">
                                    <span>支払総額(目安)</span><span>約<span id="disp-total-payment">0</span>万円</span>
                                </div>
                            </div>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-4 text-right">※シミュレーション結果は概算です。実際の契約内容や金利により異なります。</p>
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
                                    <img src="{{ $listing->shop_image }}" alt="{{ $listing->shop_name }}" 
                                        onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop'; this.classList.add('grayscale', 'opacity-50');"
                                        class="w-full h-full object-cover">
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
                                @if(!empty($listing->shop_address))
                                <p class="hidden sm:block text-xs font-bold text-gray-400 mb-3">
                                    {{ $listing->shop_address }}
                                </p>
                                @endif
                                <div class="text-sm font-bold text-gray-500 space-y-1">
                                    <p class="sm:hidden">{{ $listing->shop_address ?? '住所情報なし' }}</p>
                                    <p>TEL: {{ $listing->shop_tel ?? '-' }}</p>
                                    <p>営業時間: {{ $listing->shop_hours ?? '-' }}</p>
                                </div>
                            </div>
                        </div>
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
            @include('bikes.partials.recommendations')
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

    <style>
        /* スクロールバーを隠す（スマホで綺麗に見せるため） */
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
    {{-- ★ここから追加: レビュー投稿モーダル --}}
    @if($listing->bike_model_id)
    <div id="review-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 sm:p-0">
        {{-- 背景の黒いオーバーレイ --}}
        <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="closeReviewModal()"></div>
        
        {{-- モーダル本体 --}}
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all scale-95 opacity-0 duration-300" id="review-modal-content">
            
            <div class="p-6 sm:p-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-black text-gray-900 flex items-center gap-2">
                        <i data-lucide="pen-line" class="w-5 h-5 text-yellow-500"></i>
                        {{ $listing->bike_model_name ?? 'この車種' }}のレビュー
                    </h3>
                    <button type="button" onclick="closeReviewModal()" class="text-gray-400 hover:text-gray-600 bg-gray-50 rounded-full p-2 transition-colors">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                
                {{-- 送信成功時のメッセージ（最初は非表示） --}}
                <div id="review-success-msg" class="hidden text-center py-10">
                    <div class="w-16 h-16 bg-green-100 text-green-500 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="check" class="w-8 h-8"></i>
                    </div>
                    <h4 class="text-lg font-black text-gray-900 mb-2">投稿ありがとうございました！</h4>
                    <p class="text-xs text-gray-500 font-bold">あなたの声が次のオーナーの参考になります。</p>
                </div>

                {{-- 投稿フォーム --}}
                {{-- action関数を使って、ルート名に依存せず安全にURLを生成します --}}
                <form id="review-form" action="{{ action([\App\Http\Controllers\Bike\BikeController::class, 'storeReview'], ['id' => $listing->bike_model_id]) }}" class="space-y-5">
                    @csrf
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 sm:col-span-1">
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">ニックネーム</label>
                            <input type="text" name="nickname" required maxlength="50" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-yellow-400 focus:bg-white outline-none transition-all placeholder:text-gray-300" placeholder="例：モト太郎">
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">総合評価</label>
                            <div class="flex items-center gap-1.5 h-11" id="star-rating">
                                <input type="hidden" name="rating" id="rating-value" value="5" required>
                                @for($i = 1; $i <= 5; $i++)
                                    <button type="button" class="star-btn text-yellow-400 focus:outline-none transition-transform hover:scale-110 active:scale-90" data-val="{{ $i }}">
                                        <i data-lucide="star" class="w-7 h-7 fill-current drop-shadow-sm"></i>
                                    </button>
                                @endfor
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">タイトル</label>
                        <input type="text" name="title" required maxlength="100" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-yellow-400 focus:bg-white outline-none transition-all placeholder:text-gray-300" placeholder="一言でいうとどんなバイク？">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">レビュー本文</label>
                        <textarea name="body" required rows="4" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-yellow-400 focus:bg-white outline-none transition-all resize-none placeholder:text-gray-300 leading-relaxed" placeholder="足つき、燃費、取り回しなど、実際に乗ってみた感想や良い点・悪い点を教えてください！"></textarea>
                    </div>

                    <div id="review-error" class="hidden text-xs text-red-600 font-bold bg-red-50 border border-red-100 p-3 rounded-xl flex items-start gap-2">
                        <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                        <span id="review-error-text"></span>
                    </div>

                    <div class="pt-4 mt-4 border-t border-gray-100">
                        <button type="submit" id="review-submit-btn" class="w-full bg-yellow-400 hover:bg-yellow-500 text-yellow-900 font-black py-3.5 rounded-xl transition-all shadow-lg shadow-yellow-400/20 active:scale-[0.98] flex items-center justify-center gap-2 text-sm">
                            <i data-lucide="send" class="w-4 h-4"></i>
                            この内容で投稿する
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- モーダル制御 ＆ Ajax送信用スクリプト --}}
    <script>
        let reviewRating = 5;

        // モーダルを開く
        function openReviewModal() {
            const modal = document.getElementById('review-modal');
            const content = document.getElementById('review-modal-content');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            // 少し遅らせてフワッと出す
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        // モーダルを閉じる
        function closeReviewModal() {
            const modal = document.getElementById('review-modal');
            const content = document.getElementById('review-modal-content');
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 300);
        }

        document.addEventListener('DOMContentLoaded', () => {
            // 星の評価UIの制御
            const stars = document.querySelectorAll('.star-btn');
            const ratingInput = document.getElementById('rating-value');
            
            stars.forEach(btn => {
                btn.addEventListener('click', () => {
                    reviewRating = parseInt(btn.dataset.val);
                    ratingInput.value = reviewRating;
                    
                    stars.forEach((s, idx) => {
                        const icon = s.querySelector('i');
                        if (idx < reviewRating) {
                            icon.classList.add('fill-current');
                            s.classList.add('text-yellow-400');
                            s.classList.remove('text-gray-300');
                        } else {
                            icon.classList.remove('fill-current');
                            s.classList.remove('text-yellow-400');
                            s.classList.add('text-gray-300');
                        }
                    });
                });
            });

            // フォームのAjax送信処理
            const form = document.getElementById('review-form');
            if (form) {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    const btn = document.getElementById('review-submit-btn');
                    const errorDiv = document.getElementById('review-error');
                    const errorText = document.getElementById('review-error-text');
                    const successMsg = document.getElementById('review-success-msg');
                    
                    const originalBtnText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> 投稿しています...';
                    if (window.lucide) window.lucide.createIcons();
                    errorDiv.classList.add('hidden');
                    
                    const formData = new FormData(form);
                    const data = Object.fromEntries(formData.entries());
                    
                    try {
                        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                        const token = csrfMeta ? csrfMeta.content : '';
                        
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token
                            },
                            body: JSON.stringify(data)
                        });
                        
                        if (!response.ok) {
                            const errData = await response.json();
                            // バリデーションエラーのメッセージを組み立て
                            let msg = errData.message || '通信エラーが発生しました。';
                            if (errData.errors) {
                                msg = Object.values(errData.errors).map(e => e.join('\n')).join('<br>');
                            }
                            throw new Error(msg);
                        }
                        
                        const result = await response.json();
                        
                        // 送信成功！UIを切り替え
                        form.classList.add('hidden');
                        successMsg.classList.remove('hidden');
                        
                        // 裏でレビュー一覧に即時追加 (Optimistic UI)
                        appendReviewToList(result.review);
                        
                        // 2秒後にモーダルを自動で閉じてリセット
                        setTimeout(() => {
                            closeReviewModal();
                            setTimeout(() => {
                                form.reset();
                                form.classList.remove('hidden');
                                successMsg.classList.add('hidden');
                                btn.disabled = false;
                                btn.innerHTML = originalBtnText;
                                // 星を5に戻す
                                stars[4].click();
                            }, 500);
                        }, 2000);
                        
                    } catch (error) {
                        errorText.innerHTML = error.message;
                        errorDiv.classList.remove('hidden');
                        btn.disabled = false;
                        btn.innerHTML = originalBtnText;
                        if (window.lucide) window.lucide.createIcons();
                    }
                });
            }
        });

        // 投稿成功後、画面のリストに新しいレビューを挿入する関数
        function appendReviewToList(review) {
            if (!review) return;
            const container = document.getElementById('review-list-container');
            const noMsg = document.getElementById('no-review-msg');
            if (noMsg) noMsg.remove(); // 「まだレビューがありません」を消す

            let starsHtml = '';
            for(let i=1; i<=5; i++) {
                starsHtml += `<i data-lucide="star" class="w-3.5 h-3.5 ${i <= review.rating ? 'fill-current' : 'text-gray-300'}"></i>`;
            }

            const html = `
                <div class="p-4 bg-yellow-50/80 border-yellow-200 rounded-2xl border transition-all animate-in fade-in slide-in-from-top-4 duration-500 shadow-sm">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex items-center gap-2">
                            <div class="flex text-yellow-400">
                                ${starsHtml}
                            </div>
                            <span class="text-xs font-black text-gray-800">${review.title}</span>
                            <span class="text-[9px] font-black bg-yellow-400 text-yellow-900 px-1.5 py-0.5 rounded ml-2 shadow-sm">NEW</span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-700 leading-relaxed line-clamp-3 mb-3">
                        ${review.body}
                    </p>
                    <div class="flex justify-between items-center text-[10px] text-gray-400 font-bold">
                        <span class="flex items-center gap-1">
                            <i data-lucide="user" class="w-3 h-3 text-gray-300"></i> ${review.nickname || '匿名ユーザー'}
                        </span>
                        <span>${review.created_at}</span>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('afterbegin', html);
            if (window.lucide) window.lucide.createIcons();
        }
    </script>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.HistoryManager) {
                // 1. ログイン状態の確認
                const authMeta = document.querySelector('meta[name="auth-check"]');
                const bodyLoggedIn = document.body.dataset.loggedIn === 'true';
                const isLoggedIn = (authMeta && authMeta.content === 'true') || bodyLoggedIn;
                
                const listingId = parseInt('{{ $listing->id }}');
                
                if (!isNaN(listingId)) {
                    // 2. マネージャーの初期化完了を待つ
                    HistoryManager.init(isLoggedIn).then(() => {
                        // 3. 履歴に「現在の車両」を追加し、それが終わるのを待つ
                        HistoryManager.push(listingId).then(() => {
                            // 4. 追加が終わってから、描画処理を実行する（現在の車両は除外）
                            HistoryManager.render('history-widget', listingId).then(() => {
                                const widget = document.getElementById('history-widget');
                                // 5. 過去の履歴が1件以上あれば、セクション全体を表示する
                                if (widget && widget.children.length > 0) {
                                    document.getElementById('history-section').classList.remove('hidden');
                                }
                            });
                        });
                    });
                }
            }

            // シームレス・ナビゲーションの制御処理
            const stateStr = sessionStorage.getItem('motohub_search_state');
            if (stateStr) {
                try {
                    const state = JSON.parse(stateStr);
                    const currentId = {{ $listing->id }};
                    // 現在のIDが、記憶している検索結果リストの何番目にあるか探す
                    const currentIndex = state.ids.indexOf(currentId);
                    
                    if (currentIndex !== -1) {
                        // 検索結果から来たことが確認できたので、ナビゲーションバーを表示
                        document.getElementById('search-nav-bar').classList.remove('hidden');
                        
                        // 「一覧に戻る」ボタンのURLを復元
                        if(state.listUrl) {
                            document.getElementById('nav-back-list').href = state.listUrl;
                        }
                        
                        // 「前の車両」ボタンの有効化
                        const prevBtn = document.getElementById('nav-prev-bike');
                        if (currentIndex > 0) {
                            prevBtn.href = '/bikes/' + state.ids[currentIndex - 1];
                            prevBtn.classList.remove('text-gray-600', 'pointer-events-none');
                            prevBtn.classList.add('text-white', 'hover:text-blue-400');
                        }
                        
                        // 「次の車両」ボタンの有効化
                        const nextBtn = document.getElementById('nav-next-bike');
                        if (currentIndex < state.ids.length - 1) {
                            nextBtn.href = '/bikes/' + state.ids[currentIndex + 1];
                            nextBtn.classList.remove('text-gray-600', 'pointer-events-none');
                            nextBtn.classList.add('text-white', 'hover:text-blue-400');
                        }
                    }
                } catch(e) {
                    console.error('Search state parsing error', e);
                }
            }
        });
    </script>
</x-layout>