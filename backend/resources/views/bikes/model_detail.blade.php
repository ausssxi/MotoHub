<x-layout>
    <x-slot:title>{{ $model->manufacturer?->name }} {{ $model->name }}の中古バイク{{ $activeCount > 0 ? '【' . $activeCount . '台】' : '' }}{{ !empty($stats) && isset($stats['avg']) && $stats['count'] > 0 ? '相場' . $stats['min'] . '〜' . $stats['max'] . '万円' : '相場・価格' }} | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $model->manufacturer?->name }} {{ $model->name }}の中古バイク{{ $activeCount > 0 ? $activeCount . '台掲載中' : '情報' }}。{{ !empty($stats) && isset($stats['avg']) && $stats['count'] > 0 ? '価格' . $stats['min'] . '〜' . $stats['max'] . '万円（平均' . $stats['avg'] . '万円）。' : '' }}スペック・維持費・相場推移・口コミをMotoHubで比較検討。</x-slot:metaDescription>
    <x-slot:canonical>{{ url($model->seo_url) }}</x-slot:canonical>
    @if($model->image_url)
    <x-slot:ogImage>{{ $model->image_url }}</x-slot:ogImage>
    @endif

    <x-slot:styles>
        <x-jsonld.model-product :model="$model" :stats="$stats" :reviewStats="$reviewStats ?? null" />
        <x-jsonld.breadcrumb-model :model="$model" />
    </x-slot:styles>
    
    <x-slot:scripts>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
        <script>
            window.bikeModelStats = @json($stats ?? []);
            window.bikeModelHistory = @json($history ?? []);
        </script>
        <script>window.__bikeModelId = {{ $model->id }};</script>
        <script src="{{ asset('js/promo/engagement-banner.js') }}?v={{ filemtime(public_path('js/promo/engagement-banner.js')) }}" defer></script>
        <script src="{{ asset('js/bikes/model_detail.js') }}?v={{ filemtime(public_path('js/bikes/model_detail.js')) }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('review-form-element');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const submitBtn = form.querySelector('button[type="submit"]');
                        const originalText = submitBtn.innerHTML;
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = 'スパムチェック中...';
                        grecaptcha.ready(function() {
                            grecaptcha.execute('{{ config('services.recaptcha.site_key') }}', {action: 'submit_review'}).then(function(token) {
                                document.getElementById('recaptcha-token').value = token;
                                form.submit();
                            }).catch(function() {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                                alert('スパムチェックに失敗しました。時間をおいて再試行してください。');
                            });
                        });
                    });
                }
            });
        </script>
        {{-- pushSubscribe コンポーネントは push-manager.js で登録済み --}}
        {{-- セクションナビ制御 --}}
        <script>
            (function() {
                function initSectionNav() {
                    var nav = document.getElementById('section-nav');
                    var header = document.getElementById('model-header');
                    if (!nav || !header) return;

                    var links = nav.querySelectorAll('.section-nav-link');
                    var navHeight = 44;
                    var navVisible = false;

                    // セクション要素を取得
                    var sections = [];
                    links.forEach(function(l) {
                        var el = document.getElementById(l.dataset.navTarget);
                        if (el) sections.push(el);
                    });

                    // スクロールでナビ表示/非表示 + アクティブセクション更新
                    function onScroll() {
                        var headerBottom = header.getBoundingClientRect().bottom;
                        var shouldShow = headerBottom < 0;

                        if (shouldShow !== navVisible) {
                            navVisible = shouldShow;
                            nav.style.transform = shouldShow ? 'translateY(0)' : 'translateY(-100%)';
                            nav.style.opacity = shouldShow ? '1' : '0';
                        }

                        // アクティブセクション判定
                        if (shouldShow) {
                            var currentId = '';
                            for (var i = 0; i < sections.length; i++) {
                                var rect = sections[i].getBoundingClientRect();
                                if (rect.top <= navHeight + 60) {
                                    currentId = sections[i].id;
                                }
                            }
                            links.forEach(function(l) {
                                var isActive = l.dataset.navTarget === currentId;
                                l.classList.toggle('text-blue-600', isActive);
                                l.classList.toggle('bg-blue-50', isActive);
                                l.classList.toggle('text-gray-500', !isActive);
                            });
                        }
                    }

                    window.addEventListener('scroll', onScroll, { passive: true });
                    onScroll(); // 初回チェック

                    // スムーズスクロール
                    links.forEach(function(link) {
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            var target = document.getElementById(this.dataset.navTarget);
                            if (target) {
                                var top = target.getBoundingClientRect().top + window.pageYOffset - navHeight - 8;
                                window.scrollTo({ top: top, behavior: 'smooth' });
                            }
                        });
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initSectionNav);
                } else {
                    initSectionNav();
                }
            })();
        </script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    {{-- ★追加: パンくずリスト --}}
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <ol class="flex items-center gap-1.5 text-xs text-gray-400 font-bold overflow-x-auto whitespace-nowrap">
                <li><a href="{{ route('bikes.index') }}" class="hover:text-blue-600 transition-colors">トップ</a></li>
                <li><i data-lucide="chevron-right" class="w-3 h-3 inline"></i></li>
                <li><a href="{{ route('bikes.models') }}" class="hover:text-blue-600 transition-colors">車種一覧</a></li>
                <li><i data-lucide="chevron-right" class="w-3 h-3 inline"></i></li>
                <li><a href="{{ route('bikes.search', ['manufacturer_id' => $model->manufacturer_id]) }}" class="hover:text-blue-600 transition-colors">{{ $model->manufacturer->name }}</a></li>
                <li><i data-lucide="chevron-right" class="w-3 h-3 inline"></i></li>
                <li class="text-gray-700">{{ $model->name }}</li>
            </ol>
        </div>
    </nav>

    {{-- ヘッダーエリア --}}
    <div id="model-header" class="bg-gray-900 text-white pt-10 pb-16 relative overflow-hidden">
        <div class="absolute inset-0 z-0 opacity-30">
            @if($model->image_url)
                <img src="{{ $model->image_url }}" class="w-full h-full object-cover blur-sm" alt="" fetchpriority="high" decoding="async">
            @else
                <img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=2070" class="w-full h-full object-cover blur-sm" alt="" fetchpriority="high" decoding="async">
            @endif
        </div>
        <div class="max-w-7xl mx-auto px-4 relative z-10">
            <div class="inline-block bg-blue-600 text-white text-[10px] font-bold px-2 py-1 rounded mb-2">
                {{ $model->manufacturer->name }}
            </div>
            <h1 class="text-3xl sm:text-5xl font-black tracking-tight mb-2">
                {{ $model->name }} <span class="text-2xl sm:text-3xl text-gray-300">の中古車・買取相場</span>
            </h1>
            <p class="text-gray-300 font-bold text-sm">
                {{ $model->manufacturer->name }}｜価格推移・リセールバリュー・オーナーレビュー
            </p>

            {{-- ★追加: ヘッダーに要約スタッツ --}}
            <div class="flex flex-wrap gap-4 mt-6">
                @if(!empty($stats) && isset($stats['avg']) && $stats['count'] > 0)
                <div class="bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2">
                    <span class="text-[10px] text-gray-400 block">中古平均価格</span>
                    <span class="text-xl font-black">{{ $stats['avg'] }}<span class="text-xs">万円</span></span>
                </div>
                @endif
                @if($activeCount > 0)
                <div class="bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2">
                    <span class="text-[10px] text-gray-400 block">販売中</span>
                    <span class="text-xl font-black">{{ number_format($activeCount) }}<span class="text-xs">台</span></span>
                </div>
                @endif
                @if(isset($reviewStats) && $reviewStats->count > 0)
                <div class="bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2">
                    <span class="text-[10px] text-gray-400 block">オーナー評価</span>
                    <span class="text-xl font-black flex items-center gap-1">
                        <i data-lucide="star" class="w-4 h-4 fill-current text-yellow-400"></i>
                        {{ $reviewStats->avg_rating }}
                        <span class="text-xs text-gray-400">({{ $reviewStats->count }}件)</span>
                    </span>
                </div>
                @endif
            </div>

            {{-- 通知購読エリア --}}
            <div class="mt-4" id="push-area-header" data-model-id="{{ $model->id }}"></div>
        </div>
    </div>

    {{-- セクションナビ（fixed：ヘッダーがスクロールアウトしたらトップに表示） --}}
    <div id="section-nav" class="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-sm border-b border-gray-100 shadow-sm transition-all duration-300" style="transform:translateY(-100%);opacity:0;will-change:transform,opacity;">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center gap-1 overflow-x-auto scrollbar-hide py-2.5 -mx-1">
                @php
                    $sections = [
                        ['id' => 'overview', 'label' => '概要'],
                        ['id' => 'specs', 'label' => 'スペック'],
                    ];
                    if (!empty($news)) {
                        $sections[] = ['id' => 'news', 'label' => 'ニュース'];
                    }
                    if (!empty($videos)) {
                        $sections[] = ['id' => 'videos', 'label' => '動画'];
                    }
                    $sections = array_merge($sections, [
                        ['id' => 'resale', 'label' => '買取相場'],
                        ['id' => 'price-distribution', 'label' => '価格分布'],
                        ['id' => 'price-trend', 'label' => '価格推移'],
                        ['id' => 'reviews', 'label' => 'レビュー' . (isset($reviewStats) && $reviewStats->count > 0 ? '★' . $reviewStats->avg_rating : '')],
                        ['id' => 'faq', 'label' => 'FAQ'],
                    ]);
                @endphp
                @foreach($sections as $sec)
                    <a href="#{{ $sec['id'] }}" data-nav-target="{{ $sec['id'] }}" class="section-nav-link whitespace-nowrap px-3 py-1.5 rounded-lg text-[11px] font-bold text-gray-500 hover:text-blue-600 hover:bg-blue-50 transition-all shrink-0">
                        {{ $sec['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="bg-gray-50 min-h-screen -mt-8 pb-16">
        <div class="max-w-7xl mx-auto px-4">
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 pt-8">
                
                {{-- メインコンテンツ --}}
                <div class="lg:col-span-8 space-y-8">

                    {{-- ★追加: 車種紹介テキスト（SEOの要） --}}
                    <div id="overview" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <h2 class="text-xl font-black text-gray-900 mb-4 flex items-center gap-2">
                            <span class="bg-indigo-100 text-indigo-600 p-2 rounded-lg"><i data-lucide="info" class="w-5 h-5"></i></span>
                            {{ $model->name }}とは
                        </h2>
                        <div class="text-sm text-gray-600 leading-relaxed space-y-3">
                            <p>
                                {{ $model->name }}は{{ $model->manufacturer->name }}が
                                @if($model->displacement){{ $model->displacement }}ccクラスで@endif
                                展開する
                                @if($model->categoryData){{ $model->categoryData->name }}タイプの@endif
                                バイクです。
                                @if($model->weight)車両重量は{{ $model->weight }}kgで、@endif
                                @if($model->seat_height)シート高{{ $model->seat_height }}mmと@endif
                                @if($model->displacement && $model->displacement <= 125)
                                    原付二種クラスならではの扱いやすさが魅力です。
                                @elseif($model->displacement && $model->displacement <= 250)
                                    車検不要の250ccクラスとして維持費の安さが魅力です。
                                @elseif($model->displacement && $model->displacement <= 400)
                                    普通二輪免許で乗れる400ccクラスのモデルです。
                                @elseif($model->displacement && $model->displacement > 400)
                                    大型バイクならではのパワフルな走りが魅力です。
                                @endif
                            </p>

                            @if(!empty($stats) && isset($stats['avg']) && $stats['count'] > 0)
                            <p>
                                中古車市場では現在{{ $stats['count'] }}台が流通しており、
                                平均価格は約{{ $stats['avg'] }}万円（{{ $stats['min'] }}万円〜{{ $stats['max'] }}万円）となっています。
                                @if(!empty($resale) && isset($resale['resale_min']) && $resale['data_count'] > 0)
                                    想定買取価格は{{ $resale['resale_min'] }}〜{{ $resale['resale_max'] }}万円です。
                                @endif
                            </p>
                            @endif

                            @if(isset($reviewStats) && $reviewStats->count > 0)
                            <p>
                                MotoHubに寄せられたオーナーレビューの平均評価は★{{ $reviewStats->avg_rating }}（{{ $reviewStats->count }}件）です。
                            </p>
                            @endif
                        </div>
                    </div>

                    {{-- カタログスペック情報 --}}
                    <div id="specs" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <div class="flex items-center gap-2 mb-6">
                            <div class="p-2 bg-gray-800 rounded-lg text-white">
                                <i data-lucide="book-open" class="w-5 h-5"></i>
                            </div>
                            <h3 class="text-lg font-black text-gray-900">カタログスペック</h3>
                            <span class="hidden sm:inline-block text-[10px] font-bold text-gray-400 ml-2 border border-gray-200 px-2 py-0.5 rounded bg-gray-50">{{ $model->name }}</span>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-1">
                            @php
                            $specs = [
                                '型式' => $model->model_code,
                                '全長 / 全幅 / 全高' => ($model->length && $model->width && $model->height) ? "{$model->length}mm / {$model->width}mm / {$model->height}mm" : null,
                                'シート高' => $model->seat_height ? "{$model->seat_height}mm" : null,
                                '車両重量' => $model->weight ? "{$model->weight}kg" : null,
                                'エンジン種類' => $model->engine_type,
                                '総排気量' => $model->displacement ? "{$model->displacement}cc" : null,
                                '燃費' => $model->fuel_consumption ? "{$model->fuel_consumption}km/L" : null,
                                'タンク容量' => $model->tank_capacity ? "{$model->tank_capacity}L" : null,
                                '燃料供給方式' => $model->fuel_supply,
                                '最高出力' => $model->max_power,
                                '最大トルク' => $model->max_torque,
                                'フロントタイヤ' => $model->tire_size_front,
                                'リアタイヤ' => $model->tire_size_rear,
                                '前ブレーキ' => $model->brake_type_front,
                                '後ブレーキ' => $model->brake_type_rear,
                            ];
                            @endphp

                            @foreach(array_filter($specs) as $label => $value)
                                <div class="flex justify-between items-center py-3 border-b border-gray-50 last:border-0 sm:nth-last-child(-n+2):border-0">
                                    <span class="text-xs font-bold text-gray-500 whitespace-nowrap">{{ $label }}</span>
                                    <span class="text-sm font-black text-gray-800 text-right max-w-[60%] leading-tight">{{ $value }}</span>
                                </div>
                            @endforeach
                            
                            @if(empty(array_filter($specs)))
                                <div class="col-span-1 sm:col-span-2 text-center py-4 text-xs font-bold text-gray-400">
                                    スペック情報がまだ収集されていません。
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- 関連ニュース --}}
                    @if(!empty($news))
                    <div id="news" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <div class="flex items-center gap-2 mb-6">
                            <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600">
                                <i data-lucide="newspaper" class="w-5 h-5"></i>
                            </div>
                            <h3 class="text-lg font-black text-gray-900">{{ $model->name }} のニュース</h3>
                        </div>
                        <div class="space-y-3">
                            @foreach($news as $article)
                            <a href="{{ $article['url'] }}" target="_blank" rel="noopener noreferrer" class="flex items-start gap-3 p-2 -mx-2 rounded-xl hover:bg-gray-50 transition-colors">
                                <div class="w-20 h-[60px] rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                                    @if(!empty($article['image']))
                                        <img src="{{ $article['image'] }}" alt="" class="w-full h-full object-cover" loading="lazy" onerror="this.parentNode.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-gray-300\'><svg xmlns=\'http://www.w3.org/2000/svg\' class=\'w-6 h-6\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5\'/></svg></div>'">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                                            <i data-lucide="bike" class="w-6 h-6"></i>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-bold text-gray-800 leading-snug mb-1 line-clamp-2">{{ $article['title'] }}</div>
                                    <div class="flex items-center gap-2 text-[11px] text-gray-400">
                                        @if($article['source'])<span class="font-bold">{{ $article['source'] }}</span>@endif
                                        <span>{{ $article['date'] }}</span>
                                    </div>
                                </div>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- YouTube動画 --}}
                    @if(!empty($videos))
                    <div id="videos" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <div class="flex items-center gap-2 mb-6">
                            <div class="p-2 bg-red-50 rounded-lg text-red-600">
                                <i data-lucide="play-circle" class="w-5 h-5"></i>
                            </div>
                            <h3 class="text-lg font-black text-gray-900">{{ $model->name }} の動画</h3>
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

                    {{-- 1. 買取相場・リセール情報 --}}
                    <div id="resale" class="bg-white rounded-3xl shadow-lg p-6 sm:p-8 border border-gray-100">
                        <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                            <span class="bg-yellow-100 text-yellow-600 p-2 rounded-lg"><i data-lucide="coins" class="w-5 h-5"></i></span>
                            {{ $model->name }} の買取相場・リセールバリュー
                        </h2>

                        @if(!empty($resale) && isset($resale['resale_min']) && $resale['data_count'] > 0)
                            <div class="bg-gradient-to-r from-yellow-50 to-orange-50 rounded-2xl p-6 mb-8 border border-yellow-100">
                                <p class="text-xs font-bold text-gray-500 mb-2 text-center">このバイクの想定買取価格</p>
                                <div class="text-center mb-4">
                                    <span class="text-4xl sm:text-5xl font-black text-yellow-600 tracking-tighter">
                                        {{ $resale['resale_min'] }}<span class="text-lg text-gray-600 mx-1">~</span>{{ $resale['resale_max'] }}
                                    </span>
                                    <span class="text-sm font-bold text-gray-500">万円</span>
                                </div>
                                <p class="text-[10px] text-gray-400 text-center">
                                    ※市場流通価格（平均{{ $resale['market_avg'] }}万円）から独自アルゴリズムで算出。<br>実際の買取額は車両状態や時期により変動します。
                                </p>
                            </div>

                            <div class="space-y-4">
                                <p class="text-xs font-black text-gray-800 text-center mb-2">＼ 複数の業者で比較して高く売ろう ／</p>
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="flex flex-col">
                                        <div class="text-[10px] font-bold text-center text-blue-600 bg-blue-50 py-1 rounded-t-lg border-x border-t border-blue-100">
                                            カスタム車・改造車もOK！
                                        </div>
                                        <a href="https://px.a8.net/svt/ejp?a8mat=4AX6CG+5PEKHE+1BFI+61RIA" target="_blank" rel="nofollow" class="block w-full bg-gradient-to-br from-blue-500 to-blue-600 hover:from-blue-400 hover:to-blue-500 text-white font-black text-center py-4 rounded-b-xl shadow-md transition transform hover:-translate-y-1 flex items-center justify-center gap-2">
                                            <span>バイクワンで査定</span>
                                            <i data-lucide="external-link" class="w-4 h-4"></i>
                                        </a>
                                        <img border="0" width="1" height="1" src="https://www18.a8.net/0.gif?a8mat=4AX6CG+5PEKHE+1BFI+61RIA" alt="" class="hidden">
                                    </div>
                                    <div class="flex flex-col">
                                        <div class="text-[10px] font-bold text-center text-red-600 bg-red-50 py-1 rounded-t-lg border-x border-t border-red-100">
                                            旧車・ハーレー・大型車に強い！
                                        </div>
                                        <a href="https://px.a8.net/svt/ejp?a8mat=4AX6CG+5QLFOY+1T3W+62ENM" target="_blank" rel="nofollow" class="block w-full bg-gradient-to-br from-red-500 to-red-600 hover:from-red-400 hover:to-red-500 text-white font-black text-center py-4 rounded-b-xl shadow-md transition transform hover:-translate-y-1 flex items-center justify-center gap-2">
                                            <span>バイクBOONで査定</span>
                                            <i data-lucide="external-link" class="w-4 h-4"></i>
                                        </a>
                                        <img border="0" width="1" height="1" src="https://www18.a8.net/0.gif?a8mat=4AX6CG+5QLFOY+1T3W+62ENM" alt="" class="hidden">
                                    </div>
                                </div>
                                <p class="text-[10px] text-gray-400 text-center font-bold mt-2">提携: バイクワン / バイクBOON</p>
                            </div>
                        @else
                            <div class="text-center py-8 bg-gray-50 rounded-2xl border border-gray-100">
                                <i data-lucide="bar-chart-2" class="w-8 h-8 text-gray-300 mx-auto mb-2"></i>
                                <p class="text-sm text-gray-500 font-bold">データ不足のため、現在買取相場を算出できません。</p>
                            </div>
                        @endif
                    </div>

                    {{-- 2. 市場価格分析 --}}
                    <div id="price-distribution" class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100">
                        <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                            <span class="bg-blue-100 text-blue-600 p-2 rounded-lg"><i data-lucide="bar-chart-2" class="w-5 h-5"></i></span>
                            {{ $model->name }} 中古車価格の分布
                        </h2>
                        
                        @if(!empty($stats) && isset($stats['avg']) && $stats['count'] > 0)
                            <div class="grid grid-cols-3 gap-4 mb-8 text-center">
                                <div class="bg-gray-50 rounded-xl p-3">
                                    <div class="text-[10px] font-bold text-gray-400">平均価格</div>
                                    <div class="text-xl font-black text-gray-800">{{ $stats['avg'] }}<span class="text-xs">万円</span></div>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-3">
                                    <div class="text-[10px] font-bold text-gray-400">最安値</div>
                                    <div class="text-xl font-black text-blue-600">{{ $stats['min'] }}<span class="text-xs">万円</span></div>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-3">
                                    <div class="text-[10px] font-bold text-gray-400">最高値</div>
                                    <div class="text-xl font-black text-red-500">{{ $stats['max'] }}<span class="text-xs">万円</span></div>
                                </div>
                            </div>
                            <div class="relative h-64 w-full">
                                <canvas id="priceChart"></canvas>
                            </div>
                        @else
                            <div class="text-center py-8 bg-gray-50 rounded-2xl border border-gray-100">
                                <p class="text-sm text-gray-500 font-bold">データがありません。</p>
                            </div>
                        @endif
                    </div>

                    {{-- 価格推移・買い時分析 --}}
                    <div id="price-trend" class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-4">
                            <h2 class="text-xl font-black text-gray-900 flex items-center gap-2">
                                <span class="bg-orange-100 text-orange-600 p-2 rounded-lg"><i data-lucide="trending-up" class="w-5 h-5"></i></span>
                                {{ $model->name }} 価格推移・買い時予報
                            </h2>
                            @if(!empty($history['trend']))
                                <div class="px-4 py-2 rounded-full text-xs font-bold flex items-center gap-2
                                    {{ $history['trend']['status'] === 'down' ? 'bg-red-100 text-red-600' : ($history['trend']['status'] === 'up' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600') }}">
                                    @if($history['trend']['status'] === 'down') <i data-lucide="arrow-down-right" class="w-4 h-4"></i>
                                    @elseif($history['trend']['status'] === 'up') <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
                                    @else <i data-lucide="minus" class="w-4 h-4"></i>
                                    @endif
                                    {{ $history['trend']['message'] }}
                                </div>
                            @endif
                        </div>
                        <div class="relative h-64 w-full">
                            <canvas id="historyChart"></canvas>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-4 text-right">※MotoHub独自の過去データに基づく平均価格の推移です</p>
                    </div>

                    {{-- 3. ユーザーレビュー --}}
                    <div class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100" id="reviews">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
                            <h2 class="text-xl font-black text-gray-900 flex items-center gap-2">
                                <span class="bg-green-100 text-green-600 p-2 rounded-lg shrink-0"><i data-lucide="message-circle" class="w-5 h-5"></i></span>
                                <span>
                                    {{ $model->name }} オーナーレビュー
                                    <span class="text-sm text-gray-500 font-bold ml-1 inline-block">({{ $model->reviews->count() }}件)</span>
                                </span>
                            </h2>
                            <a href="#review-form" class="text-xs font-bold bg-black text-white px-4 py-3 sm:py-2 rounded-full hover:bg-gray-800 transition-colors inline-flex items-center justify-center w-full sm:w-auto">
                                <i data-lucide="pen-tool" class="w-3 h-3 mr-1"></i>投稿する
                            </a>
                        </div>

                        <div class="space-y-6 mb-12">
                            @forelse($model->reviews as $review)
                                <div class="border-b border-gray-100 pb-6 last:border-0">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                            <div class="flex text-yellow-400">
                                                @for($i=1; $i<=5; $i++)
                                                    <i data-lucide="star" class="w-4 h-4 {{ $i <= $review->rating ? 'fill-current' : 'text-gray-200' }}"></i>
                                                @endfor
                                            </div>
                                            <span class="text-sm font-bold text-gray-900">{{ $review->title }}</span>
                                        </div>
                                        <span class="text-[10px] text-gray-400">{{ $review->created_at->format('Y/m/d') }}</span>
                                    </div>
                                    <p class="text-sm text-gray-600 leading-relaxed mb-2 whitespace-pre-wrap">{{ $review->body }}</p>
                                    <p class="text-xs text-gray-400 font-bold">by {{ $review->nickname }}</p>
                                </div>
                            @empty
                                <div class="text-center py-8 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                                    <p class="text-sm text-gray-500 font-bold mb-2">まだレビューがありません。</p>
                                    <p class="text-xs text-gray-400">最初のレビューを投稿してみませんか？</p>
                                </div>
                            @endforelse
                        </div>

                        {{-- 投稿フォーム --}}
                        <div id="review-form" class="bg-gray-50 rounded-2xl p-6 border border-gray-100">
                            <h3 class="text-base font-black text-gray-900 mb-4">レビューを投稿する</h3>
                            
                            @if(session('success'))
                                <div class="mb-4 p-4 bg-green-100 text-green-700 text-sm font-bold rounded-xl border border-green-200">
                                    {{ session('success') }}
                                </div>
                            @endif

                            @if ($errors->any())
                                <div class="mb-4 p-4 bg-red-50 text-red-600 text-sm font-bold rounded-xl border border-red-100">
                                    <ul class="list-disc list-inside">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form id="review-form-element" action="{{ route('bikes.model_detail.review', $model->id) }}" method="POST">
                                @csrf
                                <input type="hidden" name="recaptcha_token" id="recaptcha-token">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 mb-1">ニックネーム</label>
                                        <input type="text" name="nickname" value="{{ old('nickname') }}" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="名無しライダー">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 mb-1">評価</label>
                                        <input type="hidden" name="rating" id="rating-value" value="{{ old('rating', 5) }}">
                                        <div class="flex items-center gap-1">
                                            @for($s = 1; $s <= 5; $s++)
                                            <button type="button" class="star-btn p-0.5 transition-transform hover:scale-110 focus:outline-none {{ $s <= old('rating', 5) ? 'text-yellow-400' : 'text-gray-200' }}" data-val="{{ $s }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 transition-colors {{ $s <= old('rating', 5) ? 'fill-current' : '' }}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                                </svg>
                                            </button>
                                            @endfor
                                            <span id="rating-label" class="ml-2 text-xs font-bold text-gray-400">{{ ['', '悪い', 'いまいち', '普通', '良い', '最高'][old('rating', 5)] }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-xs font-bold text-gray-500 mb-1">タイトル</label>
                                    <input type="text" name="title" value="{{ old('title') }}" required class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="例: 燃費が良くて乗りやすい！">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-xs font-bold text-gray-500 mb-1">レビュー内容</label>
                                    <textarea name="body" required rows="4" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="良い点、気になった点などを自由に書いてください。">{{ old('body') }}</textarea>
                                </div>
                                <button type="submit" class="w-full bg-black hover:bg-gray-800 text-white font-bold py-3 rounded-xl transition shadow-lg transform active:scale-95">
                                    投稿する
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- ★追加: FAQ（よくある質問） --}}
                    <div id="faq" class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100">
                        <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                            <span class="bg-purple-100 text-purple-600 p-2 rounded-lg"><i data-lucide="help-circle" class="w-5 h-5"></i></span>
                            {{ $model->name }} よくある質問
                        </h2>

                        <div class="space-y-4">
                            {{-- Q1: 中古相場 --}}
                            <details class="group border border-gray-100 rounded-xl overflow-hidden">
                                <summary class="flex items-center justify-between px-5 py-4 cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors font-bold text-sm text-gray-800">
                                    <span>{{ $model->name }}の中古車相場はいくらですか？</span>
                                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 group-open:rotate-180 transition-transform"></i>
                                </summary>
                                <div class="px-5 py-4 text-sm text-gray-600 leading-relaxed">
                                    @if(!empty($stats) && isset($stats['avg']) && $stats['count'] > 0)
                                        {{ $model->name }}の中古車は現在{{ $stats['count'] }}台が市場に流通しています。
                                        価格帯は{{ $stats['min'] }}万円〜{{ $stats['max'] }}万円で、平均価格は約{{ $stats['avg'] }}万円です。
                                        年式や走行距離、車両の状態によって価格は大きく変動します。
                                    @else
                                        現在、{{ $model->name }}の中古車価格データが十分にありません。在庫が入荷され次第、相場情報が更新されます。
                                    @endif
                                </div>
                            </details>

                            {{-- Q2: 買取価格 --}}
                            <details class="group border border-gray-100 rounded-xl overflow-hidden">
                                <summary class="flex items-center justify-between px-5 py-4 cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors font-bold text-sm text-gray-800">
                                    <span>{{ $model->name }}の買取価格（売却価格）はいくらですか？</span>
                                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 group-open:rotate-180 transition-transform"></i>
                                </summary>
                                <div class="px-5 py-4 text-sm text-gray-600 leading-relaxed">
                                    @if(!empty($resale) && isset($resale['resale_min']) && $resale['data_count'] > 0)
                                        MotoHubの独自算出では、{{ $model->name }}の想定買取価格は{{ $resale['resale_min'] }}〜{{ $resale['resale_max'] }}万円です。
                                        ただし実際の買取額は車両の状態、走行距離、カスタムの有無、買取業者によって大きく異なります。
                                        複数の業者に査定を依頼して比較することをおすすめします。
                                    @else
                                        現在、{{ $model->name }}の買取相場データが十分にありません。買取業者に直接査定を依頼することをおすすめします。
                                    @endif
                                </div>
                            </details>

                            {{-- Q3: 維持費 --}}
                            <details class="group border border-gray-100 rounded-xl overflow-hidden">
                                <summary class="flex items-center justify-between px-5 py-4 cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors font-bold text-sm text-gray-800">
                                    <span>{{ $model->name }}の維持費はどれくらいですか？</span>
                                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 group-open:rotate-180 transition-transform"></i>
                                </summary>
                                <div class="px-5 py-4 text-sm text-gray-600 leading-relaxed">
                                    @if($model->displacement)
                                        @if($model->displacement <= 125)
                                            {{ $model->name }}は{{ $model->displacement }}ccのため、ファミリーバイク特約が使え、車検も不要です。年間の維持費は保険・税金・消耗品を含めて約3〜5万円程度が目安です。
                                        @elseif($model->displacement <= 250)
                                            {{ $model->name }}は{{ $model->displacement }}ccのため車検が不要で、維持費を抑えやすいクラスです。年間の維持費は保険・税金・消耗品を含めて約5〜10万円程度が目安です。
                                        @elseif($model->displacement <= 400)
                                            {{ $model->name }}は{{ $model->displacement }}ccのため、2年ごとに車検が必要です。年間の維持費は車検代・保険・税金・消耗品を含めて約10〜15万円程度が目安です。
                                        @else
                                            {{ $model->name }}は{{ $model->displacement }}ccの大型バイクのため、2年ごとに車検が必要です。年間の維持費は車検代・保険・税金・消耗品を含めて約12〜20万円程度が目安です。
                                        @endif
                                        @if($model->fuel_consumption)
                                            燃費は{{ $model->fuel_consumption }}km/Lです。
                                        @endif
                                    @else
                                        {{ $model->name }}の維持費は排気量クラスによって異なります。詳しくはスペック情報をご確認ください。
                                    @endif
                                </div>
                            </details>

                            {{-- Q4: オーナー評価 --}}
                            <details class="group border border-gray-100 rounded-xl overflow-hidden">
                                <summary class="flex items-center justify-between px-5 py-4 cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors font-bold text-sm text-gray-800">
                                    <span>{{ $model->name }}のオーナー評価・口コミは？</span>
                                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 group-open:rotate-180 transition-transform"></i>
                                </summary>
                                <div class="px-5 py-4 text-sm text-gray-600 leading-relaxed">
                                    @if(isset($reviewStats) && $reviewStats->count > 0)
                                        {{ $model->name }}のオーナーレビューは{{ $reviewStats->count }}件寄せられており、平均評価は★{{ $reviewStats->avg_rating }}です。
                                        <a href="#reviews" class="text-blue-600 hover:underline font-bold">レビュー一覧はこちら</a>からご覧いただけます。
                                    @else
                                        まだ{{ $model->name }}のオーナーレビューは投稿されていません。
                                        <a href="#review-form" class="text-blue-600 hover:underline font-bold">最初のレビューを投稿</a>してみませんか？
                                    @endif
                                </div>
                            </details>
                        </div>

                        {{-- FAQ構造化データ --}}
                        @php
                            $faqItems = [
                                [
                                    '@type' => 'Question',
                                    'name' => "{$model->name}の中古車相場はいくらですか？",
                                    'acceptedAnswer' => [
                                        '@type' => 'Answer',
                                        'text' => !empty($stats) && isset($stats['avg']) && $stats['count'] > 0
                                            ? "{$model->name}の中古車は現在{$stats['count']}台が流通。価格帯は{$stats['min']}万円〜{$stats['max']}万円で、平均価格は約{$stats['avg']}万円です。"
                                            : '現在データ収集中です。',
                                    ],
                                ],
                                [
                                    '@type' => 'Question',
                                    'name' => "{$model->name}の買取価格はいくらですか？",
                                    'acceptedAnswer' => [
                                        '@type' => 'Answer',
                                        'text' => !empty($resale) && isset($resale['resale_min']) && $resale['data_count'] > 0
                                            ? "想定買取価格は{$resale['resale_min']}〜{$resale['resale_max']}万円です。"
                                            : '現在データ収集中です。',
                                    ],
                                ],
                                [
                                    '@type' => 'Question',
                                    'name' => "{$model->name}の維持費はどれくらいですか？",
                                    'acceptedAnswer' => [
                                        '@type' => 'Answer',
                                        'text' => $model->displacement
                                            ? ($model->displacement <= 125
                                                ? "{$model->name}は{$model->displacement}ccのため車検不要。年間維持費は約3〜5万円が目安です。"
                                                : ($model->displacement <= 250
                                                    ? "{$model->name}は{$model->displacement}ccのため車検不要。年間維持費は約5〜10万円が目安です。"
                                                    : ($model->displacement <= 400
                                                        ? "{$model->name}は{$model->displacement}ccのため2年ごとに車検が必要。年間維持費は約10〜15万円が目安です。"
                                                        : "{$model->name}は{$model->displacement}ccの大型バイク。年間維持費は約12〜20万円が目安です。")))
                                            : "{$model->name}の維持費は排気量クラスによって異なります。",
                                    ],
                                ],
                                [
                                    '@type' => 'Question',
                                    'name' => "{$model->name}のオーナー評価・口コミは？",
                                    'acceptedAnswer' => [
                                        '@type' => 'Answer',
                                        'text' => isset($reviewStats) && $reviewStats->count > 0
                                            ? "{$model->name}のオーナーレビューは{$reviewStats->count}件、平均評価は★{$reviewStats->avg_rating}です。"
                                            : "まだ{$model->name}のオーナーレビューは投稿されていません。",
                                    ],
                                ],
                            ];

                            $faqSchema = [
                                '@context' => 'https://schema.org',
                                '@type' => 'FAQPage',
                                'mainEntity' => $faqItems,
                            ];
                        @endphp
                        <script type="application/ld+json">
                            {!! json_encode($faqSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
                        </script>
                    </div>

                    {{-- この車種のオーナー --}}
                    @if(isset($owners) && $owners->count() > 0)
                    <div class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100">
                        <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                            <span class="bg-pink-100 text-pink-600 p-2 rounded-lg">
                                <i data-lucide="users" class="w-5 h-5"></i>
                            </span>
                            {{ $model->name }} のオーナー
                            <span class="text-sm text-gray-400 font-bold">({{ $owners->count() }}人)</span>
                        </h2>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            @foreach($owners as $owner)
                            <a href="{{ route('garage.public.show', $owner->id) }}"
                               class="group block bg-gray-50 rounded-xl p-4 border border-gray-100 hover:border-pink-300 hover:shadow-md transition-all">
                                <div class="aspect-[4/3] rounded-lg bg-gray-200 overflow-hidden mb-3">
                                    @if($owner->display_image)
                                        <img src="{{ $owner->display_image }}" alt="{{ $owner->display_name }}"
                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                                             loading="lazy" decoding="async">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-400">
                                            <i data-lucide="bike" class="w-8 h-8"></i>
                                        </div>
                                    @endif
                                </div>
                                <p class="text-xs font-bold text-gray-500">{{ $owner->user->name ?? '名無しライダー' }}</p>
                                <p class="text-sm font-black text-gray-800">{{ $owner->display_name }}</p>
                                @if($owner->model_year)
                                    <span class="text-[10px] text-gray-400">{{ $owner->model_year }}年式</span>
                                @endif
                            </a>
                            @endforeach
                        </div>

                        <div class="mt-6 text-center">
                            <a href="{{ route('garage.public.index') }}" class="text-xs font-bold text-pink-600 hover:underline">
                                みんなの愛車をもっと見る →
                            </a>
                        </div>
                    </div>
                    @endif

                    {{-- オーナーがいなくても登録を促すCTA --}}
                    @if(!isset($owners) || $owners->count() === 0)
                    <div class="bg-gradient-to-r from-pink-50 to-rose-50 rounded-3xl p-6 sm:p-8 border border-pink-100 text-center">
                        <i data-lucide="heart" class="w-8 h-8 text-pink-400 mx-auto mb-2"></i>
                        <h3 class="text-lg font-black text-gray-900 mb-2">{{ $model->name }} に乗っていますか？</h3>
                        <p class="text-xs text-gray-500 mb-4">愛車を登録して、燃費記録・整備ログを管理しましょう</p>
                        <a href="{{ route('mybikes.index') }}" class="inline-block bg-pink-600 text-white font-bold text-sm px-6 py-3 rounded-xl hover:bg-pink-700 transition-colors">
                            愛車を登録する
                        </a>
                    </div>
                    @endif

                    {{-- 関連車種: 同メーカー --}}
                    @if(isset($relatedModels) && $relatedModels->count() > 0)
                    <div class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100">
                        <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                            <span class="bg-teal-100 text-teal-600 p-2 rounded-lg"><i data-lucide="git-branch" class="w-5 h-5"></i></span>
                            {{ $model->manufacturer->name }}の他の車種
                        </h2>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            @foreach($relatedModels as $related)
                            <a href="{{ $related->seo_url }}" class="group block bg-gray-50 rounded-xl p-4 border border-gray-100 hover:border-blue-300 hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
                                <div class="aspect-[4/3] rounded-lg bg-gray-100 overflow-hidden mb-3">
                                    @if($related->image_url)
                                        <img src="{{ $related->image_url }}" alt="{{ $related->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" decoding="async">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                                            <i data-lucide="bike" class="w-6 h-6"></i>
                                        </div>
                                    @endif
                                </div>
                                <h3 class="text-xs font-black text-gray-800 group-hover:text-blue-600 transition-colors line-clamp-2 mb-1">{{ $related->name }}</h3>
                                @if($related->listings_count > 0)
                                <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">{{ $related->listings_count }}台</span>
                                @endif
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- 関連車種: 同排気量帯 --}}
                    @if($similarDisplacementModels->isNotEmpty())
                    <div class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100">
                        <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                            <span class="bg-blue-100 text-blue-600 p-2 rounded-lg"><i data-lucide="gauge" class="w-5 h-5"></i></span>
                            同排気量帯の人気車種
                        </h2>
                        <p class="text-xs text-gray-400 font-bold mb-4 -mt-3">{{ $model->displacement }}cc 前後（{{ $model->displacement - 50 }}〜{{ $model->displacement + 50 }}cc）</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            @foreach($similarDisplacementModels as $related)
                            <a href="{{ $related->seo_url }}" class="group block bg-gray-50 rounded-xl p-4 border border-gray-100 hover:border-blue-300 hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
                                <div class="aspect-[4/3] rounded-lg bg-gray-100 overflow-hidden mb-3">
                                    @if($related->image_url)
                                        <img src="{{ $related->image_url }}" alt="{{ $related->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" decoding="async">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                                            <i data-lucide="bike" class="w-6 h-6"></i>
                                        </div>
                                    @endif
                                </div>
                                <p class="text-[9px] font-bold text-gray-400 mb-0.5">{{ $related->manufacturer->name }}</p>
                                <h3 class="text-xs font-black text-gray-800 group-hover:text-blue-600 transition-colors line-clamp-2 mb-1">{{ $related->name }}</h3>
                                @if($related->listings_count > 0)
                                <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">{{ $related->listings_count }}台</span>
                                @endif
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- 関連車種: 同カテゴリ --}}
                    @if($sameCategoryModels->isNotEmpty())
                    <div class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100">
                        <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                            <span class="bg-purple-100 text-purple-600 p-2 rounded-lg"><i data-lucide="layers" class="w-5 h-5"></i></span>
                            同カテゴリの車種
                        </h2>
                        <p class="text-xs text-gray-400 font-bold mb-4 -mt-3">{{ $model->categoryData?->name ?? 'その他' }}カテゴリの他モデル</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            @foreach($sameCategoryModels as $related)
                            <a href="{{ $related->seo_url }}" class="group block bg-gray-50 rounded-xl p-4 border border-gray-100 hover:border-blue-300 hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
                                <div class="aspect-[4/3] rounded-lg bg-gray-100 overflow-hidden mb-3">
                                    @if($related->image_url)
                                        <img src="{{ $related->image_url }}" alt="{{ $related->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" decoding="async">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                                            <i data-lucide="bike" class="w-6 h-6"></i>
                                        </div>
                                    @endif
                                </div>
                                <p class="text-[9px] font-bold text-gray-400 mb-0.5">{{ $related->manufacturer->name }}</p>
                                <h3 class="text-xs font-black text-gray-800 group-hover:text-blue-600 transition-colors line-clamp-2 mb-1">{{ $related->name }}</h3>
                                @if($related->listings_count > 0)
                                <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">{{ $related->listings_count }}台</span>
                                @endif
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- エリア別リンク --}}
                    @if(isset($prefectureStocks) && $prefectureStocks->isNotEmpty())
                    <div class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100">
                        <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                            <span class="bg-orange-100 text-orange-600 p-2 rounded-lg"><i data-lucide="map-pin" class="w-5 h-5"></i></span>
                            {{ $model->name }}をエリアから探す
                        </h2>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                            @foreach($prefectureStocks as $ps)
                            <a href="{{ route('bikes.search', ['bike_model_id' => $model->id, 'prefecture' => $ps->prefecture]) }}"
                               class="group flex items-center justify-between bg-gray-50 hover:bg-blue-50 border border-gray-100 hover:border-blue-200 rounded-xl px-4 py-3 transition-all duration-200">
                                <span class="text-xs font-black text-gray-800 group-hover:text-blue-700 transition-colors">{{ $ps->prefecture }}</span>
                                <span class="text-[10px] font-bold text-gray-400 group-hover:text-blue-500 bg-white px-2 py-0.5 rounded-full border border-gray-100">{{ $ps->stock_count }}台</span>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- 閲覧履歴ウィジェット --}}
                    @include('bikes.partials.history_widget', ['widgetId' => 'model-history-widget'])

                    @if(isset($similarModels) && $similarModels->count() > 0)
                    <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100 mt-8">
                        <h2 class="text-lg font-black text-gray-900 mb-4">{{ $model->name }}を見た人はこの車種も見ています</h2>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            @foreach($similarModels as $similar)
                            <a href="{{ $similar->seo_url ?? route('bikes.modelDetail', $similar->id) }}" class="bg-gray-50 rounded-xl p-3 border border-gray-100 hover:border-blue-300 hover:shadow-md transition-all">
                                <p class="text-xs text-gray-400 font-bold">{{ $similar->manufacturer->name ?? '' }}</p>
                                <p class="text-sm font-black text-gray-800 line-clamp-1">{{ $similar->name }}</p>
                                <p class="text-xs text-blue-600 font-bold mt-1">{{ $similar->listings_count }}台販売中</p>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- 関連パーツ --}}
                    @if(!empty($relatedParts))
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="wrench" class="w-5 h-5 text-blue-500"></i>
                            {{ $model->name }} のパーツを探す
                        </h2>
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
                            <a href="{{ route('parts.index', ['bike' => $model->name]) }}"
                                class="inline-flex items-center gap-1 text-sm font-bold text-blue-600 hover:text-blue-800 transition-colors">
                                {{ $model->name }} のパーツをもっと見る
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </a>
                        </div>
                    </div>
                    @endif

                    {{-- 駐車場エリアリンク --}}
                    <div class="bg-green-50 rounded-2xl p-5 border border-green-100 flex items-center justify-between hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">🅿️</span>
                            <span class="text-sm font-black text-gray-800">バイク駐車場をエリアから探す</span>
                        </div>
                        <a href="{{ route('parking.area.index') }}" class="text-xs font-bold text-green-600 hover:text-green-800 flex items-center gap-1">
                            エリア一覧 <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    </div>

                    {{-- 回遊リンク --}}
                    <x-cross-links :crossLinks="$crossLinks" />

                </div>

                {{-- サイドバー --}}
                <div class="lg:col-span-4 space-y-6">
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sticky top-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-black text-gray-900">販売中の車両</h3>
                            <a href="{{ route('bikes.search', ['bike_model_id' => $model->id]) }}" class="text-xs font-bold text-blue-600 hover:underline">すべて見る</a>
                        </div>

                        <div class="space-y-4">
                            @forelse($listings as $bike)
                                <a href="{{ route('bikes.show', $bike['id']) }}" class="flex gap-3 group">
                                    <div class="w-20 h-20 shrink-0 rounded-lg overflow-hidden bg-gray-100 relative">
                                        @if(!empty($bike['images'][0]))
                                            <img src="{{ $bike['images'][0] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" decoding="async">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-gray-300"><i data-lucide="bike"></i></div>
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0 py-1">
                                        <h4 class="text-xs font-black text-gray-800 line-clamp-2 group-hover:text-blue-600 transition-colors mb-1">
                                            {{ $bike['name'] }}
                                        </h4>
                                        <div class="text-red-500 font-black text-sm">
                                            {{ $bike['total_price'] }}<span class="text-[10px]">万円</span>
                                        </div>
                                        <div class="text-[10px] text-gray-400 mt-0.5 truncate">
                                            {{ $bike['prefecture'] }}
                                        </div>
                                    </div>
                                </a>
                            @empty
                                <p class="text-xs text-gray-400 font-bold text-center py-4">現在、在庫はありません。</p>
                            @endforelse
                        </div>
                        
                        <div class="mt-6 pt-6 border-t border-gray-100 space-y-3">
                            <a href="{{ route('bikes.search', ['bike_model_id' => $model->id]) }}" class="block w-full bg-gray-900 hover:bg-gray-800 text-white font-bold text-xs text-center py-3 rounded-xl transition-colors">
                                {{ $model->name }} の在庫を検索する
                            </a>
                            {{-- サイドバー通知ボタン --}}
                            <div id="push-area-sidebar" data-model-id="{{ $model->id }}"></div>
                        </div>
                        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mt-6">
                            <div class="text-center">
                                <i data-lucide="code" class="w-6 h-6 text-blue-500 mx-auto mb-2"></i>
                                <h3 class="text-sm font-black text-gray-900 mb-1">ブログに相場を貼りませんか？</h3>
                                <p class="text-[10px] text-gray-400 mb-3">{{ $model->name }}の相場ウィジェットを無料で埋め込めます</p>
                                <a href="{{ route('pages.widget', ['model_id' => $model->id]) }}" class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs text-center py-2.5 rounded-xl transition-colors">
                                    埋め込みコードを取得
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-layout>