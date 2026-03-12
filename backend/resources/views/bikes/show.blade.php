<x-layout>
    <x-slot:title>
        {{ $listing->name }} | MotoHub
    </x-slot:title>

    <x-slot:metaDescription>
        {{ mb_substr(strip_tags($listing->description ?? "{$listing->maker} {$listing->name} の詳細ページです。販売店:{$listing->shop_name} 価格:{$listing->total_price}万円"), 0, 120) }}...
    </x-slot:metaDescription>

    @if(!empty($listing->images) && isset($listing->images[0]))
    <x-slot:ogImage>{{ $listing->images[0] }}</x-slot:ogImage>
    @endif

    <x-jsonld.product :listing="$listing" />
    <x-jsonld.breadcrumb :listing="$listing" />

    <x-slot:scripts>
        <script src="{{ asset('js/compare/manager.js') }}"></script>
        <script src="{{ asset('js/compare/ui.js') }}"></script>
        <script src="{{ asset('js/bikes/loan-simulator.js') }}"></script>

        {{-- JSにBladeの変数を渡す --}}
        <script>
            window.bikeModelStats = {!! json_encode($stats ?? [], JSON_HEX_TAG) !!};
            window.currentListingId = "{{ $listing->id }}";
            window.recaptchaSiteKey = "{{ env('RECAPTCHA_SITE_KEY') }}";
        </script>
        <script>window.__bikeModelId = {{ $listing->bike_model_id ?? 'null' }};</script>
        <script src="{{ asset('js/promo/engagement-banner.js') }}" defer></script>

        {{-- Chart.js + model_detail.js: チャートが見えた時のみ遅延読込（TBT大幅改善） --}}
        <script>
            (function() {
                var canvas = document.getElementById('priceChart') || document.getElementById('historyChart');
                if (!canvas) return;
                var container = document.getElementById('price-stats-container') || canvas;
                var obs = new IntersectionObserver(function(entries) {
                    if (entries[0].isIntersecting) {
                        obs.disconnect();
                        var s = document.createElement('script');
                        s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                        s.onload = function() {
                            var s2 = document.createElement('script');
                            s2.src = '{{ asset("js/bikes/model_detail.js") }}?v={{ filemtime(public_path("js/bikes/model_detail.js")) }}';
                            document.head.appendChild(s2);
                        };
                        s.onerror = function() {
                            console.warn('Chart.js CDN load failed');
                        };
                        document.head.appendChild(s);
                    }
                }, { rootMargin: '200px' });
                obs.observe(container);
            })();
        </script>

        {{-- reCAPTCHA: レビューフォームが見えた時のみ遅延読込 --}}
        <script>
            (function() {
                var form = document.getElementById('review-form');
                if (!form) return;
                var obs = new IntersectionObserver(function(entries) {
                    if (entries[0].isIntersecting) {
                        obs.disconnect();
                        var s = document.createElement('script');
                        s.src = 'https://www.google.com/recaptcha/api.js?render=' + window.recaptchaSiteKey;
                        document.head.appendChild(s);
                    }
                }, { rootMargin: '300px' });
                obs.observe(form);
            })();
        </script>
        <script src="{{ asset('js/bikes/review.js') }}"></script>
        <script src="{{ asset('js/search/seamless-nav.js') }}"></script>
        <script src="{{ asset('js/bikes/show.js') }}"></script>
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
    
    {{-- シームレス・ナビゲーション --}}
    <div id="search-nav-bar" class="hidden bg-gray-900 border-b border-gray-800 shadow-md sticky top-[64px] z-[30]">
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
                        {{-- 人気ラベル --}}
                        @if($listing->engagement['is_popular'] ?? false)
                        <div class="absolute top-4 left-4 z-20 bg-orange-500 text-white px-3 py-1.5 rounded-xl text-[10px] font-black italic flex items-center gap-1 shadow-lg animate-bounce">
                            <i data-lucide="flame" class="w-3.5 h-3.5 fill-current"></i> POPULAR
                        </div>
                        @endif
                        <div class="aspect-[4/3] bg-gray-100 relative group overflow-hidden">
                            @if(!empty($listing->images) && count($listing->images) > 0)
                                <div class="absolute inset-0 z-0 bg-cover bg-center blur-2xl opacity-50 scale-110" 
                                     style="background-image: url('{{ $listing->images[0] }}');" 
                                     aria-hidden="true"></div>
                                
                                <div class="absolute inset-0 z-10 flex items-center justify-center p-1">
                                    <img src="{{ $listing->images[0] }}" alt="{{ $listing->name }}"
                                        onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop'; this.classList.add('grayscale', 'opacity-50');"
                                        class="max-w-full max-h-full object-contain shadow-sm"
                                        width="800" height="600"
                                        fetchpriority="high" decoding="async">
                                </div>
                            @else
                                <img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop" 
                                     class="w-full h-full object-cover grayscale opacity-50" alt="No Image">
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <i data-lucide="image-off" class="w-8 h-8 text-white/50"></i>
                                </div>
                            @endif

                            <div class="absolute bottom-4 left-4 z-20 bg-black/60 backdrop-blur-sm px-3 py-1.5 rounded-xl flex items-center gap-2 border border-white/10 shadow-sm">
                                @if(isset($listing->source_icon_key) && $listing->source_icon_key !== 'default')
                                    <img src="{{ asset('images/sites/' . $listing->source_icon_key . '.png') }}" class="w-4 h-4 rounded-sm brightness-110" alt="">
                                @else
                                    <i data-lucide="external-link" class="w-4 h-4 text-white/80"></i>
                                @endif
                                <span class="text-[10px] font-black text-white/90">{{ $listing->source ?? $listing->site_name ?? '外部サイト' }}</span>
                            </div>
                            
                            <div class="absolute bottom-4 right-4 z-20 bg-black/70 text-white px-3 py-1 rounded-full text-xs font-bold backdrop-blur-sm">
                                <i data-lucide="camera" class="w-3 h-3 inline mr-1"></i>
                                {{ count($listing->images ?? []) }}枚
                            </div>
                        </div>

                        @if(!empty($listing->images) && count($listing->images) > 1)
                        <div class="flex gap-2 p-4 overflow-x-auto scrollbar-hide bg-white border-t border-gray-100">
                            @foreach($listing->images as $img)
                                <button class="shrink-0 w-20 h-20 rounded-lg overflow-hidden border-2 border-transparent hover:border-blue-600 transition bg-gray-50">
                                <img src="{{ $img }}" 
                                        onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop'; this.classList.add('grayscale', 'opacity-50');"
                                        class="w-full h-full object-cover"
                                        loading="lazy" decoding="async">
                                </button>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- 2. 車両基本情報 --}}
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <div class="flex items-center gap-4 mb-6 py-3 px-4 bg-blue-50/50 rounded-2xl border border-blue-100/50">
                            <div class="flex -space-x-2 shrink-0">
                                <div class="w-6 h-6 rounded-full bg-blue-500 flex items-center justify-center text-white border-2 border-white shadow-sm"><i data-lucide="eye" class="w-3 h-3"></i></div>
                                <div class="w-6 h-6 rounded-full bg-red-500 flex items-center justify-center text-white border-2 border-white shadow-sm"><i data-lucide="heart" class="w-3 h-3 fill-current"></i></div>
                            </div>
                            <div class="text-[10px] sm:text-xs font-bold text-blue-800">
                                <span class="font-black text-blue-600">本日 {{ $listing->engagement['view_count_today'] ?? 0 }}名</span> が閲覧中 
                                <span class="mx-2 text-blue-200">|</span>
                                <span class="font-black text-red-600">{{ $listing->engagement['favorite_count'] ?? 0 }}名</span> がお気に入りに追加
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
                            <div>
                                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 leading-tight mb-3">{{ $listing->name }}</h1>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-[10px] font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded uppercase">{{ $listing->maker }}</span>
                                    <span class="text-[10px] font-black text-orange-600 bg-orange-50 px-2 py-0.5 rounded uppercase">{{ $listing->category }}</span>
                                    <span class="text-[10px] font-black text-green-600 bg-green-50 px-2 py-0.5 rounded uppercase">{{ $listing->condition }}</span>
                                    <span class="text-[10px] font-black text-purple-600 bg-purple-50 px-2 py-0.5 rounded uppercase">{{ $listing->prefecture }}</span>
                                    
                                    @if(!empty($listing->site_name))
                                        <span class="text-[10px] font-black text-gray-600 bg-white border border-gray-200 shadow-sm px-2 py-0.5 rounded uppercase flex items-center gap-1">
                                            <i data-lucide="link" class="w-3 h-3 text-gray-400"></i> {{ $listing->site_name }}
                                        </span>
                                    @endif
                                </div>
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
                            
                            {{-- ★修正: イベントバブリングを阻害しないようJS直接呼び出しに変更 --}}
                            <div class="flex items-center gap-3">
                                <button class="compare-btn w-12 h-12 rounded-full border border-gray-200 flex items-center justify-center text-gray-400 hover:bg-gray-50 transition-colors shadow-sm" data-id="{{ $listing->id }}">
                                    <i data-lucide="layers" class="w-5 h-5"></i>
                                </button>
                                <div class="flex items-center sm:gap-3 bg-transparent sm:bg-gray-50 sm:pl-4 sm:pr-1.5 sm:py-1.5 rounded-full border-transparent sm:border-gray-200 border cursor-pointer hover:bg-red-50 hover:border-red-200 group transition-colors" onclick="if(window.WishlistManager) window.WishlistManager.toggle('{{ $listing->id }}')">
                                    <div class="flex flex-col text-right hidden sm:flex pointer-events-none">
                                        <span class="text-[10px] font-black text-gray-900 group-hover:text-red-700 leading-none">お気に入り</span>
                                        <span class="text-[8px] font-bold text-gray-500 group-hover:text-red-500 mt-0.5">LINEで値下げ通知</span>
                                    </div>
                                    <button class="wishlist-btn w-10 h-10 sm:w-9 sm:h-9 rounded-full border border-gray-200 flex items-center justify-center text-gray-400 bg-white shadow-sm transition-colors pointer-events-none" data-id="{{ $listing->id }}">
                                        <i data-lucide="heart" class="w-4 h-4"></i>
                                    </button>
                                </div>
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

                    {{-- カタログスペック情報 --}}
                    @if(!empty(array_filter($listing->specs)))
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mt-8">
                        <div class="flex items-center gap-2 mb-6">
                            <div class="p-2 bg-gray-800 rounded-lg text-white">
                                <i data-lucide="book-open" class="w-5 h-5"></i>
                            </div>
                            <h3 class="text-lg font-black text-gray-900">カタログスペック</h3>
                            <span class="hidden sm:inline-block text-[10px] font-bold text-gray-400 ml-2 border border-gray-200 px-2 py-0.5 rounded bg-gray-50">{{ $listing->bike_model_name }}</span>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-1">
                            @foreach([
                                'seat_height' => ['label' => 'シート高', 'unit' => 'mm', 'icon' => 'user'],
                                'weight' => ['label' => '車両重量', 'unit' => 'kg', 'icon' => 'weight'],
                                'fuel_consumption' => ['label' => '燃費 (定地)', 'unit' => 'km/L', 'icon' => 'fuel'],
                                'tank_capacity' => ['label' => 'タンク容量', 'unit' => 'L', 'icon' => 'database'],
                                'engine_type' => ['label' => 'エンジン種類', 'unit' => '', 'icon' => 'settings'],
                                'max_power' => ['label' => '最高出力', 'unit' => '', 'icon' => 'zap'],
                                'max_torque' => ['label' => '最大トルク', 'unit' => '', 'icon' => 'gauge-circle'],
                                'tire_size_front' => ['label' => 'フロントタイヤ', 'unit' => '', 'icon' => 'circle-dashed'],
                                'tire_size_rear' => ['label' => 'リアタイヤ', 'unit' => '', 'icon' => 'circle-dashed']
                            ] as $key => $conf)
                                @if(!empty($listing->specs[$key]))
                                <div class="flex justify-between items-center py-3 border-b border-gray-50 last:border-0 sm:nth-last-child(-n+2):border-0">
                                    <span class="text-xs font-bold text-gray-500 flex items-center gap-1.5 whitespace-nowrap">
                                        <i data-lucide="{{ $conf['icon'] }}" class="w-3.5 h-3.5 text-gray-300"></i>
                                        {{ $conf['label'] }}
                                    </span>
                                    <span class="text-sm font-black text-gray-800 text-right max-w-[60%] leading-tight">
                                        {{ $listing->specs[$key] }} <span class="text-[10px] text-gray-500 ml-0.5 font-bold">{{ $conf['unit'] }}</span>
                                    </span>
                                </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    @endif

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

                        @if(isset($stats) && ($stats['count'] ?? 0) > 0)
                        {{-- サーバーサイドで直接レンダリング（JSの読み込み連鎖に依存しない） --}}
                        <div id="price-stats-content">
                            <div class="grid grid-cols-3 gap-2 sm:gap-4 mb-8">
                                <div class="bg-gray-50 rounded-xl p-3 sm:p-4 text-center border border-gray-100">
                                    <div class="text-[10px] font-bold text-gray-400 mb-1">相場平均</div>
                                    <div class="text-base sm:text-xl font-black text-gray-800">{{ $stats['avg'] }}<span class="text-xs ml-0.5">万円</span></div>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-3 sm:p-4 text-center border border-gray-100">
                                    <div class="text-[10px] font-bold text-gray-400 mb-1">最安値</div>
                                    <div class="text-base sm:text-xl font-black text-blue-600">{{ $stats['min'] }}<span class="text-xs ml-0.5">万円</span></div>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-3 sm:p-4 text-center border border-gray-100">
                                    <div class="text-[10px] font-bold text-gray-400 mb-1">最高値</div>
                                    <div class="text-base sm:text-xl font-black text-red-500">{{ $stats['max'] }}<span class="text-xs ml-0.5">万円</span></div>
                                </div>
                            </div>

                            {{-- ヒストグラムはChart.js遅延読込後に描画 --}}
                            <div class="relative h-64 w-full">
                                <canvas id="priceChart"></canvas>
                            </div>

                            <p class="text-[10px] text-gray-400 mt-4 text-right">※MotoHubに掲載中の「{{ $listing->name }}」全車両のデータから算出</p>
                            @if($listing->bike_model_id)
                            <div class="mt-8 pt-6 border-t border-gray-100 text-center">
                                <a href="{{ $bikeModelForUrl?->seo_url ?? '#' }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-blue-50 to-indigo-50 hover:from-blue-100 hover:to-indigo-100 text-blue-700 font-bold rounded-xl transition shadow-sm border border-blue-100 group">
                                    <i data-lucide="coins" class="w-4 h-4 text-yellow-500"></i>
                                    <span>この車種の買取相場・リセール情報を見る</span>
                                    <i data-lucide="chevron-right" class="w-4 h-4 text-blue-400 group-hover:translate-x-1 transition-transform"></i>
                                </a>
                            </div>
                            @endif
                        </div>
                        @else
                        <p class="text-xs text-gray-400 font-bold text-center py-6">この車種の価格データが不足しています</p>
                        @endif
                    </div>
                    @endif

                    {{-- オーナーレビューセクション --}}
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
                                <button type="button" onclick="openReviewModal()" class="inline-flex items-center text-xs font-bold bg-yellow-400 hover:bg-yellow-500 text-yellow-900 px-3 py-2 rounded-lg transition-colors shadow-sm active:scale-95">
                                    <i data-lucide="pen-line" class="w-3.5 h-3.5 mr-1"></i> レビューを書く
                                </button>
                                
                                @if($reviews->isNotEmpty())
                                <a href="{{ $bikeModelForUrl?->seo_url ? $bikeModelForUrl->seo_url . '#reviews' : '#' }}" class="inline-flex items-center text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors py-2">
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
                                        class="w-full h-full object-cover"
                                        loading="lazy" decoding="async">
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
                                    <p class="pt-2 mt-2 border-t border-gray-100 text-xs">
                                        <span class="text-gray-400">情報提供元:</span> 
                                        <span class="text-gray-700">{{ $listing->site_name ?? '外部サイト' }}</span>
                                    </p>
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
                                @if(isset($stats['rank']) && in_array($stats['rank'], ['S', 'A']) && isset($stats['diff']) && $stats['diff'] < 0)
                                    <div class="inline-flex items-center justify-center gap-1.5 bg-red-50 text-red-600 px-3 py-1 rounded-full text-xs font-black border border-red-100 mb-3 shadow-sm">
                                        <i data-lucide="trending-down" class="w-4 h-4"></i>
                                        相場より {{ abs($stats['diff']) }}万円 おトク！
                                    </div>
                                @endif

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
                                
                                @if($priceDropDiff)
                                <div class="mt-3 inline-flex flex-col items-center justify-center gap-1 bg-yellow-50 text-yellow-800 px-4 py-2.5 rounded-xl text-xs font-black border border-yellow-300 shadow-sm animate-pulse w-full">
                                    <div class="flex items-center gap-1.5">
                                        <i data-lucide="arrow-down-circle" class="w-4 h-4 text-yellow-600"></i>
                                        以前より {{ $priceDropDiff }}万円 値下がりしました！
                                    </div>
                                </div>
                                @endif
                                
                                @if($priceDropDiff)
                                <div class="mt-3 inline-flex flex-col items-center justify-center gap-1 bg-yellow-50 text-yellow-800 px-4 py-2.5 rounded-xl text-xs font-black border border-yellow-300 shadow-sm animate-pulse w-full">
                                    <div class="flex items-center gap-1.5">
                                        <i data-lucide="arrow-down-circle" class="w-4 h-4 text-yellow-600"></i>
                                        以前より {{ $priceDropDiff }}万円 値下がりしました！
                                    </div>
                                </div>
                                @endif
                            </div>

                            <div class="mb-6">
                                <div class="bg-gray-50 hover:bg-red-50 rounded-2xl p-4 border border-gray-200 hover:border-red-200 flex items-center justify-between group cursor-pointer transition-colors shadow-sm" onclick="if(window.WishlistManager) window.WishlistManager.toggle('{{ $listing->id }}')">
                                    <div class="flex flex-col text-left pointer-events-none">
                                        <span class="text-sm font-black text-gray-900 group-hover:text-red-700 flex items-center gap-1.5 transition-colors">
                                            <i data-lucide="bell-ring" class="w-4 h-4 text-yellow-500 group-hover:text-red-500"></i> 値下げアラート
                                        </span>
            
                                        {{-- 状態によってテキストを切り替え --}}
                                        @guest
                                            <span class="text-[10px] font-bold text-gray-500 group-hover:text-red-500 mt-1 transition-colors">価格が下がったらLINEでお知らせ！</span>
                                        @else
                                            @if(auth()->user()->hasLineLinked())
                                                <span class="text-[10px] font-bold text-[#06C755] mt-1 transition-colors flex items-center gap-1">
                                                    <i data-lucide="message-circle" class="w-3 h-3"></i> LINEで最速通知を受信します
                                                </span>
                                            @else
                                                <span class="text-[10px] font-bold text-gray-500 group-hover:text-red-500 mt-1 transition-colors">価格が下がったらLINEでお知らせ！</span>
                                            @endif
                                        @endguest
                                    </div>
                                    <button class="wishlist-btn w-10 h-10 rounded-full border border-gray-200 flex items-center justify-center text-gray-400 bg-white transition-colors shadow-sm pointer-events-none" data-id="{{ $listing->id }}">
                                        <i data-lucide="heart" class="w-4 h-4"></i>
                                    </button>
                                </div>
    
                                {{-- 通知ステータスの詳細バナー --}}
                                @guest
                                    {{-- 未ログインユーザーへの登録訴求バナー --}}
                                    <div class="mt-3 bg-blue-50/70 border border-blue-100 rounded-xl p-3 flex items-start gap-2">
                                        <i data-lucide="info" class="w-4 h-4 text-blue-500 shrink-0 mt-0.5"></i>
                                        <div>
                                            <p class="text-[10px] text-blue-900 font-bold leading-relaxed mb-2">
                                                通知を受け取るには、無料のログインが必要です。
                                            </p>
                                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center w-full bg-white border border-gray-200 hover:border-blue-300 text-gray-700 text-[10px] font-black py-2 rounded-lg transition-colors shadow-sm">
                                                <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="w-3 h-3 mr-1.5" alt="G" loading="lazy" decoding="async">
                                                Googleで1秒ログイン
                                            </a>
                                        </div>
                                    </div>
                                @else
                                    {{-- ログイン済み：LINE未連携のユーザーへLINE連携を強く訴求 --}}
                                    @if(!auth()->user()->hasLineLinked())
                                        <div class="mt-3 bg-gray-50 border border-gray-200 rounded-xl p-3 shadow-sm">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-[10px] font-bold text-gray-500">現在の通知設定:</span>
                                                <span class="text-[10px] font-black text-gray-700 flex items-center gap-1 bg-white px-2 py-1 rounded-md border border-gray-200 shadow-sm">
                                                    <i data-lucide="mail" class="w-3 h-3"></i> メール通知のみ
                                                </span>
                                            </div>
                                            <a href="{{ route('auth.line.redirect') }}" class="flex items-center justify-center gap-1.5 w-full text-white text-[10px] font-bold py-2.5 rounded-lg transition-opacity hover:opacity-90 shadow-sm" style="background-color: #06C755;">
                                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 5.88 2 10.54c0 4.07 3.42 7.49 8.05 8.44.31.07.73.21.84.48.1.25.06.63.03.88l-.14.83c-.04.25-.2.97.85.53s5.61-3.31 7.66-5.67C21.03 13.86 22 12.28 22 10.54 22 5.88 17.52 2 12 2z"/></svg>
                                                LINE連携して見逃しを防ぐ
                                            </a>
                                        </div>
                                    @endif
                                @endguest
                            </div>

                            <div class="space-y-3">
                                <a href="{{ $listing->url }}" target="_blank" class="block w-full bg-red-600 hover:bg-red-500 text-white font-black text-center py-4 rounded-xl shadow-lg shadow-red-500/30 transition hover:-translate-y-1">
                                    {{ $listing->site_name ?? '販売店' }} で在庫確認・見積もり
                                    <span class="block text-[10px] font-medium opacity-80 mt-0.5">（無料・別タブで開きます）</span>
                                </a>
                                
                                @if(!empty($listing->shop_tel) && $listing->shop_tel !== '-')
                                    <a href="tel:{{ str_replace('-', '', $listing->shop_tel) }}" class="block w-full bg-white border-2 border-gray-100 hover:border-blue-600 text-gray-700 hover:text-blue-600 font-bold text-center py-3 rounded-xl transition group">
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
                        {{-- 検討を促すサイドバーパーツ --}}
                        <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-3xl p-6 text-white shadow-lg">
                            <h4 class="text-xs font-black uppercase tracking-widest text-orange-400 mb-3 flex items-center gap-2">
                                <i data-lucide="alert-circle" class="w-4 h-4"></i> 売却済みにご注意ください
                            </h4>
                            <p class="text-[11px] font-bold leading-relaxed text-gray-300">
                                この車両は現在 <span class="text-white font-black text-sm">{{ $listing->engagement['view_count_today'] ?? 0 }}名</span> が閲覧し、<span class="text-red-400 font-black text-sm">{{ $listing->engagement['favorite_count'] ?? 0 }}名</span> が検討リストに追加しています。中古バイクは1点物のため、タッチの差で売約済みとなるケースが多くなっています。
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-12">
            @include('bikes.partials.recommendations')
            </div>

            {{-- 愛車ガレージCTA --}}
            <div class="mt-8 bg-pink-50 rounded-2xl p-5 border border-pink-100 text-center">
                <p class="text-sm font-bold text-gray-800 mb-2">この車種に乗っていますか？</p>
                <a href="{{ route('mybikes.index') }}" class="text-xs font-bold text-pink-600 hover:underline">
                    愛車ガレージに登録して燃費・整備を記録する →
                </a>
            </div>

            {{-- 近くの駐車場・ショップ・回遊リンク --}}
            <div class="mt-12 space-y-6">
                <x-nearby-parkings :nearbyParkings="$nearbyParkings" :latitude="$shopLat" :longitude="$shopLng" />
                <x-nearby-shops :nearbyShops="$nearbyShops" :latitude="$shopLat" :longitude="$shopLng" />
                @if($alsoViewed->count() > 0)
                <div class="mt-8">
                    <h2 class="text-lg font-black text-gray-900 mb-4">この車両を見た人はこれも見ています</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4">
                        @foreach($alsoViewed as $item)
                        <a href="{{ route('bikes.show', $item->id) }}" class="bg-white rounded-xl border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">
                            <div class="h-28 sm:h-40 bg-gray-100 overflow-hidden">
                                @if(!empty($item->images[0]))
                                <img src="{{ $item->images[0] }}" alt="{{ $item->title }}" class="w-full h-full object-cover" loading="lazy" decoding="async">
                                @endif
                            </div>
                            <div class="p-3">
                                @if($item->total_price)
                                <p class="text-sm font-black text-red-600 mb-1">{{ number_format($item->total_price / 10000, 1) }}万円</p>
                                @endif
                                <p class="text-xs font-bold text-gray-800 line-clamp-2">{{ $item->title }}</p>
                                <div class="flex items-center gap-2 mt-1 text-[10px] text-gray-400">
                                    @if($item->model_year)<span>{{ $item->model_year }}年</span>@endif
                                    @if($item->mileage)<span>{{ number_format($item->mileage) }}km</span>@endif
                                    @if($item->shop?->prefecture)<span>{{ $item->shop->prefecture }}</span>@endif
                                </div>
                            </div>
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif
                <x-cross-links :crossLinks="$crossLinks" />
            </div>
        </div>
    </div>

    {{-- スマホ用固定フッターCV（お気に入り数連動） --}}
    <div class="fixed bottom-0 left-0 w-full bg-white/90 backdrop-blur-md border-t border-gray-200 p-3 sm:p-4 lg:hidden z-50 safe-area-bottom shadow-[0_-4px_12px_rgba(0,0,0,0.05)]">
        <div class="flex gap-2 sm:gap-3 items-center">
            
            {{-- ★修正: 中のアイコンとテキストをクリック無視にし、ボタン本体が確実にイベントを拾うように --}}
            <button class="wishlist-btn shrink-0 w-12 h-12 sm:w-14 sm:h-14 rounded-xl border border-gray-200 flex flex-col items-center justify-center text-gray-400 bg-gray-50 shadow-sm transition-colors" data-id="{{ $listing->id }}">
                <i data-lucide="heart" class="w-5 h-5 mb-0.5 pointer-events-none"></i>
                <span class="text-[7px] sm:text-[8px] font-black leading-none tracking-tighter pointer-events-none">アラート</span>
            </button>

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 sm:gap-2 mb-0.5">
                    <div class="text-[9px] sm:text-[10px] font-bold text-gray-400 whitespace-nowrap">支払総額</div>
                    @if(isset($stats['rank']) && in_array($stats['rank'], ['S', 'A']) && isset($stats['diff']) && $stats['diff'] < 0)
                        <span class="text-[8px] sm:text-[9px] font-black text-red-600 bg-red-50 px-1.5 py-0.5 rounded border border-red-100 truncate">
                            {{ abs($stats['diff']) }}万円 安い!
                        </span>
                    @endif
                </div>
                <div class="text-lg sm:text-xl font-black text-red-500 leading-none truncate">{{ $listing->total_price }}<span class="text-[10px] sm:text-xs text-gray-500 ml-0.5">万円</span></div>
            </div>

            <a href="{{ $listing->url }}" target="_blank" class="w-36 sm:w-48 bg-red-600 text-white font-black flex flex-col items-center justify-center rounded-xl shadow-lg shadow-red-500/30 py-2 sm:py-2.5 active:scale-95 transition-transform shrink-0">
                <span class="text-xs sm:text-sm">在庫確認・見積</span>
                <span class="text-[8px] sm:text-[9px] font-medium opacity-90 flex items-center gap-1 mt-0.5">
                    <i data-lucide="users" class="w-2.5 h-2.5"></i> {{ $listing->engagement['favorite_count'] ?? 0 }}名が検討中
                </span>
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
    
    {{-- レビュー投稿モーダル --}}
    @if($listing->bike_model_id)
    <div id="review-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 sm:p-0">
        {{-- 背景の黒いオーバーレイ --}}
        <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="closeReviewModal()"></div>
        
        {{-- モーダル本体 --}}
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden transform transition scale-95 opacity-0 duration-300" id="review-modal-content">
            
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
                <form id="review-form" action="{{ action([\App\Http\Controllers\Bike\BikeController::class, 'storeReview'], ['id' => $listing->bike_model_id]) }}" class="space-y-5">
                    @csrf
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 sm:col-span-1">
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">ニックネーム</label>
                            <input type="text" name="nickname" required maxlength="50" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-yellow-400 focus:bg-white outline-none transition placeholder:text-gray-300" placeholder="例：モト太郎">
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
                        <input type="text" name="title" required maxlength="100" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-yellow-400 focus:bg-white outline-none transition placeholder:text-gray-300" placeholder="一言でいうとどんなバイク？">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">レビュー本文</label>
                        <textarea name="body" required rows="4" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-yellow-400 focus:bg-white outline-none transition resize-none placeholder:text-gray-300 leading-relaxed" placeholder="足つき、燃費、取り回しなど、実際に乗ってみた感想や良い点・悪い点を教えてください！"></textarea>
                    </div>

                    <div id="review-error" class="hidden text-xs text-red-600 font-bold bg-red-50 border border-red-100 p-3 rounded-xl flex items-start gap-2">
                        <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                        <span id="review-error-text"></span>
                    </div>

                    <div class="pt-4 mt-4 border-t border-gray-100">
                        <button type="submit" id="review-submit-btn" class="w-full bg-yellow-400 hover:bg-yellow-500 text-yellow-900 font-black py-3.5 rounded-xl transition shadow-lg shadow-yellow-400/20 active:scale-[0.98] flex items-center justify-center gap-2 text-sm">
                            <i data-lucide="send" class="w-4 h-4"></i>
                            この内容で投稿する
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</x-layout>