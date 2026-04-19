<x-layout>
    @php
        $titleParts = [$listing->name];
        if ($listing->total_price) {
            $titleParts[] = number_format((float)$listing->total_price * 10000) . '円';
        }
        if ($listing->model_year) {
            $titleParts[] = $listing->model_year . '年式';
        }
        $rawMileage = preg_replace('/[^0-9]/', '', (string) ($listing->mileage ?? ''));
        if ($rawMileage !== '' && $rawMileage !== '0') {
            $m = (int) $rawMileage;
            $titleParts[] = ($m < 5000 ? '低走行' : '') . number_format($m) . 'km';
        }

        $categoryMessages = [
            'ネイキッド' => '街乗りからツーリングまで万能',
            'スポーツ/レプリカ' => 'サーキットも公道も楽しめる',
            'アメリカン' => 'ゆったりクルーズに最適',
            'オフロード' => '林道もダートも走破',
            'スクーター' => '通勤・通学の足に',
            'ツアラー' => 'ロングツーリングの相棒',
            'アドベンチャー' => 'オンもオフも自由自在',
            'クラシック' => 'レトロな佇まいが魅力',
            'ミニバイク' => '取り回し抜群のコンパクトサイズ',
        ];
        $catName = $listing->category ?? '';
        $catMsg = $categoryMessages[$catName] ?? '';

        $descParts = [$listing->name];
        if ($listing->total_price) {
            $descParts[] = '総額' . number_format((float)$listing->total_price * 10000) . '円';
        }
        if ($catMsg) {
            $descParts[] = $catMsg;
        }
        $descParts[] = ($listing->shop_name ?? '') . ($listing->prefecture ? '（' . $listing->prefecture . '）' : '');
    @endphp
    <x-slot:title>{{ implode('｜', $titleParts) }} - MotoHub</x-slot:title>

    <x-slot:metaDescription>{{ implode('。', array_filter($descParts)) }}。MotoHubで価格相場と比較して、お買い得な中古バイクを見つけよう。</x-slot:metaDescription>

    @if(!empty($listing->images) && isset($listing->images[0]))
    <x-slot:ogImage>{{ $listing->images[0] }}</x-slot:ogImage>
    @endif

    <x-jsonld.product :listing="$listing" />
    <x-jsonld.breadcrumb :listing="$listing" />

    <x-slot:scripts>
        <script src="{{ asset('js/compare/manager.js') }}?v={{ filemtime(public_path('js/compare/manager.js')) }}"></script>
        <script src="{{ asset('js/compare/ui.js') }}?v={{ filemtime(public_path('js/compare/ui.js')) }}"></script>
        <script src="{{ asset('js/bikes/loan-simulator.js') }}?v={{ filemtime(public_path('js/bikes/loan-simulator.js')) }}"></script>

        {{-- JSにBladeの変数を渡す --}}
        <script>
            window.bikeModelStats = {!! json_encode($stats ?? [], JSON_HEX_TAG) !!};
            window.currentListingId = "{{ $listing->id }}";
            window.recaptchaSiteKey = "{{ config('services.recaptcha.site_key') }}";
        </script>
        <script>window.__bikeModelId = {{ $listing->bike_model_id ?? 'null' }};</script>
        <script src="{{ asset('js/promo/engagement-banner.js') }}?v={{ filemtime(public_path('js/promo/engagement-banner.js')) }}" defer></script>

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
        <script src="{{ asset('js/bikes/review.js') }}?v={{ filemtime(public_path('js/bikes/review.js')) }}"></script>
        <script src="{{ asset('js/search/seamless-nav.js') }}?v={{ filemtime(public_path('js/search/seamless-nav.js')) }}"></script>
        <script src="{{ asset('js/bikes/show.js') }}?v={{ filemtime(public_path('js/bikes/show.js')) }}"></script>
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

            {{-- 売り切れバナー --}}
            @if($listing->is_sold_out && $soldOutData)
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 sm:p-6 mb-6 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i data-lucide="tag" class="w-5 h-5 text-amber-600"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-lg font-black text-amber-800">この車両は販売終了しました</p>
                        <p class="text-sm text-amber-700 mt-1">
                            {{ $listing->bike_model_name ?? 'この車種' }}の販売中車両を探しませんか？
                        </p>
                        <div class="flex flex-wrap gap-2 mt-3">
                            @if($listing->bike_model_id)
                            <a href="{{ route('bikes.search', ['manufacturer_id' => $listing->manufacturer_id, 'bike_model_id' => $listing->bike_model_id]) }}"
                               class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-full hover:bg-blue-700 transition shadow-sm">
                                <i data-lucide="search" class="w-3.5 h-3.5"></i>
                                {{ $listing->bike_model_name ?? 'この車種' }}の販売中車両を見る
                            </a>
                            @endif
                            @if($bikeModelForUrl)
                            <a href="{{ route('bikes.model_detail.fallback', $bikeModelForUrl->id) }}"
                               class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-700 text-xs font-bold rounded-full border border-gray-200 hover:bg-gray-50 transition">
                                <i data-lucide="bar-chart-3" class="w-3.5 h-3.5"></i>
                                {{ $listing->bike_model_name ?? 'この車種' }}の相場を見る
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="lg:grid lg:grid-cols-12 lg:gap-8">
                {{-- メインカラム --}}
                <div class="lg:col-span-8 space-y-8">

                {{-- 1. 画像ギャラリー --}}
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                        {{-- 人気ラベル --}}
                        @if($listing->is_sold_out)
                        <div class="absolute top-4 left-4 z-20 bg-gray-800 text-white px-4 py-2 rounded-xl text-sm font-black flex items-center gap-1.5 shadow-lg">
                            <i data-lucide="ban" class="w-4 h-4"></i> SOLD OUT
                        </div>
                        @elseif($listing->engagement['is_popular'] ?? false)
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
                        @if(!$listing->is_sold_out)
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
                        @endif
                        
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
                                <div class="compare-btn flex items-center gap-3 bg-gray-50 pl-4 pr-1.5 py-1.5 rounded-full border border-gray-200 cursor-pointer hover:bg-blue-50 hover:border-blue-200 group transition-colors" data-id="{{ $listing->id }}">
                                    <div class="flex flex-col text-right pointer-events-none">
                                        <span class="text-[10px] font-black text-gray-900 group-hover:text-blue-700 leading-none compare-label">比較</span>
                                        <span class="text-[8px] font-bold text-gray-500 group-hover:text-blue-500 mt-0.5 compare-sub">リストに追加</span>
                                    </div>
                                    <button class="w-10 h-10 sm:w-9 sm:h-9 rounded-full border border-gray-200 flex items-center justify-center text-gray-400 bg-white shadow-sm transition-colors pointer-events-none compare-icon">
                                        <i data-lucide="layers" class="w-4 h-4"></i>
                                    </button>
                                </div>
                                <div class="flex items-center gap-3 bg-gray-50 pl-4 pr-1.5 py-1.5 rounded-full border border-gray-200 cursor-pointer hover:bg-red-50 hover:border-red-200 group transition-colors" onclick="if(window.WishlistManager) window.WishlistManager.toggle('{{ $listing->id }}')">
                                    <div class="flex flex-col text-right pointer-events-none">
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

                    {{-- この車両について（車種・地域・価格帯テキスト） --}}
                    @if(!empty($modelComment) || !empty($regionComment) || !empty($priceBandComment))
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <div class="flex items-center gap-2 mb-6">
                            <div class="p-2 bg-cyan-50 rounded-lg text-cyan-600">
                                <i data-lucide="book-open" class="w-5 h-5"></i>
                            </div>
                            <h3 class="text-lg font-black text-gray-900">この車両について</h3>
                        </div>
                        <div class="space-y-4">
                            @if(!empty($modelComment) && !($bikeModelForUrl && $bikeModelForUrl->enriched_content))
                            {{-- enriched_contentがない場合のみ定型文を表示（enriched_content有の場合は車種紹介セクションで代替） --}}
                            <div>
                                <h4 class="text-sm font-black text-gray-800 mb-1 flex items-center gap-1.5">
                                    <i data-lucide="bike" class="w-4 h-4 text-gray-400"></i>
                                    {{ $listing->bike_model_name ?? $listing->maker ?? 'この車種' }}とは
                                </h4>
                                <p class="text-sm text-gray-600 leading-relaxed">{{ $modelComment }}</p>
                            </div>
                            @endif
                            @if(!empty($priceBandComment))
                            <div>
                                <h4 class="text-sm font-black text-gray-800 mb-1 flex items-center gap-1.5">
                                    <i data-lucide="coins" class="w-4 h-4 text-gray-400"></i>
                                    この価格帯の特徴
                                </h4>
                                <p class="text-sm text-gray-600 leading-relaxed">{{ $priceBandComment }}</p>
                            </div>
                            @endif
                            @if(!empty($regionComment))
                            <div>
                                <h4 class="text-sm font-black text-gray-800 mb-1 flex items-center gap-1.5">
                                    <i data-lucide="map-pin" class="w-4 h-4 text-gray-400"></i>
                                    {{ $listing->prefecture ?? 'この地域' }}のツーリング情報
                                </h4>
                                <p class="text-sm text-gray-600 leading-relaxed">{{ $regionComment }}</p>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

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

                    {{-- 車種紹介（enriched_content） --}}
                    @if($bikeModelForUrl && $bikeModelForUrl->enriched_content && !empty($bikeModelForUrl->enriched_content['introduction']))
                    <div class="bg-blue-50 rounded-3xl border border-blue-100 p-6 sm:p-8">
                        <h3 class="text-lg font-black text-gray-900 mb-3">
                            @if($bikeModelForUrl->manufacturer && $bikeModelForUrl->slug)
                            <a href="{{ route('bikes.model_detail', ['mfrSlug' => $bikeModelForUrl->manufacturer->slug, 'modelSlug' => $bikeModelForUrl->slug]) }}"
                               class="text-blue-600 hover:underline">
                                {{ $bikeModelForUrl->name }}について
                            </a>
                            @else
                                {{ $bikeModelForUrl->name }}について
                            @endif
                        </h3>
                        <p class="text-sm text-gray-700 leading-relaxed">{{ $bikeModelForUrl->enriched_content['introduction'] }}</p>
                        @if(isset($bikeModelForUrl->enriched_content['target_rider']))
                        <p class="text-sm text-gray-600 mt-3">
                            <span class="font-black">おすすめ:</span> {{ $bikeModelForUrl->enriched_content['target_rider'] }}
                        </p>
                        @endif
                        @if($bikeModelForUrl->manufacturer && $bikeModelForUrl->slug)
                        <a href="{{ route('bikes.model_detail', ['mfrSlug' => $bikeModelForUrl->manufacturer->slug, 'modelSlug' => $bikeModelForUrl->slug]) }}"
                           class="text-sm text-blue-600 hover:underline mt-3 inline-flex items-center gap-1 font-bold">
                            {{ $bikeModelForUrl->name }}の詳細を見る
                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </a>
                        @endif
                    </div>
                    @endif

                    {{-- モデル履歴サマリー --}}
                    @if($bikeModelForUrl && $bikeModelForUrl->model_history)
                    <div class="flex flex-wrap gap-3 text-sm">
                        @if($bikeModelForUrl->model_history['first_year'] ?? null)
                        <span class="bg-gray-100 px-3 py-1.5 rounded-full font-bold text-gray-700">
                            発売: {{ $bikeModelForUrl->model_history['first_year'] }}年〜
                        </span>
                        @endif
                        @if($bikeModelForUrl->model_history['is_current'] ?? false)
                        <span class="bg-green-100 text-green-700 px-3 py-1.5 rounded-full font-bold">現行販売中</span>
                        @else
                        <span class="bg-red-100 text-red-700 px-3 py-1.5 rounded-full font-bold">生産終了</span>
                        @endif
                        @if($bikeModelForUrl->model_history['current_new_price_min'] ?? null)
                        <span class="bg-gray-100 px-3 py-1.5 rounded-full font-bold text-gray-700">
                            新車価格: {{ $bikeModelForUrl->model_history['current_new_price_min'] }}〜{{ $bikeModelForUrl->model_history['current_new_price_max'] }}万円
                        </span>
                        @endif
                    </div>
                    @endif

                    {{-- 装備ポイント解説 --}}
                    @php
                        $tagDescriptions = [
                            'ETC' => ['icon' => '📡', 'title' => 'ETC搭載', 'desc' => '高速道路の料金所をノンストップで通過可能。ツーリング派には必須の装備です。'],
                            'ABS' => ['icon' => '🛑', 'title' => 'ABS搭載', 'desc' => '急ブレーキ時のタイヤロックを防止。雨天時や緊急時の安全性が大幅に向上します。'],
                            'カスタム車' => ['icon' => '🔧', 'title' => 'カスタム車', 'desc' => 'オーナーのこだわりが詰まったカスタム仕様。個性的な1台をお探しの方におすすめです。'],
                            'ノーマル車' => ['icon' => '✨', 'title' => 'ノーマル車', 'desc' => 'メーカー純正状態を維持。信頼性が高く、リセールバリューも良好です。'],
                            'FI(インジェクション)' => ['icon' => '⚙️', 'title' => 'インジェクション車', 'desc' => '電子制御燃料噴射で安定した始動性と燃費性能。冬場の始動もスムーズです。'],
                            'USB電源' => ['icon' => '🔌', 'title' => 'USB電源装備', 'desc' => 'スマホやナビの充電が走行中に可能。ロングツーリングの必需品です。'],
                            'エンジンガード' => ['icon' => '🛡️', 'title' => 'エンジンガード装着', 'desc' => '転倒時にエンジンやカウルを保護。立ちゴケが心配な方にも安心です。'],
                            'ワンオーナー' => ['icon' => '👤', 'title' => 'ワンオーナー車', 'desc' => '新車から一人のオーナーが大切に乗ってきた車両。整備履歴が明確で状態の良い車両が多い傾向です。'],
                            'グリップヒーター' => ['icon' => '🔥', 'title' => 'グリップヒーター装備', 'desc' => '冬場のライディングでも手がかじかまず快適。寒冷地や冬ツーリングに重宝します。'],
                            'フェンダーレス' => ['icon' => '🏍️', 'title' => 'フェンダーレス化', 'desc' => 'リアフェンダーを取り外しスッキリとしたリア周りに。スポーティな外観が特徴です。'],
                            'リアボックス' => ['icon' => '📦', 'title' => 'リアボックス装着', 'desc' => '荷物の収納力が大幅アップ。通勤・買い物からツーリングまで幅広く活躍します。'],
                            '社外マフラー' => ['icon' => '🔧', 'title' => '社外マフラー装着', 'desc' => '排気効率の向上やサウンドの変化が期待できます。見た目のカスタム感もUP。'],
                            'ドラレコ' => ['icon' => '📹', 'title' => 'ドライブレコーダー装着', 'desc' => '万が一の事故時に映像記録を残せます。あおり運転対策にも有効。'],
                            'ヨシムラマフラー' => ['icon' => '🏁', 'title' => 'ヨシムラマフラー装着', 'desc' => 'レース界で定評のあるヨシムラ製マフラー。性能とサウンド、ブランド価値の高い人気パーツです。'],
                            'ローダウン' => ['icon' => '⬇️', 'title' => 'ローダウン仕様', 'desc' => '足つき性を改善。身長が低めの方でも安心して乗れます。'],
                            'クイックシフター' => ['icon' => '⚡', 'title' => 'クイックシフター装備', 'desc' => 'クラッチ操作なしでシフトアップ可能。スポーツ走行がよりスムーズになります。'],
                            'モリワキマフラー' => ['icon' => '🏁', 'title' => 'モリワキマフラー装着', 'desc' => '老舗マフラーメーカー・モリワキ製。確かな品質と迫力のサウンドが魅力です。'],
                            'スマホホルダー' => ['icon' => '📱', 'title' => 'スマホホルダー装備', 'desc' => 'ナビアプリを見ながら走行可能。初めての道でも安心です。'],
                            'セル付き' => ['icon' => '🔑', 'title' => 'セル付き', 'desc' => 'ボタン一つでエンジン始動。キックスタートが苦手な方にも安心です。'],
                            'パニアケース' => ['icon' => '🧳', 'title' => 'パニアケース装着', 'desc' => '左右に大容量の収納。長距離ツーリングやキャンプツーリングに最適です。'],
                            '低走行' => ['icon' => '📏', 'title' => '低走行車', 'desc' => '走行距離が少なく、エンジンや駆動系の摩耗が少ない車両です。'],
                            '美車' => ['icon' => '💎', 'title' => '美車', 'desc' => '外装の状態が非常に良好。傷や色あせが少なく、見た目を重視する方におすすめです。'],
                            'ガレージ保管' => ['icon' => '🏠', 'title' => 'ガレージ保管車', 'desc' => '屋内保管で雨風や紫外線から守られてきた車両。外装やゴム類の劣化が少ない傾向です。'],
                        ];

                        $matchedTags = [];
                        if ($tags && count($tags) > 0) {
                            foreach ($tags as $tag) {
                                $name = $tag->name ?? ($tag['name'] ?? '');
                                if (isset($tagDescriptions[$name])) {
                                    $matchedTags[] = array_merge($tagDescriptions[$name], ['tag' => $name]);
                                }
                            }
                        }
                    @endphp

                    @if(count($matchedTags) > 0)
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <div class="flex items-center gap-2 mb-6">
                            <div class="p-2 bg-violet-50 rounded-lg text-violet-600">
                                <i data-lucide="tags" class="w-5 h-5"></i>
                            </div>
                            <h3 class="text-lg font-black text-gray-900">この車両の装備ポイント</h3>
                        </div>
                        <div class="grid gap-3">
                            @foreach($matchedTags as $mt)
                            <div class="flex items-start gap-3 bg-gray-50 rounded-2xl p-4 border border-gray-100">
                                <span class="text-2xl leading-none mt-0.5">{{ $mt['icon'] }}</span>
                                <div class="flex-1 min-w-0">
                                    <a href="{{ route('bikes.search', ['tag' => $mt['tag']]) }}" class="text-sm font-black text-gray-800 hover:text-blue-600 transition mb-0.5 block">{{ $mt['title'] }}</a>
                                    <p class="text-xs text-gray-500 leading-relaxed">{{ $mt['desc'] }}</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- YouTube動画 --}}
                    @if(!empty($videos))
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <div class="flex items-center gap-2 mb-6">
                            <div class="p-2 bg-red-50 rounded-lg text-red-600">
                                <i data-lucide="play-circle" class="w-5 h-5"></i>
                            </div>
                            <h3 class="text-lg font-black text-gray-900">この車種の動画</h3>
                        </div>

                        {{-- 1件目: iframe埋め込み --}}
                        <div class="relative w-full rounded-2xl overflow-hidden mb-4" style="padding-bottom:56.25%">
                            <iframe
                                src="https://www.youtube.com/embed/{{ $videos[0]['video_id'] }}"
                                title="{{ $videos[0]['title'] }}"
                                class="absolute inset-0 w-full h-full"
                                frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                                loading="lazy"
                            ></iframe>
                        </div>
                        <p class="text-xs font-bold text-gray-700 mb-1 line-clamp-2">{{ $videos[0]['title'] }}</p>
                        <p class="text-[11px] text-gray-400 mb-4">{{ $videos[0]['channel'] }}</p>

                        {{-- 2件目以降: サムネカード --}}
                        @if(count($videos) > 1)
                        <div class="space-y-3">
                            @foreach(array_slice($videos, 1) as $video)
                            <a href="https://www.youtube.com/watch?v={{ $video['video_id'] }}" target="_blank" rel="noopener noreferrer" class="flex items-start gap-3 p-2 -mx-2 rounded-xl hover:bg-gray-50 transition-colors">
                                <div class="w-28 h-[64px] rounded-lg overflow-hidden bg-gray-100 flex-shrink-0 relative">
                                    <img src="{{ $video['thumbnail'] }}" alt="" class="w-full h-full object-cover" loading="lazy">
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <div class="bg-black/60 rounded-full p-1"><i data-lucide="play" class="w-3.5 h-3.5 text-white fill-white"></i></div>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-bold text-gray-800 leading-snug mb-1 line-clamp-2">{{ $video['title'] }}</div>
                                    <div class="flex items-center gap-2 text-[11px] text-gray-400">
                                        <span class="font-bold">{{ $video['channel'] }}</span>
                                        @if($video['date'])<span>{{ $video['date'] }}</span>@endif
                                    </div>
                                </div>
                            </a>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endif

                    {{-- 年間維持費シミュレーション --}}
                    @if($bikeModelForUrl && $bikeModelForUrl->displacement)
                    @php
                        $cc = (int) $bikeModelForUrl->displacement;
                        if ($cc <= 50) {
                            $tax = 2000; $jibaiseki = 4325; $shaken = 0; $insurance = 15000; $shakenLabel = 'なし';
                        } elseif ($cc <= 90) {
                            $tax = 2000; $jibaiseki = 4325; $shaken = 0; $insurance = 20000; $shakenLabel = 'なし';
                        } elseif ($cc <= 125) {
                            $tax = 2400; $jibaiseki = 4325; $shaken = 0; $insurance = 25000; $shakenLabel = 'なし';
                        } elseif ($cc <= 250) {
                            $tax = 3600; $jibaiseki = 6110; $shaken = 0; $insurance = 30000; $shakenLabel = 'なし';
                        } else {
                            $tax = 6000; $jibaiseki = 4635; $shaken = 20000; $insurance = 40000; $shakenLabel = '約' . number_format($shaken) . '円/年';
                        }
                        $maintenanceTotal = $tax + $jibaiseki + $shaken + $insurance;
                        $costItems = [
                            ['label' => '軽自動車税', 'amount' => $tax, 'icon' => 'receipt-japanese-yen'],
                            ['label' => '自賠責保険', 'amount' => $jibaiseki, 'icon' => 'shield-check', 'note' => '2年契約÷2'],
                            ['label' => '車検費用', 'amount' => $shaken, 'icon' => 'clipboard-check', 'note' => $cc > 250 ? '2年ごと÷2' : null, 'display' => $shakenLabel],
                            ['label' => '任意保険(目安)', 'amount' => $insurance, 'icon' => 'umbrella'],
                        ];
                    @endphp
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <div class="flex items-center gap-2 mb-6">
                            <div class="p-2 bg-emerald-50 rounded-lg text-emerald-600">
                                <i data-lucide="piggy-bank" class="w-5 h-5"></i>
                            </div>
                            <h3 class="text-lg font-black text-gray-900">年間維持費の目安</h3>
                            <span class="text-[10px] font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full ml-auto">{{ $cc }}cc</span>
                        </div>

                        <div class="space-y-3 mb-5">
                            @foreach($costItems as $item)
                            <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                                <span class="text-xs font-bold text-gray-500 flex items-center gap-1.5">
                                    <i data-lucide="{{ $item['icon'] }}" class="w-3.5 h-3.5 text-gray-300"></i>
                                    {{ $item['label'] }}
                                    @if(!empty($item['note']))
                                        <span class="text-[10px] text-gray-400">({{ $item['note'] }})</span>
                                    @endif
                                </span>
                                <span class="text-sm font-black text-gray-800">
                                    @if(isset($item['display']) && $item['amount'] === 0)
                                        {{ $item['display'] }}
                                    @else
                                        {{ number_format($item['amount']) }}<span class="text-[10px] text-gray-500 ml-0.5">円</span>
                                    @endif
                                </span>
                            </div>
                            @endforeach
                        </div>

                        <div class="bg-emerald-50 rounded-2xl p-4 border border-emerald-100 flex items-center justify-between">
                            <span class="text-sm font-black text-emerald-800">年間合計（税込目安）</span>
                            <span class="text-xl font-black text-emerald-700">
                                約{{ number_format($maintenanceTotal) }}<span class="text-xs text-emerald-500 ml-0.5">円/年</span>
                            </span>
                        </div>

                        <p class="text-[10px] text-gray-400 mt-3 leading-relaxed">
                            ※概算です。任意保険は年齢・等級により異なります。駐車場代・ガソリン代・消耗品は含みません。
                        </p>
                    </div>
                    @endif

                    {{-- この車両の価格分析（統合セクション） --}}
                    @if($listing->model_year && is_numeric($listing->total_price))
                    <div id="price-stats-container"
                         data-model-id="{{ $listing->bike_model_id ?? '' }}"
                         data-total-price="{{ $listing->total_price ?? 0 }}"
                         class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 overflow-hidden">

                        <div class="flex items-center gap-2 mb-6">
                            <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                                <i data-lucide="bar-chart-2" class="w-5 h-5"></i>
                            </div>
                            <h3 class="text-lg font-black text-gray-900">この車両の価格分析</h3>
                        </div>

                        {{-- 相場分析テキスト（サイドバーから移動） --}}
                        @if(isset($stats['avg']) && $stats['avg'] > 0 && is_numeric($listing->total_price) && ($stats['count'] ?? 0) > 1)
                        @php
                            $pMan = (float) $listing->total_price;
                            $avg = $stats['avg'];
                            $diffAbs = abs($stats['diff']);
                            $diffPct = $avg > 0 ? (int) round($diffAbs / $avg * 100) : 0;
                            $count = $stats['count'];
                            $bikeName = $listing->name;
                            $isCheaper = $stats['diff'] < 0;
                        @endphp
                        <div class="mb-6 rounded-2xl p-4 text-xs leading-relaxed font-bold {{ $isCheaper ? 'bg-blue-50 text-blue-800 border border-blue-100' : 'bg-gray-50 text-gray-700 border border-gray-200' }}">
                            <div class="flex items-start gap-2">
                                <i data-lucide="{{ $isCheaper ? 'badge-check' : 'info' }}" class="w-4 h-4 mt-0.5 flex-shrink-0 {{ $isCheaper ? 'text-blue-500' : 'text-gray-400' }}"></i>
                                <p>
                                    この{{ $bikeName }}は支払総額<strong>{{ $pMan }}万円</strong>で、同車種の相場平均<strong>{{ $avg }}万円</strong>より
                                    @if($isCheaper)
                                        約<strong>{{ $diffAbs }}万円（{{ $diffPct }}%）お得</strong>です。
                                        @if($pricePercentile !== null && $pricePercentile <= 30)
                                            現在流通中の{{ $count }}台中、価格の安さは<strong>上位{{ $pricePercentile > 0 ? $pricePercentile : 1 }}%</strong>に入ります。
                                        @endif
                                    @else
                                        約<strong>{{ $diffAbs }}万円高め</strong>です。走行距離の少なさやコンディションを考慮すると妥当な価格帯です。
                                    @endif
                                </p>
                            </div>
                        </div>
                        @endif

                        {{-- 市場ポジション分析（サイドバーから移動） --}}
                        @if(!empty($marketPosition) && !empty($marketPosition['items']))
                        <div class="mb-6 rounded-2xl border border-gray-200 overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-black text-gray-800 flex items-center gap-1.5">
                                        <i data-lucide="bar-chart-3" class="w-3.5 h-3.5 text-blue-500"></i>
                                        市場ポジション
                                    </h4>
                                    <span class="text-[10px] font-bold text-gray-400">同車種{{ $marketPosition['count'] }}台と比較</span>
                                </div>
                            </div>
                            <div class="divide-y divide-gray-100">
                                @foreach($marketPosition['items'] as $mp)
                                <div class="flex items-center justify-between px-4 py-3 bg-white">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm">{!! $mp['icon'] !!}</span>
                                        <span class="text-xs font-bold text-gray-600">{{ $mp['title'] }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-black {{ $mp['rank'] === 'good' ? 'text-green-600' : ($mp['rank'] === 'caution' ? 'text-orange-500' : 'text-gray-700') }}">
                                            {{ $mp['label'] }}
                                        </span>
                                        <div class="text-[10px] text-gray-400 font-bold">
                                            {{ $mp['value'] }} / 平均{{ $mp['avg'] }}
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @php
                                $overallLabel = match($marketPosition['overall']) {
                                    'excellent' => ['label' => 'とてもお買い得', 'color' => 'bg-green-50 text-green-700 border-green-200'],
                                    'good' => ['label' => 'お買い得', 'color' => 'bg-blue-50 text-blue-700 border-blue-200'],
                                    default => ['label' => '標準的', 'color' => 'bg-gray-50 text-gray-600 border-gray-200'],
                                };
                            @endphp
                            <div class="px-4 py-3 {{ $overallLabel['color'] }} border-t text-center">
                                <span class="text-xs font-black">総合評価: {{ $overallLabel['label'] }}</span>
                            </div>
                        </div>
                        @endif

                        {{-- 価格分布グラフ --}}
                        @if(isset($stats) && ($stats['count'] ?? 0) > 0)
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

                            @if(!empty($priceAnalysisText))
                            <div class="mt-4 p-3 bg-blue-50 rounded-xl border border-blue-100">
                                <p class="text-xs text-blue-800 leading-relaxed font-bold">{{ $priceAnalysisText }}</p>
                            </div>
                            @endif
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

                    {{-- FAQ（よくある質問） --}}
                    @php
                        $faqItems = [];
                        $faqBikeName = $listing->name;
                        $faqCc = $bikeModelForUrl?->displacement ? (int) $bikeModelForUrl->displacement : null;
                        $faqModelName = $bikeModelForUrl?->name ?? $faqBikeName;
                        $ec = $bikeModelForUrl?->enriched_content;

                        if ($ec && (!empty($ec['introduction']) || !empty($ec['target_rider']) || !empty($ec['buying_tips']) || !empty($ec['rivals']))) {
                            // enriched_contentがある場合：高品質FAQ 4問のみ
                            if (!empty($ec['introduction'])) {
                                $faqItems[] = ['q' => "{$faqModelName}はどんなバイクですか？", 'a' => $ec['introduction']];
                            }
                            if (!empty($ec['target_rider'])) {
                                $faqItems[] = ['q' => "{$faqModelName}はどんな人におすすめですか？", 'a' => $ec['target_rider']];
                            }
                            if (!empty($ec['buying_tips'])) {
                                $faqItems[] = ['q' => "{$faqModelName}を中古で買う際のポイントは？", 'a' => $ec['buying_tips']];
                            }
                            if (!empty($ec['rivals'])) {
                                $faqItems[] = ['q' => "{$faqModelName}のライバル車種は？", 'a' => $ec['rivals']];
                            }
                        } else {
                            // enriched_contentがない場合：テンプレートFAQにフォールバック
                            if ($faqCc) {
                                if ($faqCc <= 50) { $licenseType = '原付免許（または普通自動車免許）'; }
                                elseif ($faqCc <= 125) { $licenseType = '小型限定普通二輪免許以上'; }
                                elseif ($faqCc <= 400) { $licenseType = '普通二輪免許以上'; }
                                else { $licenseType = '大型二輪免許'; }
                                $faqItems[] = ['q' => "{$faqBikeName}の排気量は？", 'a' => "{$faqCc}ccです。運転には{$licenseType}が必要です。"];
                            }
                            if (!empty($listing->specs['fuel_consumption'])) {
                                $faqItems[] = ['q' => "{$faqBikeName}の燃費は？", 'a' => "カタログ値で{$listing->specs['fuel_consumption']}km/Lです。実燃費は走行環境により異なります。"];
                            }
                            if ($faqCc) {
                                if ($faqCc <= 50) { $faqMaint = 23325; }
                                elseif ($faqCc <= 90) { $faqMaint = 26325; }
                                elseif ($faqCc <= 125) { $faqMaint = 31725; }
                                elseif ($faqCc <= 250) { $faqMaint = 39710; }
                                else { $faqMaint = 70635; }
                                $faqItems[] = ['q' => "{$faqBikeName}の維持費はいくら？", 'a' => "年間約" . number_format($faqMaint) . "円が目安です（軽自動車税・自賠責保険・車検・任意保険含む）。駐車場代やガソリン代は別途かかります。"];
                            }
                            if ($faqCc) {
                                if ($faqCc <= 125) { $beginnerAnswer = "軽量・コンパクトで取り回しやすく、初心者にもおすすめの排気量帯です。車検も不要で維持費を抑えられます。"; }
                                elseif ($faqCc <= 250) { $beginnerAnswer = "車検不要で維持費が比較的安く、高速道路も走れるため初心者にもバランスの良い排気量帯です。"; }
                                elseif ($faqCc <= 400) { $beginnerAnswer = "普通二輪免許で乗れる最大排気量です。パワーに余裕があるため、ある程度の運転経験があると安心です。"; }
                                else { $beginnerAnswer = "大型二輪免許が必要です。車体が重くパワーもあるため、中型バイクで経験を積んでからのステップアップがおすすめです。"; }
                                $faqItems[] = ['q' => "{$faqBikeName}は初心者でも乗れる？", 'a' => $beginnerAnswer];
                            }
                            if (isset($stats['avg']) && $stats['avg'] > 0 && ($stats['count'] ?? 0) > 1) {
                                $faqItems[] = ['q' => "{$faqBikeName}の相場はいくら？", 'a' => "現在の中古相場平均は{$stats['avg']}万円です（流通中{$stats['count']}台のデータより）。最安値は{$stats['min']}万円、最高値は{$stats['max']}万円です。"];
                            }
                            if ($faqCc) {
                                if ($faqCc <= 250) {
                                    $faqItems[] = ['q' => "{$faqBikeName}に車検は必要？", 'a' => "250cc以下のため車検は不要です。ただし自賠責保険の加入と定期的な点検整備は必要です。"];
                                } else {
                                    $faqItems[] = ['q' => "{$faqBikeName}に車検は必要？", 'a' => "251cc以上のため車検が必要です（新車は3年、以降2年ごと）。費用は法定費用+整備費で約5〜10万円が目安です。"];
                                }
                            }
                            if ($listing->total_price && is_numeric($listing->total_price)) {
                                $faqItems[] = ['q' => "この{$faqBikeName}の総額はいくら？", 'a' => "支払総額は{$listing->total_price}万円です。車両本体価格に諸費用（登録費用・自賠責保険・納車整備費等）が含まれています。"];
                            }
                        }
                    @endphp

                    @if(count($faqItems) > 0)
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <div class="flex items-center gap-2 mb-6">
                            <div class="p-2 bg-amber-50 rounded-lg text-amber-600">
                                <i data-lucide="help-circle" class="w-5 h-5"></i>
                            </div>
                            <h3 class="text-lg font-black text-gray-900">{{ $faqBikeName }} のよくある質問</h3>
                        </div>

                        <div class="space-y-2" x-data="{ open: null }">
                            @foreach($faqItems as $i => $faq)
                            <div class="border border-gray-100 rounded-2xl overflow-hidden">
                                <button
                                    @click="open === {{ $i }} ? open = null : open = {{ $i }}"
                                    class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition-colors"
                                >
                                    <span class="text-sm font-black text-gray-800 pr-4">{{ $faq['q'] }}</span>
                                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform duration-200" :class="open === {{ $i }} && 'rotate-180'"></i>
                                </button>
                                <div x-show="open === {{ $i }}" x-cloak
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 -translate-y-1"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                     class="px-4 pb-4">
                                    <p class="text-xs leading-relaxed text-gray-600">{{ $faq['a'] }}</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    {{-- Product JSON-LD 構造化データ --}}
                    <script type="application/ld+json">
                        @php
                            $productSchema = [
                                '@context' => 'https://schema.org',
                                '@type' => 'Product',
                                'name' => $listing->name,
                                'image' => $listing->images ?? [],
                                'description' => Str::limit(strip_tags($listing->description ?? ''), 200),
                                'brand' => [
                                    '@type' => 'Brand',
                                    'name' => $listing->manufacturer->name ?? '',
                                ],
                            ];
                            $price = $listing->total_price ?? $listing->price ?? null;
                            if ($price) {
                                $productSchema['offers'] = [
                                    '@type' => 'Offer',
                                    'price' => $price,
                                    'priceCurrency' => 'JPY',
                                    'availability' => $listing->is_sold_out ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
                                    'url' => url()->current(),
                                    'seller' => [
                                        '@type' => 'Organization',
                                        'name' => $listing->shop->name ?? '',
                                    ],
                                ];
                            }
                        @endphp
                        {!! json_encode($productSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
                    </script>
                    {{-- FAQ JSON-LD 構造化データ --}}
                    <script type="application/ld+json">
                        {!! json_encode([
                            '@context' => 'https://schema.org',
                            '@type' => 'FAQPage',
                            'mainEntity' => array_map(fn($f) => [
                                '@type' => 'Question',
                                'name' => $f['q'],
                                'acceptedAnswer' => [
                                    '@type' => 'Answer',
                                    'text' => $f['a'],
                                ],
                            ], $faqItems),
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
                    </script>
                    @endif

                    {{-- 関連ニュース --}}
                    @if(!empty($news))
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <div class="flex items-center gap-2 mb-6">
                            <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600">
                                <i data-lucide="newspaper" class="w-5 h-5"></i>
                            </div>
                            <h3 class="text-lg font-black text-gray-900">この車種のニュース</h3>
                        </div>
                        <div class="space-y-3">
                            @foreach($news as $article)
                            <a href="{{ route('news.show', $article['id']) }}" class="flex items-start gap-3 p-2 -mx-2 rounded-xl hover:bg-gray-50 transition-colors">
                                <div class="w-20 h-[60px] rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                                    @if(!empty($article['thumbnail_url']))
                                        <img src="{{ $article['thumbnail_url'] }}" alt="" class="w-full h-full object-cover" loading="lazy" onerror="this.style.display='none'">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                                            <i data-lucide="bike" class="w-6 h-6"></i>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-bold text-gray-800 leading-snug mb-1 line-clamp-2">{{ $article['title'] }}</div>
                                    <div class="flex items-center gap-2 text-[11px] text-gray-400">
                                        @if(!empty($article['source']))<span class="font-bold">{{ $article['source'] }}</span>@endif
                                        @if(!empty($article['published_at']))
                                        <span>{{ \Carbon\Carbon::parse($article['published_at'])->format('Y/m/d') }}</span>
                                        @endif
                                        @if(($article['comments_count'] ?? 0) > 0)
                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded-full font-bold text-[10px]">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                            {{ $article['comments_count'] }}
                                        </span>
                                        @endif
                                    </div>
                                </div>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- 施策E: 関連ブログ記事 --}}
                    @if(isset($relatedBlogPosts) && $relatedBlogPosts->isNotEmpty())
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <div class="flex items-center gap-2 mb-6">
                            <div class="p-2 bg-pink-50 rounded-lg text-pink-600">
                                <i data-lucide="file-text" class="w-5 h-5"></i>
                            </div>
                            <h3 class="text-lg font-black text-gray-900">関連記事</h3>
                        </div>
                        <div class="space-y-3">
                            @foreach($relatedBlogPosts as $post)
                            <a href="{{ route('blog.show', $post->slug) }}" class="flex items-start gap-3 p-3 -mx-1 rounded-xl hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-100">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-gray-800 leading-snug mb-1 line-clamp-2">{{ $post->title }}</p>
                                    <p class="text-xs text-gray-400">{{ $post->published_at?->format('Y.m.d') }}</p>
                                </div>
                                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300 flex-shrink-0 mt-1"></i>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- レビューサマリー --}}
                    @if($bikeModelForUrl && isset($reviewDetailedStats) && $reviewDetailedStats['total'] > 0)
                    <div class="bg-gray-50 rounded-2xl p-4 text-sm flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="font-black text-gray-800">{{ $bikeModelForUrl->name }}のオーナー評価:</span>
                        @if($reviewDetailedStats['design']['avg'])
                            <span class="text-gray-600">デザイン<span class="text-yellow-500">★</span>{{ number_format($reviewDetailedStats['design']['avg'], 1) }}</span>
                        @endif
                        @if($reviewDetailedStats['engine']['avg'])
                            <span class="text-gray-400">|</span>
                            <span class="text-gray-600">エンジン<span class="text-yellow-500">★</span>{{ number_format($reviewDetailedStats['engine']['avg'], 1) }}</span>
                        @endif
                        @if($reviewDetailedStats['handling']['avg'])
                            <span class="text-gray-400">|</span>
                            <span class="text-gray-600">取り回し<span class="text-yellow-500">★</span>{{ number_format($reviewDetailedStats['handling']['avg'], 1) }}</span>
                        @endif
                        @if($bikeModelForUrl->manufacturer && $bikeModelForUrl->slug)
                        <a href="{{ route('bikes.model_detail', ['mfrSlug' => $bikeModelForUrl->manufacturer->slug, 'modelSlug' => $bikeModelForUrl->slug]) }}#community"
                           class="text-blue-600 hover:underline font-bold ml-auto">詳細を見る</a>
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

                        {{-- 車種モデルのレビュー統計プレビュー --}}
                        @if($bikeModelForUrl && isset($reviewDetailedStats) && $reviewDetailedStats['total'] > 0)
                        <div class="bg-gray-50 rounded-2xl p-4 mb-4 border border-gray-100">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-black text-gray-800">{{ $bikeModelForUrl->name }}のオーナー評価</span>
                                <span class="text-xs font-bold text-gray-400">{{ $reviewDetailedStats['total'] }}件のレビュー</span>
                            </div>
                            <div class="flex flex-wrap gap-3 text-xs font-bold text-gray-600 mb-3">
                                @if($reviewDetailedStats['design']['avg'])
                                    <span>デザイン <span class="text-yellow-500">★</span>{{ number_format($reviewDetailedStats['design']['avg'], 1) }}</span>
                                @endif
                                @if($reviewDetailedStats['engine']['avg'])
                                    <span>エンジン <span class="text-yellow-500">★</span>{{ number_format($reviewDetailedStats['engine']['avg'], 1) }}</span>
                                @endif
                                @if($reviewDetailedStats['handling']['avg'])
                                    <span>取り回し <span class="text-yellow-500">★</span>{{ number_format($reviewDetailedStats['handling']['avg'], 1) }}</span>
                                @endif
                                @if($reviewDetailedStats['fuel_economy']['avg'])
                                    <span>燃費 <span class="text-yellow-500">★</span>{{ number_format($reviewDetailedStats['fuel_economy']['avg'], 1) }}</span>
                                @endif
                                @if($reviewDetailedStats['cost_performance']['avg'])
                                    <span>コスパ <span class="text-yellow-500">★</span>{{ number_format($reviewDetailedStats['cost_performance']['avg'], 1) }}</span>
                                @endif
                            </div>
                            @if($bikeModelForUrl->manufacturer && $bikeModelForUrl->slug)
                            <a href="{{ route('bikes.model_detail', ['mfrSlug' => $bikeModelForUrl->manufacturer->slug, 'modelSlug' => $bikeModelForUrl->slug]) }}#community"
                               class="text-xs font-bold text-blue-600 hover:underline inline-flex items-center gap-1">
                                レビュー・評価の詳細を見る <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                            </a>
                            @endif
                        </div>
                        @endif

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
                                    @php
                                        $detailRatings = [
                                            'デザイン' => $review->rating_design,
                                            'エンジン' => $review->rating_engine,
                                            '取り回し' => $review->rating_handling,
                                            '燃費' => $review->rating_fuel_economy,
                                            'コスパ' => $review->rating_cost_performance,
                                        ];
                                        $hasDetail = collect($detailRatings)->filter()->isNotEmpty();
                                    @endphp
                                    @if($hasDetail)
                                    <div class="flex flex-wrap gap-x-3 gap-y-1 mb-3">
                                        @foreach($detailRatings as $label => $val)
                                            @if($val)
                                            <span class="text-[10px] font-bold text-gray-500">{{ $label }}<span class="text-yellow-500 ml-0.5">{{ str_repeat('★', $val) }}</span></span>
                                            @endif
                                        @endforeach
                                    </div>
                                    @endif
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

                    {{-- 関連パーツ --}}
                    @if(!empty($relatedParts))
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <h3 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="wrench" class="w-5 h-5 text-blue-500"></i>
                            {{ $bikeModelForUrl->name ?? '' }} のパーツを探す
                        </h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach($relatedParts as $part)
                            @php
                                $hasCode = !empty($part['jan_code']) || !empty($part['part_number']);
                                $compareParams = [];
                                if (!empty($part['jan_code'])) $compareParams['jan'] = $part['jan_code'];
                                if (!empty($part['part_number'])) $compareParams['partnum'] = $part['part_number'];
                                $compareParams['keyword'] = \Illuminate\Support\Str::limit($part['name'], 100, '');
                                $compareUrl = route('parts.compare', $compareParams);
                                $fallbackQuery = $part['part_number'] ?: \Illuminate\Support\Str::limit($part['name'], 60, '');
                                $yahooUrl = 'https://shopping.yahoo.co.jp/search?p=' . urlencode($fallbackQuery);
                                $amazonQuery = $part['jan_code'] ?: $part['part_number'] ?: \Illuminate\Support\Str::limit($part['name'], 60, '');
                                $amazonUrl = 'https://www.amazon.co.jp/s?k=' . urlencode($amazonQuery);
                                $amazonTag = config('services.amazon.associate_tag');
                                if ($amazonTag) $amazonUrl .= '&tag=' . urlencode($amazonTag);
                            @endphp
                            <div class="bg-gray-50 rounded-xl overflow-hidden hover:shadow-md transition-shadow flex flex-col">
                                <div class="aspect-square bg-white flex items-center justify-center overflow-hidden">
                                    @if($part['image'])
                                        <img src="{{ str_replace('?_ex=128x128', '?_ex=300x300', $part['image']) }}"
                                            alt="{{ $part['name'] }}" class="w-full h-full object-contain p-2" loading="lazy">
                                    @else
                                        <span class="text-gray-300 text-3xl">🔧</span>
                                    @endif
                                </div>
                                <div class="p-2.5 flex flex-col flex-grow">
                                    <h3 class="text-xs font-bold text-gray-800 line-clamp-2 mb-1">{{ $part['name'] }}</h3>
                                    <p class="text-sm font-black text-red-600 mb-2">&yen;{{ number_format($part['price']) }}</p>
                                    <div class="mt-auto flex flex-col gap-1">
                                        @if($hasCode)
                                            <a href="{{ $compareUrl }}"
                                                class="block text-center text-[11px] font-bold py-1.5 rounded-lg transition-colors bg-gray-800 hover:bg-gray-900 text-white">
                                                価格を比較する
                                            </a>
                                        @else
                                            <a href="{{ $part['url'] }}" target="_blank" rel="noopener noreferrer"
                                                class="block text-center text-[11px] font-bold py-1.5 rounded-lg transition-colors bg-red-500 hover:bg-red-600 text-white">
                                                楽天市場で見る
                                            </a>
                                            <a href="{{ $yahooUrl }}" target="_blank" rel="noopener noreferrer"
                                                class="block text-center text-[11px] font-bold py-1.5 rounded-lg transition-colors bg-blue-500 hover:bg-blue-600 text-white"
                                                style="background-color:#3b82f6;color:#fff">
                                                Yahoo!で探す
                                            </a>
                                            <a href="{{ $amazonUrl }}" target="_blank" rel="noopener noreferrer"
                                                class="block text-center text-[11px] font-bold py-1.5 rounded-lg transition-colors bg-amber-500 hover:bg-amber-600 text-white"
                                                style="background-color:#f59e0b;color:#fff">
                                                Amazonで探す
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        <div class="mt-4 text-center">
                            <a href="{{ route('parts.index', ['bike' => $bikeModelForUrl->name ?? '']) }}"
                                class="inline-flex items-center gap-1 text-sm font-bold text-blue-600 hover:text-blue-800 transition-colors">
                                {{ $bikeModelForUrl->name ?? '' }} のパーツをもっと見る
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </a>
                        </div>
                    </div>
                    @endif
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
                            </div>

                            {{-- この車種の販売データ --}}
                            @if(!empty($rankingStats))
                            <div class="mb-6 bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                                <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
                                    <i data-lucide="bar-chart-3" class="w-4 h-4 text-yellow-500"></i>
                                    <h3 class="text-sm font-black text-gray-900">この車種の販売データ</h3>
                                </div>
                                <div class="p-4">
                                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px" class="mb-3">
                                        <div class="bg-gray-50 rounded-xl p-3 text-center">
                                            <p class="text-[10px] text-gray-400 font-bold mb-1">先月販売台数</p>
                                            <p class="text-lg font-black text-gray-900">{{ number_format($rankingStats['sold']) }}<span class="text-xs text-gray-400 ml-0.5">台</span></p>
                                        </div>
                                        <div class="bg-gray-50 rounded-xl p-3 text-center">
                                            <p class="text-[10px] text-gray-400 font-bold mb-1">全車種順位</p>
                                            <p class="text-lg font-black {{ $rankingStats['rank'] && $rankingStats['rank'] <= 10 ? 'text-yellow-600' : 'text-gray-900' }}">
                                                @if($rankingStats['rank'])
                                                    {{ $rankingStats['rank'] }}<span class="text-xs text-gray-400 ml-0.5">位</span>
                                                    <span class="text-[10px] text-gray-400">/{{ number_format($rankingStats['totalModels']) }}</span>
                                                @else
                                                    -
                                                @endif
                                            </p>
                                        </div>
                                        <div class="bg-gray-50 rounded-xl p-3 text-center">
                                            <p class="text-[10px] text-gray-400 font-bold mb-1">1日平均</p>
                                            <p class="text-lg font-black text-gray-900">{{ $rankingStats['dailyAvg'] }}<span class="text-xs text-gray-400 ml-0.5">台/日</span></p>
                                        </div>
                                        <div class="bg-gray-50 rounded-xl p-3 text-center">
                                            <p class="text-[10px] text-gray-400 font-bold mb-1">平均在庫日数</p>
                                            <p class="text-lg font-black text-gray-900">{{ $rankingStats['avgDays'] }}<span class="text-xs text-gray-400 ml-0.5">日</span></p>
                                        </div>
                                    </div>
                                    @if($rankingStats['topPrice'] || $rankingStats['topRegion'])
                                    <div class="space-y-1.5 mb-3">
                                        @if($rankingStats['topPrice'])
                                        <p class="text-[11px] text-gray-500 font-bold flex items-center gap-1.5">
                                            <i data-lucide="tag" class="w-3 h-3 text-gray-400"></i>
                                            売れ筋価格帯: <span class="text-gray-800">{{ $rankingStats['topPrice'] }}</span>
                                        </p>
                                        @endif
                                        @if($rankingStats['topRegion'])
                                        <p class="text-[11px] text-gray-500 font-bold flex items-center gap-1.5">
                                            <i data-lucide="map-pin" class="w-3 h-3 text-gray-400"></i>
                                            最多販売地域: <span class="text-gray-800">{{ $rankingStats['topRegion'] }}</span>
                                        </p>
                                        @endif
                                    </div>
                                    @endif
                                    @if($listing->bike_model_id)
                                    <a href="{{ route('ranking.model_stats', $listing->bike_model_id) }}" class="block w-full text-center py-2.5 rounded-xl bg-yellow-50 border border-yellow-200 text-xs font-black text-yellow-700 hover:bg-yellow-100 transition-colors">
                                        詳しい分析を見る →
                                    </a>
                                    @endif
                                </div>
                            </div>
                            @endif

                            @if(!$listing->is_sold_out)
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
                            @endif

                            <div class="space-y-3">
                                @if($listing->is_sold_out)
                                <div class="bg-gray-100 text-gray-500 font-bold text-center py-4 rounded-xl">
                                    この車両は販売終了しました
                                </div>
                                <a href="{{ route('bikes.search', ['manufacturer_id' => $listing->manufacturer_id, 'bike_model_id' => $listing->bike_model_id]) }}"
                                   class="block w-full bg-blue-600 hover:bg-blue-500 text-white font-black text-center py-4 rounded-xl shadow-lg transition hover:-translate-y-1">
                                    {{ $listing->bike_model_name ?? 'この車種' }}の販売中車両を探す
                                </a>
                                @else
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
                                @endif
                            </div>

                            @if(!$listing->is_sold_out)
                            <div class="mt-6 pt-6 border-t border-gray-100 text-center">
                                <div class="text-[10px] font-bold text-gray-400">
                                    ※ 見積もり依頼は無料です。<br>
                                    MotoHubを見たとお伝えください。
                                </div>
                            </div>
                            @endif
                        </div>
                        @if(!$listing->is_sold_out)
                        {{-- 検討を促すサイドバーパーツ --}}
                        <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-3xl p-6 text-white shadow-lg">
                            <h4 class="text-xs font-black uppercase tracking-widest text-orange-400 mb-3 flex items-center gap-2">
                                <i data-lucide="alert-circle" class="w-4 h-4"></i> 売却済みにご注意ください
                            </h4>
                            <p class="text-[11px] font-bold leading-relaxed text-gray-300">
                                この車両は現在 <span class="text-white font-black text-sm">{{ $listing->engagement['view_count_today'] ?? 0 }}名</span> が閲覧し、<span class="text-red-400 font-black text-sm">{{ $listing->engagement['favorite_count'] ?? 0 }}名</span> が検討リストに追加しています。中古バイクは1点物のため、タッチの差で売約済みとなるケースが多くなっています。
                            </p>
                        </div>
                        @endif
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

            {{-- ===== 売り切れ車両専用セクション ===== --}}
            @if($listing->is_sold_out && $soldOutData)
            <div class="mt-8 space-y-6">

                {{-- 実装②: この車両の販売記録 --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8">
                    <div class="flex items-center gap-2 mb-5">
                        <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                            <i data-lucide="receipt" class="w-5 h-5"></i>
                        </div>
                        <h2 class="text-lg font-black text-gray-900">この車両の販売記録</h2>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-5">
                        @if($soldOutData['listing_days'] !== null)
                        <p class="text-base font-bold text-gray-800 leading-relaxed">
                            この車両は掲載から<span class="text-xl font-black text-blue-600">{{ $soldOutData['listing_days'] }}日</span>で売却されました
                            @if($soldOutData['sold_price'])
                            （販売価格: <span class="font-black text-red-600">{{ $soldOutData['sold_price'] }}万円</span>）
                            @endif
                        </p>
                        @endif
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mt-4">
                            @if($soldOutData['created_at'])
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">掲載開始</p>
                                <p class="text-sm font-black text-gray-800 mt-1">{{ $soldOutData['created_at'] }}</p>
                            </div>
                            @endif
                            @if($soldOutData['updated_at'])
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">売却日</p>
                                <p class="text-sm font-black text-gray-800 mt-1">{{ $soldOutData['updated_at'] }}</p>
                            </div>
                            @endif
                            @if($soldOutData['sold_price'])
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">販売価格</p>
                                <p class="text-sm font-black text-red-600 mt-1">{{ $soldOutData['sold_price'] }}万円</p>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- 車種の市場データ --}}
                    @if($soldOutData['market_avg_price'] || $soldOutData['ranking_rank'] || $soldOutData['avg_sell_days'])
                    <div class="mt-5 pt-5 border-t border-gray-100">
                        <h3 class="text-sm font-black text-gray-700 mb-3 flex items-center gap-1.5">
                            <i data-lucide="trending-up" class="w-4 h-4 text-green-500"></i>
                            {{ $listing->bike_model_name ?? 'この車種' }}の市場データ
                        </h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            @if($soldOutData['market_avg_price'])
                            <div class="bg-blue-50 rounded-xl p-4 text-center">
                                <p class="text-[10px] font-bold text-blue-500 uppercase tracking-widest">現在の平均価格</p>
                                <p class="text-xl font-black text-blue-700 mt-1">{{ $soldOutData['market_avg_price'] }}<span class="text-xs">万円</span></p>
                                <p class="text-[10px] text-blue-400 font-bold mt-0.5">販売中{{ $soldOutData['market_active_count'] }}台</p>
                            </div>
                            @endif
                            @if($soldOutData['ranking_rank'])
                            <div class="bg-amber-50 rounded-xl p-4 text-center">
                                <p class="text-[10px] font-bold text-amber-500 uppercase tracking-widest">売れ筋ランキング</p>
                                <p class="text-xl font-black text-amber-700 mt-1">{{ $soldOutData['ranking_rank'] }}<span class="text-xs">位</span></p>
                                <p class="text-[10px] text-amber-400 font-bold mt-0.5">{{ $soldOutData['ranking_total'] }}車種中</p>
                            </div>
                            @endif
                            @if($soldOutData['avg_sell_days'])
                            <div class="bg-green-50 rounded-xl p-4 text-center">
                                <p class="text-[10px] font-bold text-green-500 uppercase tracking-widest">平均売却日数</p>
                                <p class="text-xl font-black text-green-700 mt-1">{{ $soldOutData['avg_sell_days'] }}<span class="text-xs">日</span></p>
                                <p class="text-[10px] text-green-400 font-bold mt-0.5">先月の実績</p>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>

                {{-- 実装③: 同車種の販売中車両 --}}
                @if($activeSameModel->isNotEmpty())
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8">
                    <div class="flex items-center gap-2 mb-5">
                        <div class="p-2 bg-green-50 rounded-lg text-green-600">
                            <i data-lucide="bike" class="w-5 h-5"></i>
                        </div>
                        <h2 class="text-lg font-black text-gray-900">{{ $listing->bike_model_name ?? 'この車種' }}の販売中車両</h2>
                        <span class="text-xs font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded-full">{{ $activeSameModel->count() }}台</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4">
                        @foreach($activeSameModel as $active)
                        @php
                            $activeImg = is_string($active->local_image_paths) ? json_decode($active->local_image_paths, true) : $active->local_image_paths;
                        @endphp
                        <a href="{{ route('bikes.show', $active->id) }}" class="bg-white rounded-xl border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">
                            <div class="aspect-[4/3] bg-gray-100 overflow-hidden">
                                @if(!empty($activeImg) && is_array($activeImg))
                                <img src="{{ asset('storage/' . ltrim($activeImg[0], '/')) }}" alt="{{ $active->title }}" class="w-full h-full object-cover" loading="lazy">
                                @else
                                <div class="w-full h-full flex items-center justify-center text-gray-300">
                                    <i data-lucide="image-off" class="w-6 h-6"></i>
                                </div>
                                @endif
                            </div>
                            <div class="p-3">
                                @if($active->total_price)
                                <p class="text-sm font-black text-red-600 mb-1">{{ number_format($active->total_price / 10000, 1) }}万円</p>
                                @endif
                                <p class="text-xs font-bold text-gray-800 line-clamp-2 mb-1">{{ $active->title }}</p>
                                <div class="flex items-center gap-2 text-[10px] text-gray-400">
                                    @if($active->model_year)<span>{{ $active->model_year }}年</span>@endif
                                    @if($active->mileage)<span>{{ number_format($active->mileage) }}km</span>@endif
                                    @if($active->shop?->prefecture)<span>{{ $active->shop->prefecture }}</span>@endif
                                </div>
                            </div>
                        </a>
                        @endforeach
                    </div>
                    @if($bikeModelForUrl)
                    <div class="mt-4 text-center">
                        <a href="{{ route('bikes.search', ['manufacturer_id' => $listing->manufacturer_id, 'bike_model_id' => $listing->bike_model_id]) }}"
                           class="inline-flex items-center gap-1 text-sm font-bold text-blue-600 hover:text-blue-800 transition-colors">
                            {{ $listing->bike_model_name ?? 'この車種' }}の販売中車両をもっと見る
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    </div>
                    @endif
                </div>
                @elseif($listing->bike_model_id)
                {{-- 販売中車両が0台の場合 --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8 text-center">
                    <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="inbox" class="w-6 h-6 text-gray-400"></i>
                    </div>
                    <p class="text-sm font-bold text-gray-600 mb-1">現在{{ $listing->bike_model_name ?? 'この車種' }}の在庫はありません</p>
                    <p class="text-xs text-gray-400 mb-4">新しい在庫が入荷したらお知らせします</p>
                    <a href="{{ route('bikes.search', ['keyword' => $listing->bike_model_name ?? '']) }}"
                       class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-blue-600 text-white text-xs font-bold rounded-full hover:bg-blue-700 transition">
                        <i data-lucide="bell" class="w-3.5 h-3.5"></i>
                        類似車種を探す
                    </a>
                </div>
                @endif

                {{-- 実装⑤: 内部リンクの強化 --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8">
                    <h2 class="text-sm font-black text-gray-500 uppercase tracking-widest mb-4">関連ページ</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @if($bikeModelForUrl)
                        <a href="{{ route('bikes.model_detail.fallback', $bikeModelForUrl->id) }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors border border-gray-100">
                            <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center text-blue-500 flex-shrink-0">
                                <i data-lucide="book-open" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-800">{{ $listing->bike_model_name }}の車種詳細</p>
                                <p class="text-[10px] text-gray-400 font-bold">スペック・相場・レビュー</p>
                            </div>
                        </a>
                        @endif
                        @if($listing->manufacturer_id)
                        <a href="{{ route('bikes.search', ['manufacturer_id' => $listing->manufacturer_id]) }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors border border-gray-100">
                            <div class="w-10 h-10 bg-orange-50 rounded-lg flex items-center justify-center text-orange-500 flex-shrink-0">
                                <i data-lucide="layers" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-800">{{ $listing->maker }}の車両一覧</p>
                                <p class="text-[10px] text-gray-400 font-bold">全車種の在庫を検索</p>
                            </div>
                        </a>
                        @endif
                        @if($listing->shop_id)
                        <a href="{{ route('shops.show', $listing->shop_id) }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors border border-gray-100">
                            <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center text-green-500 flex-shrink-0">
                                <i data-lucide="store" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-800">{{ $listing->shop_name }}の在庫一覧</p>
                                <p class="text-[10px] text-gray-400 font-bold">この販売店の他の車両</p>
                            </div>
                        </a>
                        @endif
                        <a href="{{ route('ranking.index') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors border border-gray-100">
                            <div class="w-10 h-10 bg-amber-50 rounded-lg flex items-center justify-center text-amber-500 flex-shrink-0">
                                <i data-lucide="trophy" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-800">売れ筋ランキング</p>
                                <p class="text-[10px] text-gray-400 font-bold">人気車種の販売動向</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            @endif

            {{-- 近くの駐車場・ショップ・回遊リンク --}}
            <div class="mt-12 space-y-6">
                <x-nearby-shops :nearbyShops="$nearbyShops" :latitude="$shopLat" :longitude="$shopLng" />
                <x-nearby-parkings :nearbyParkings="$nearbyParkings" :latitude="$shopLat" :longitude="$shopLng" :prefecture="$listing->prefecture" />

                {{-- 都道府県エリア駐車場リンク --}}
                @if($listing->prefecture)
                <div class="bg-green-50 rounded-2xl p-5 border border-green-100 flex items-center justify-between hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">🅿️</span>
                        <span class="text-sm font-black text-gray-800">{{ $listing->prefecture }}のバイク駐車場をもっと探す</span>
                    </div>
                    <a href="{{ route('parking.area.prefecture', $listing->prefecture) }}" class="text-xs font-bold text-green-600 hover:text-green-800 flex items-center gap-1">
                        エリア一覧 <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                </div>
                @endif
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
    {{-- bottom-navの高さ(60px)の上に配置 --}}
    @if($listing->is_sold_out)
    {{-- 売り切れ時: 同車種の検索へ誘導 --}}
    <div class="fixed bottom-[60px] md:bottom-0 left-0 w-full bg-white/90 backdrop-blur-md border-t border-gray-200 p-3 sm:p-4 lg:hidden z-50 shadow-[0_-4px_12px_rgba(0,0,0,0.05)]">
        <div class="flex gap-2 sm:gap-3 items-center">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-gray-500">この車両は販売終了しました</p>
            </div>
            <a href="{{ route('bikes.search', ['manufacturer_id' => $listing->manufacturer_id, 'bike_model_id' => $listing->bike_model_id]) }}"
               class="w-48 bg-blue-600 text-white font-black flex items-center justify-center gap-1.5 rounded-xl shadow-lg py-2.5 active:scale-95 transition-transform shrink-0">
                <i data-lucide="search" class="w-4 h-4"></i>
                <span class="text-xs sm:text-sm">販売中車両を探す</span>
            </a>
        </div>
    @else
    <div id="mobile-price-bar" class="fixed bottom-[60px] md:bottom-0 left-0 w-full bg-white/90 backdrop-blur-md border-t border-gray-200 p-3 sm:p-4 lg:hidden z-50 shadow-[0_-4px_12px_rgba(0,0,0,0.05)]">
        <div class="flex gap-2 sm:gap-3 items-center">

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
                @if($listing->total_price && is_numeric($listing->total_price))
                <div class="text-lg sm:text-xl font-black text-red-500 leading-none truncate">{{ $listing->total_price }}<span class="text-[10px] sm:text-xs text-gray-500 ml-0.5">万円</span></div>
                @elseif($listing->price && $listing->price !== '-')
                <div class="text-lg sm:text-xl font-black text-red-500 leading-none truncate">{{ $listing->price }}<span class="text-[10px] sm:text-xs text-gray-500 ml-0.5">万円</span></div>
                @endif
            </div>

            <a href="{{ $listing->url }}" target="_blank" class="w-36 sm:w-48 bg-red-600 text-white font-black flex flex-col items-center justify-center rounded-xl shadow-lg shadow-red-500/30 py-2 sm:py-2.5 active:scale-95 transition-transform shrink-0">
                <span class="text-xs sm:text-sm">在庫確認・見積</span>
                <span class="text-[8px] sm:text-[9px] font-medium opacity-90 flex items-center gap-1 mt-0.5">
                    <i data-lucide="users" class="w-2.5 h-2.5"></i> {{ $listing->engagement['favorite_count'] ?? 0 }}名が検討中
                </span>
            </a>
        </div>
    @endif
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
    <div id="review-modal" class="fixed inset-0 z-[100] hidden items-start justify-center py-8 px-4 sm:px-0 overflow-y-auto">
        {{-- 背景の黒いオーバーレイ --}}
        <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="closeReviewModal()"></div>
        
        {{-- モーダル本体 --}}
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto transform transition scale-95 opacity-0 duration-300" id="review-modal-content">
            
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

                    {{-- 項目別評価（任意） --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @php
                            $ratingItems = [
                                'rating_design' => 'デザイン',
                                'rating_engine' => 'エンジン性能',
                                'rating_handling' => '取り回し',
                                'rating_fuel_economy' => '燃費',
                                'rating_cost_performance' => 'コスパ',
                            ];
                        @endphp
                        @foreach($ratingItems as $fieldName => $fieldLabel)
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">{{ $fieldLabel }} <span class="text-gray-300">（任意）</span></label>
                            <input type="hidden" name="{{ $fieldName }}" id="{{ $fieldName }}-value" value="">
                            <div class="flex items-center gap-1">
                                @for($s = 1; $s <= 5; $s++)
                                <button type="button" class="detail-star-btn p-0.5 transition-transform hover:scale-110 focus:outline-none text-gray-200" data-field="{{ $fieldName }}" data-val="{{ $s }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 transition-colors" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                    </svg>
                                </button>
                                @endfor
                            </div>
                        </div>
                        @endforeach
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