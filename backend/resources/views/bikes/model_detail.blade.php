<x-layout>
    @php
        $seoName = $model->name;
        if ($model->display_name && $model->display_name !== $model->name) {
            $seoName .= '(' . $model->display_name . ')';
        }
    @endphp
    <x-slot:title>{{ $model->manufacturer?->name }} {{ $seoName }}の中古バイク{{ $activeCount > 0 ? '【' . $activeCount . '台】' : '' }}{{ !empty($stats) && isset($stats['avg']) && $stats['count'] > 0 ? '相場' . $stats['min'] . '〜' . $stats['max'] . '万円' : '相場・価格' }} | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $model->manufacturer?->name }} {{ $seoName }}の中古バイク{{ $activeCount > 0 ? $activeCount . '台掲載中' : '情報' }}。{{ !empty($stats) && isset($stats['avg']) && $stats['count'] > 0 ? '価格' . $stats['min'] . '〜' . $stats['max'] . '万円（平均' . $stats['avg'] . '万円）。' : '' }}スペック・維持費・相場推移・口コミをMotoHubで比較検討。</x-slot:metaDescription>
    <x-slot:canonical>{{ url($model->seo_url) }}</x-slot:canonical>
    @if(!empty($reviewOgpMode) && $model->manufacturer?->slug && $model->slug && !empty($scrollToReviewId))
    <x-slot:ogImage>{{ url("/bikes/{$model->manufacturer->slug}/{$model->slug}/review-ogp/{$scrollToReviewId}.png") }}</x-slot:ogImage>
    @elseif(!empty($reviewOgpMode) && $model->manufacturer?->slug && $model->slug)
    <x-slot:ogImage>{{ url("/bikes/{$model->manufacturer->slug}/{$model->slug}/review-ogp.png") }}</x-slot:ogImage>
    @elseif($model->image_url)
    <x-slot:ogImage>{{ $model->image_url }}</x-slot:ogImage>
    @endif
    <x-slot:publishedTime>{{ $model->created_at->toIso8601String() }}</x-slot:publishedTime>
    <x-slot:modifiedTime>{{ $model->updated_at->toIso8601String() }}</x-slot:modifiedTime>

    <x-slot:styles>
        <x-jsonld.model-product :model="$model" :stats="$stats" :reviewStats="$reviewStats ?? null" />
        <x-jsonld.breadcrumb-model :model="$model" />
    </x-slot:styles>
    
    <x-slot:scripts>
        <script>
            window.bikeModelStats = @json($stats ?? []);
            window.bikeModelHistory = @json($history ?? []);
        </script>
        <script>window.__bikeModelId = {{ $model->id }};</script>
        {{-- 再訪フック用: この車種を localStorage に記録（slug/メーカー/在庫数/日時のみ・PII非該当）。
             slug が無い車種はURLを作れないので記録対象外。 --}}
        @if($model->slug && $model->manufacturer && $model->manufacturer->slug)
        <script>window.__viewedModel = {
            slug: @json($model->slug),
            mfrSlug: @json($model->manufacturer->slug),
            maker: @json($model->manufacturer->name ?? ''),
            name: @json($model->name),
            stockCount: {{ (int) ($activeCount ?? 0) }}
        };</script>
        @endif
        @if(!empty($reviewOgpMode))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var targetId = '{{ !empty($scrollToReviewId) ? "review-" . $scrollToReviewId : "reviews" }}';
                var target = document.getElementById(targetId);
                if (target) {
                    var communityBtn = document.querySelector('[data-tab="community"]');
                    if (communityBtn) communityBtn.click();
                    setTimeout(function() {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 300);
                }
            });
        </script>
        @endif
        <script src="{{ asset('js/promo/engagement-banner.js') }}?v={{ asset_buster(public_path('js/promo/engagement-banner.js')) }}" defer></script>
        <script src="{{ asset('js/bikes/model_detail.js') }}?v={{ asset_buster(public_path('js/bikes/model_detail.js')) }}" defer></script>
        <script src="{{ asset('js/bikes/review.js') }}?v={{ asset_buster(public_path('js/bikes/review.js')) }}" defer></script>
        <script src="{{ asset('js/qa-push.js') }}?v={{ asset_buster(public_path('js/qa-push.js')) }}" defer></script>
        {{-- 再訪フック: この車種を localStorage に記録（window.__viewedModel を読む） --}}
        <script src="{{ asset('js/viewed-models.js') }}?v={{ asset_buster(public_path('js/viewed-models.js')) }}" defer></script>
        {{-- Chart.js遅延読み込み: チャート要素が表示された時にCDNからロード --}}
        <script>
            (function() {
                var chartLoaded = false;
                var chartCallbacks = [];
                function loadChartJs(cb) {
                    if (chartLoaded) { if (cb) cb(); return; }
                    if (chartCallbacks.length > 0) { if (cb) chartCallbacks.push(cb); return; }
                    if (cb) chartCallbacks.push(cb);
                    var s = document.createElement('script');
                    s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                    s.onload = function() {
                        chartLoaded = true;
                        chartCallbacks.forEach(function(fn) { fn(); });
                        chartCallbacks = [];
                    };
                    document.head.appendChild(s);
                }
                window.__loadChartJs = loadChartJs;
                function setupObserver() {
                    if ('IntersectionObserver' in window) {
                        var targets = document.querySelectorAll('#yearDistributionChart, #priceChart, #historyChart, #reviewRadarChart');
                        if (targets.length === 0) return;
                        var obs = new IntersectionObserver(function(entries) {
                            entries.forEach(function(e) {
                                if (e.isIntersecting) { loadChartJs(); obs.disconnect(); }
                            });
                        }, { rootMargin: '200px' });
                        targets.forEach(function(t) { obs.observe(t); });
                    } else {
                        loadChartJs();
                    }
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', setupObserver);
                } else {
                    setupObserver();
                }
            })();
        </script>
        {{-- reCAPTCHA遅延読み込み: レビューフォームが表示された時にロード --}}
        <script>
            (function() {
                var recaptchaLoaded = false;
                function loadRecaptcha() {
                    if (recaptchaLoaded) return;
                    recaptchaLoaded = true;
                    var s = document.createElement('script');
                    s.src = 'https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}';
                    document.head.appendChild(s);
                }
                var form = document.getElementById('review-form-element');
                if (!form) return;
                if ('IntersectionObserver' in window) {
                    new IntersectionObserver(function(entries, obs) {
                        if (entries[0].isIntersecting) { loadRecaptcha(); obs.disconnect(); }
                    }, { rootMargin: '300px' }).observe(form);
                } else {
                    loadRecaptcha();
                }
            })();
        </script>
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
                        function doRecaptcha() {
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
                        }
                        if (typeof grecaptcha !== 'undefined') {
                            doRecaptcha();
                        } else {
                            var s = document.createElement('script');
                            s.src = 'https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}';
                            s.onload = doRecaptcha;
                            document.head.appendChild(s);
                        }
                    });
                }
            });
        </script>
        {{-- タブ切り替え制御 --}}
        <script>
            (function() {
                function initTabs() {
                    var tabNav = document.getElementById('tab-nav');
                    var btns = tabNav ? tabNav.querySelectorAll('.tab-btn') : [];
                    var panels = document.querySelectorAll('.tab-panel');
                    if (!btns.length || !panels.length) return;

                    function switchTab(tabId) {
                        btns.forEach(function(b) {
                            var isActive = b.dataset.tab === tabId;
                            b.classList.toggle('border-blue-600', isActive);
                            b.classList.toggle('text-blue-600', isActive);
                            b.classList.toggle('font-bold', isActive);
                            b.classList.toggle('border-transparent', !isActive);
                            b.classList.toggle('text-gray-500', !isActive);
                        });
                        panels.forEach(function(p) {
                            p.style.display = p.id === 'tab-panel-' + tabId ? '' : 'none';
                        });
                        // Chart.js再描画
                        if (tabId === 'overview' && typeof Chart !== 'undefined') {
                            var yc = Chart.getChart('yearDistributionChart');
                            if (yc) yc.resize();
                        }
                        if (tabId === 'market' && typeof Chart !== 'undefined') {
                            var pc = Chart.getChart('priceChart');
                            var hc = Chart.getChart('historyChart');
                            if (pc) pc.resize();
                            if (hc) hc.resize();
                        }
                        if (tabId === 'community' && typeof Chart !== 'undefined') {
                            var rc = Chart.getChart('reviewRadarChart');
                            if (rc) rc.resize();
                        }
                        // lucide再描画（非表示パネル内のアイコン対策）
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                    }

                    btns.forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            var tabId = this.dataset.tab;
                            switchTab(tabId);
                            history.replaceState(null, '', '#' + tabId);
                            // タブナビ位置までスクロール
                            var navRect = tabNav.getBoundingClientRect();
                            if (navRect.top < 0) {
                                tabNav.scrollIntoView({ behavior: 'smooth' });
                            }
                        });
                    });

                    // URLハッシュ連動
                    function handleHash() {
                        var hash = location.hash.replace('#', '');
                        if (!hash) { switchTab('overview'); return; }
                        var validTabs = [];
                        btns.forEach(function(b) { validTabs.push(b.dataset.tab); });
                        if (validTabs.indexOf(hash) !== -1) {
                            switchTab(hash);
                        } else {
                            // タブ内アンカー（#reviews / #threads / #review-form 等）：親タブを表示してから
                            // その要素まで1タップでスクロールする。switchTab で display:none→block にした直後は
                            // まだ再描画・レイアウトが確定しておらず、同期で scrollIntoView を呼ぶと不発になる。
                            // → 1フレーム遅らせて（既存46-54行のタブclick＋setTimeout scrollと同型）表示確定後に走らせる。
                            var target = document.getElementById(hash);
                            if (target) {
                                var parentPanel = target.closest('.tab-panel');
                                if (parentPanel) {
                                    var panelTab = parentPanel.id.replace('tab-panel-', '');
                                    switchTab(panelTab);
                                    setTimeout(function () {
                                        // sticky タブナビ(top-0)の高さ分オフセット（scroll-mt はビルド未生成のためJSで担保）。
                                        var offset = (tabNav ? tabNav.offsetHeight : 0) + 12;
                                        var y = target.getBoundingClientRect().top + window.pageYOffset - offset;
                                        window.scrollTo({ top: y, behavior: 'smooth' });
                                    }, 60);
                                }
                            }
                            // どのタブにも属さないハッシュの場合は現在のタブを維持（切り替えない）
                        }
                    }

                    window.addEventListener('hashchange', handleHash);
                    handleHash();
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initTabs);
                } else {
                    initTabs();
                }
            })();
        </script>
        {{-- パーツカテゴリサブタブ切り替え --}}
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var partsBtns = document.querySelectorAll('.parts-tab-btn');
                var partsPanels = document.querySelectorAll('.parts-panel');
                if (!partsBtns.length) return;
                partsBtns.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var target = btn.dataset.partsTab;
                        partsBtns.forEach(function(b) {
                            b.classList.toggle('bg-blue-600', b.dataset.partsTab === target);
                            b.classList.toggle('text-white', b.dataset.partsTab === target);
                            b.classList.toggle('bg-gray-100', b.dataset.partsTab !== target);
                            b.classList.toggle('text-gray-600', b.dataset.partsTab !== target);
                        });
                        partsPanels.forEach(function(p) {
                            p.classList.toggle('hidden', p.dataset.partsPanel !== target);
                        });
                    });
                });
            });
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
                <li><a href="{{ $model->manufacturer->slug ? route('bikes.manufacturer_hub', ['makerSlug' => $model->manufacturer->slug]) : route('bikes.search', ['manufacturer_id' => $model->manufacturer_id]) }}" class="hover:text-blue-600 transition-colors">{{ $model->manufacturer->name }}</a></li>
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
            <div class="flex items-center gap-2 mb-2">
                <span class="inline-block bg-blue-600 text-white text-[10px] font-bold px-2 py-1 rounded">
                    {{ $model->manufacturer->name }}
                </span>
                @if($model->is_discontinued)
                    <span class="inline-block bg-red-600 text-white text-[10px] font-bold px-2 py-1 rounded">
                        生産終了{{ $model->production_end_year ? "（{$model->production_end_year}年）" : '' }}
                    </span>
                @endif
            </div>
            <h1 class="text-3xl sm:text-5xl font-black tracking-tight mb-2">
                {{ $model->name }} <span class="text-2xl sm:text-3xl text-gray-300">の中古車・買取相場</span>
            </h1>
            <p class="text-gray-300 font-bold text-sm">
                {{ $model->manufacturer->name }}｜価格推移・リセールバリュー・オーナーレビュー
            </p>

            {{-- Xシェアボタン（この車種をPOSTする） --}}
            @php
                $modelShareText = '「' . $model->name . '」の詳細・レビュー・中古車情報はMotoHubでチェック！ #MotoHub #中古バイク #バイク好きと繋がりたい #バイクのある生活 #ツーリング #バイク乗りと繋がりたい #' . str_replace(' ', '', $model->name) . ' #' . $model->manufacturer->name . ' #バイク #バイクレビュー #中古バイク情報';
                $modelShareUrl = ($model->manufacturer?->slug && $model->slug)
                    ? route('bikes.model_detail', ['mfrSlug' => $model->manufacturer->slug, 'modelSlug' => $model->slug])
                    : route('bikes.model_detail.fallback', $model->id);
            @endphp
            <a href="https://twitter.com/intent/tweet?text={{ urlencode($modelShareText) }}&url={{ urlencode($modelShareUrl) }}"
               target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 bg-white/10 backdrop-blur-sm text-white text-xs font-bold rounded-full hover:bg-white/20 transition">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                シェア
            </a>

            {{-- 通知購読エリア（ファーストビュー内に前出し / iOS非PWAは自動でガイド表示） --}}
            <div class="mt-5" id="push-area-header"
                 data-model-id="{{ $model->id }}"
                 data-model-name="{{ $model->name }}"></div>

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
                @if(!empty($rankingStats) && $rankingStats['overallRank'])
                <a href="{{ route('ranking.model_stats', $model->id) }}" class="bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2 hover:bg-white/20 transition-colors">
                    <span class="text-[10px] text-gray-400 block">売れ筋</span>
                    <span class="text-xl font-black flex items-center gap-1">
                        @php $heroMedal = match($rankingStats['overallRank']) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => '🏆' }; @endphp
                        <span>{{ $heroMedal }}</span>
                        {{ $rankingStats['overallRank'] }}<span class="text-xs">位</span>
                    </span>
                </a>
                @endif
            </div>

        </div>
    </div>

    {{-- タブナビ --}}
    <div id="tab-nav" class="sticky top-0 z-30 bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex overflow-x-auto scrollbar-hide -mb-px">
                <button data-tab="overview" class="tab-btn whitespace-nowrap px-4 py-3 text-sm border-b-2 border-blue-600 text-blue-600 font-bold transition-colors hover:text-blue-600">概要</button>
                {{-- レビュー・FAQ を概要の直後へ（投稿導線を前方に出す。パネルはID照合のため不動でOK） --}}
                <button data-tab="community" class="tab-btn whitespace-nowrap px-4 py-3 text-sm border-b-2 border-transparent text-gray-500 transition-colors hover:text-gray-700">レビュー・FAQ</button>
                <button data-tab="market" class="tab-btn whitespace-nowrap px-4 py-3 text-sm border-b-2 border-transparent text-gray-500 transition-colors hover:text-gray-700">相場・価格</button>
                <button data-tab="inventory" class="tab-btn whitespace-nowrap px-4 py-3 text-sm border-b-2 border-transparent text-gray-500 transition-colors hover:text-gray-700">在庫・エリア</button>
                @if(!empty($relatedParts) || !empty($fitmentSummary))
                <button data-tab="parts" class="tab-btn whitespace-nowrap px-4 py-3 text-sm border-b-2 border-transparent text-gray-500 transition-colors hover:text-gray-700">パーツ</button>
                @endif
                @if(!empty($news) || !empty($videos))
                <button data-tab="media" class="tab-btn whitespace-nowrap px-4 py-3 text-sm border-b-2 border-transparent text-gray-500 transition-colors hover:text-gray-700">ニュース・動画</button>
                @endif
            </div>
        </div>
    </div>

    <div class="bg-gray-50 min-h-screen -mt-8 pb-16">
        <div class="max-w-7xl mx-auto px-4">
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 pt-8">
                
                {{-- メインコンテンツ --}}
                <div class="lg:col-span-8">

                    {{-- ===== オーナーの声 ダイジェスト（タブ操作なしで視界に入れる＝投稿導線の露出） =====
                         スペックはタブ内容のため物理的な「スペック直後」は取れない。タブ内容の手前・常時表示に置く。
                         既存変数（$model->reviews / $modelQuestions）を流用し追加クエリなし。 --}}
                    @php
                        $digestReviews = $model->reviews->sortByDesc('created_at')->take(2);
                        $digestReviewCount = $model->reviews->count();
                        $digestReviewAvg = $digestReviewCount > 0 ? round($model->reviews->avg('rating'), 1) : null;
                        $digestThreads = !empty($modelThreads) ? $modelThreads->take(2) : collect();
                    @endphp
                    <div class="space-y-4 mb-8">
                        {{-- レビュー ダイジェスト --}}
                        <div class="bg-white rounded-3xl shadow-sm p-5 sm:p-6 border border-gray-100">
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <h2 class="text-base sm:text-lg font-black text-gray-900 flex items-center gap-2">
                                    <span class="bg-blue-100 text-blue-600 p-1.5 rounded-lg shrink-0"><i data-lucide="message-circle" class="w-4 h-4"></i></span>
                                    オーナーレビュー
                                    @if($digestReviewCount > 0)
                                    <span class="text-xs text-gray-500 font-bold">（{{ $digestReviewCount }}件・<span class="text-amber-600">★{{ $digestReviewAvg }}</span>）</span>
                                    @endif
                                </h2>
                                <a href="#reviews" class="shrink-0 text-xs font-bold text-blue-600 hover:underline inline-flex items-center gap-0.5">すべて見る<i data-lucide="chevron-right" class="w-3.5 h-3.5"></i></a>
                            </div>
                            @forelse($digestReviews as $review)
                            <a href="#review-{{ $review->id }}" class="block rounded-2xl border border-gray-100 p-3 hover:border-blue-200 hover:bg-blue-50 transition-colors {{ ! $loop->last ? 'mb-2' : '' }}">
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="flex text-yellow-400 shrink-0">
                                        @for($i = 1; $i <= 5; $i++)<i data-lucide="star" class="w-3.5 h-3.5 {{ $i <= $review->rating ? 'fill-current' : 'text-gray-200' }}"></i>@endfor
                                    </div>
                                    <span class="text-sm font-bold text-gray-900 truncate">{{ $review->title }}</span>
                                </div>
                                <p class="text-sm text-gray-600 leading-relaxed line-clamp-3">{{ $review->body }}</p>
                                <p class="text-[11px] text-gray-400 mt-1">by {{ $review->nickname }}・{{ $review->created_at->format('Y/m/d') }}</p>
                            </a>
                            @empty
                            {{-- 0件: 呼び水（書くCTAは質問する と同一クラスに揃える） --}}
                            <div class="text-center py-4 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                                <p class="text-sm text-gray-600 font-bold mb-1">{{ $model->name }} のレビューはまだありません。</p>
                                <p class="text-xs text-gray-500 mb-3">最初のオーナーレビューを書きませんか？</p>
                                <a href="#review-form" class="inline-flex items-center gap-1 text-xs font-bold bg-blue-600 text-white px-4 py-2 rounded-full hover:bg-blue-700 transition-colors"><i data-lucide="pen-tool" class="w-3 h-3"></i>レビューを書く</a>
                            </div>
                            @endforelse

                            {{-- レビューがある場合も「書く」導線を常時表示（質問する と対称・#review-form へアンカー遷移） --}}
                            @if($digestReviewCount > 0)
                            <div class="mt-3 text-center">
                                <a href="#review-form" class="inline-flex items-center gap-1 text-xs font-bold bg-blue-600 text-white px-4 py-2 rounded-full hover:bg-blue-700 transition-colors"><i data-lucide="pen-tool" class="w-3 h-3"></i>レビューを書く</a>
                            </div>
                            @endif
                        </div>

                        {{-- クチコミ・相談 ダイジェスト（統合スレッド。質問にはMotoHub必答が即時） --}}
                        <div class="bg-white rounded-3xl shadow-sm p-5 sm:p-6 border border-gray-100">
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <h2 class="text-base sm:text-lg font-black text-gray-900 flex items-center gap-2">
                                    <span class="bg-blue-100 text-blue-600 p-1.5 rounded-lg shrink-0"><i data-lucide="messages-square" class="w-4 h-4"></i></span>
                                    クチコミ・相談
                                    @if(($modelThreadsTotal ?? 0) > 0)
                                    <span class="text-xs text-gray-500 font-bold">（{{ $modelThreadsTotal }}件）</span>
                                    @endif
                                </h2>
                                <a href="#threads" class="shrink-0 text-xs font-bold text-blue-600 hover:underline inline-flex items-center gap-0.5">すべて見る<i data-lucide="chevron-right" class="w-3.5 h-3.5"></i></a>
                            </div>
                            @forelse($digestThreads as $t)
                            <a href="#threads" class="block rounded-2xl border border-gray-100 p-3 hover:border-blue-200 hover:bg-blue-50 transition-colors {{ ! $loop->last ? 'mb-2' : '' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="text-sm font-bold text-gray-900 leading-snug"><span class="text-[10px] font-black px-1.5 py-0.5 rounded mr-1 {{ $t->type === 'question' ? 'text-blue-600 bg-blue-50' : 'text-emerald-700 bg-emerald-50' }}">{{ $t->type_badge_label }}</span>{{ $t->display_title }}</span>
                                    <span class="shrink-0 text-[11px] font-black text-gray-400">
                                        @if($t->type === 'question')返信{{ $t->published_replies_count }}件@else{{ $t->created_at->diffForHumans() }}@endif
                                    </span>
                                </div>
                            </a>
                            @empty
                            {{-- 0件: 呼び水 --}}
                            <div class="text-center py-4 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                                <p class="text-sm text-gray-600 font-bold mb-1">この{{ $model->name }}のクチコミ・相談はまだありません。</p>
                                <p class="text-xs text-gray-500 mb-3">「通勤に最高」などひとことでもOK。質問にはMotoHubがすぐ回答します。</p>
                                <a href="#threads" class="inline-flex items-center gap-1 text-xs font-bold bg-blue-600 text-white px-4 py-2 rounded-full hover:bg-blue-700 transition-colors"><i data-lucide="message-circle" class="w-3 h-3"></i>ひとこと・質問する</a>
                            </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- ===== タブ1: 概要 ===== --}}
                    <div id="tab-panel-overview" class="tab-panel space-y-8">

                    {{-- 新基準原付ハブへの相互リンク（対象/ベース/旧50ccモデルのみ表示） --}}
                    @include('bikes.partials.shinkijun-link', ['model' => $model])

                    {{-- 車種紹介テキスト（SEOの要） --}}
                    <div id="overview" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <h2 class="text-xl font-black text-gray-900 mb-4 flex items-center gap-2">
                            <span class="bg-indigo-100 text-indigo-600 p-2 rounded-lg"><i data-lucide="info" class="w-5 h-5"></i></span>
                            {{ $model->name }}とは
                        </h2>

                        @if($model->enriched_content)
                            {{-- AI生成コンテンツ --}}
                            <div class="text-sm text-gray-600 leading-relaxed space-y-4">
                                <p>{{ $model->enriched_content['introduction'] ?? '' }}</p>

                                @if(!empty($model->enriched_content['market_insight']))
                                <div>
                                    <h3 class="text-sm font-bold text-gray-800 mb-1 flex items-center gap-1.5">
                                        <i data-lucide="trending-up" class="w-4 h-4 text-indigo-500"></i>
                                        市場動向
                                    </h3>
                                    <p>{{ $model->enriched_content['market_insight'] }}</p>
                                </div>
                                @endif

                                @if(!empty($model->enriched_content['target_rider']))
                                <div>
                                    <h3 class="text-sm font-bold text-gray-800 mb-1 flex items-center gap-1.5">
                                        <i data-lucide="user-check" class="w-4 h-4 text-indigo-500"></i>
                                        こんなライダーにおすすめ
                                    </h3>
                                    <p>{{ $model->enriched_content['target_rider'] }}</p>
                                </div>
                                @endif

                                @if(!empty($model->enriched_content['buying_tips']))
                                <div>
                                    <h3 class="text-sm font-bold text-gray-800 mb-1 flex items-center gap-1.5">
                                        <i data-lucide="search-check" class="w-4 h-4 text-indigo-500"></i>
                                        中古で買う際のポイント
                                    </h3>
                                    <p>{{ $model->enriched_content['buying_tips'] }}</p>
                                </div>
                                @endif

                                @if(!empty($model->enriched_content['rivals']))
                                <div>
                                    <h3 class="text-sm font-bold text-gray-800 mb-1 flex items-center gap-1.5">
                                        <i data-lucide="git-compare" class="w-4 h-4 text-indigo-500"></i>
                                        ライバル車種
                                    </h3>
                                    <p>{{ $model->enriched_content['rivals'] }}</p>
                                </div>
                                @endif
                            </div>
                        @else
                            {{-- フォールバック: 既存の定型テンプレート --}}
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
                        @endif
                    </div>

                    {{-- 売れ筋ランキング --}}
                    @if(!empty($rankingStats))
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6 border border-blue-100">
                        <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
                            🏆 {{ $model->name }}の売れ筋ランキング
                        </h2>

                        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px" class="mb-4">
                            {{-- 総合ランキング --}}
                            <div class="bg-white rounded-lg shadow-sm p-4 text-center">
                                <p class="text-[10px] text-gray-400 font-bold mb-1">総合ランキング</p>
                                @if($rankingStats['overallRank'])
                                    @php
                                        $medal = match($rankingStats['overallRank']) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => null };
                                    @endphp
                                    <p class="text-2xl font-black {{ $rankingStats['overallRank'] <= 3 ? 'text-yellow-600' : ($rankingStats['overallRank'] <= 10 ? 'text-blue-600' : 'text-gray-900') }}">
                                        @if($medal)<span class="text-xl">{{ $medal }}</span> @endif
                                        {{ $rankingStats['overallRank'] }}<span class="text-xs text-gray-400 ml-0.5">位</span>
                                    </p>
                                    <p class="text-[10px] text-gray-400 font-bold">全{{ number_format($rankingStats['totalModels']) }}車種中</p>
                                    @if($rankingStats['overallRank'] <= 3)
                                        <span class="inline-block mt-1.5 px-2 py-0.5 rounded-full text-[10px] font-black bg-yellow-100 text-yellow-700 border border-yellow-200">トップ3！</span>
                                    @elseif($rankingStats['overallRank'] <= 10)
                                        <span class="inline-block mt-1.5 px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-100 text-blue-700 border border-blue-200">TOP10</span>
                                    @endif
                                @else
                                    <p class="text-2xl font-black text-gray-300">-</p>
                                @endif
                            </div>

                            {{-- カテゴリ内ランキング --}}
                            <div class="bg-white rounded-lg shadow-sm p-4 text-center">
                                <p class="text-[10px] text-gray-400 font-bold mb-1">カテゴリ内ランキング</p>
                                @if($rankingStats['categoryRank'])
                                    <p class="text-2xl font-black {{ $rankingStats['categoryRank'] <= 3 ? 'text-indigo-600' : 'text-gray-900' }}">
                                        {{ $rankingStats['categoryRank'] }}<span class="text-xs text-gray-400 ml-0.5">位</span>
                                    </p>
                                    <p class="text-[10px] text-gray-400 font-bold">{{ $model->categoryData?->name ?? 'カテゴリ' }}で{{ number_format($rankingStats['categoryTotal']) }}車種中</p>
                                @else
                                    <p class="text-2xl font-black text-gray-300">-</p>
                                    <p class="text-[10px] text-gray-400 font-bold">データなし</p>
                                @endif
                            </div>

                            {{-- 月間販売台数 --}}
                            <div class="bg-white rounded-lg shadow-sm p-4 text-center">
                                <p class="text-[10px] text-gray-400 font-bold mb-1">月間販売台数</p>
                                <p class="text-2xl font-black text-gray-900">
                                    {{ number_format($rankingStats['monthlySold']) }}<span class="text-xs text-gray-400 ml-0.5">台</span>
                                </p>
                                <p class="text-[10px] text-gray-400 font-bold">先月実績</p>
                                @if($rankingStats['isPopular'])
                                    <span class="inline-block mt-1.5 px-2 py-0.5 rounded-full text-[10px] font-black bg-green-100 text-green-700 border border-green-200">人気車種！</span>
                                @endif
                            </div>

                            {{-- 平均売却日数 --}}
                            <div class="bg-white rounded-lg shadow-sm p-4 text-center">
                                <p class="text-[10px] text-gray-400 font-bold mb-1">平均売却日数</p>
                                <p class="text-2xl font-black {{ $rankingStats['avgDays'] <= 14 ? 'text-red-600' : 'text-gray-900' }}">
                                    {{ $rankingStats['avgDays'] }}<span class="text-xs text-gray-400 ml-0.5">日</span>
                                </p>
                                <p class="text-[10px] text-gray-400 font-bold">掲載〜売却</p>
                                @if($rankingStats['avgDays'] <= 14 && $rankingStats['avgDays'] > 0)
                                    <span class="inline-block mt-1.5 px-2 py-0.5 rounded-full text-[10px] font-black bg-red-100 text-red-700 border border-red-200">即売れ！</span>
                                @endif
                            </div>
                        </div>

                        {{-- リンク --}}
                        <div class="flex flex-col sm:flex-row gap-2">
                            <a href="{{ route('ranking.index') }}" class="flex-1 text-center py-2.5 rounded-lg bg-white border border-blue-200 text-xs font-black text-blue-700 hover:bg-blue-50 transition-colors">
                                📊 売れ筋ランキングを見る →
                            </a>
                            <a href="{{ route('ranking.model_stats', $model->id) }}" class="flex-1 text-center py-2.5 rounded-lg bg-white border border-indigo-200 text-xs font-black text-indigo-700 hover:bg-indigo-50 transition-colors">
                                📈 {{ $model->name }}の詳しい分析 →
                            </a>
                        </div>
                    </div>
                    @endif

                    {{-- 年式分布 --}}
                    @if(!empty($yearStats) && $yearDistribution->count() >= 3)
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <h2 class="text-xl font-black text-gray-900 mb-4 flex items-center gap-2">
                            <span class="bg-cyan-100 text-cyan-600 p-2 rounded-lg"><i data-lucide="calendar-range" class="w-5 h-5"></i></span>
                            {{ $model->name }} の年式分布
                        </h2>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <div class="text-[10px] font-bold text-gray-400">流通年式</div>
                                <div class="text-base font-black text-gray-900">{{ $yearStats['min'] }}〜{{ $yearStats['max'] }}<span class="text-xs">年</span></div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <div class="text-[10px] font-bold text-gray-400">最多年式</div>
                                <div class="text-base font-black text-gray-900">{{ $yearStats['mode'] }}<span class="text-xs">年</span></div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <div class="text-[10px] font-bold text-gray-400">新車販売</div>
                                <div class="text-base font-black {{ $yearStats['has_new'] ? 'text-green-600' : 'text-gray-400' }}">{{ $yearStats['has_new'] ? '販売中' : '終了' }}</div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <div class="text-[10px] font-bold text-gray-400">データカバー率</div>
                                <div class="text-base font-black text-gray-900">{{ $yearStats['coverage'] }}<span class="text-xs">%</span></div>
                            </div>
                        </div>
                        <div class="relative w-full" style="height: {{ min(max($yearDistribution->count() * 18, 200), 400) }}px">
                            <canvas id="yearDistributionChart"></canvas>
                        </div>
                    </div>
                    <script>
                    (function() {
                        function initYearChart() {
                            var ctx = document.getElementById('yearDistributionChart');
                            if (!ctx) return;
                            var distData = @json($yearDistribution);
                            var currentYear = new Date().getFullYear();
                            var labels = distData.map(function(d) { return d.model_year + '年'; });
                            var values = distData.map(function(d) { return d.count; });
                            var colors = distData.map(function(d) {
                                return d.model_year >= currentYear ? 'rgba(34,197,94,0.7)' : 'rgba(59,130,246,0.7)';
                            });
                            var borderColors = distData.map(function(d) {
                                return d.model_year >= currentYear ? 'rgba(34,197,94,1)' : 'rgba(59,130,246,1)';
                            });
                            new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: '台数',
                                        data: values,
                                        backgroundColor: colors,
                                        borderColor: borderColors,
                                        borderWidth: 1,
                                        borderRadius: 3
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { display: false },
                                        tooltip: {
                                            callbacks: {
                                                label: function(ctx) { return ctx.parsed.y + '台'; }
                                            }
                                        }
                                    },
                                    scales: {
                                        x: {
                                            ticks: { font: { size: 10, weight: 'bold' }, maxRotation: 45, minRotation: 0 },
                                            grid: { display: false }
                                        },
                                        y: {
                                            beginAtZero: true,
                                            ticks: { precision: 0, font: { size: 10 } }
                                        }
                                    }
                                }
                            });
                        }
                        var attempts = 0;
                        var timer = setInterval(function() {
                            attempts++;
                            if (typeof Chart !== 'undefined') {
                                clearInterval(timer);
                                initYearChart();
                            } else if (attempts > 100) {
                                clearInterval(timer);
                            }
                        }, 100);
                    })();
                    </script>
                    @endif

                    {{-- モデル履歴 --}}
                    @if($model->model_history)
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <h2 class="text-xl font-black text-gray-900 mb-4 flex items-center gap-2">
                            <span class="bg-violet-100 text-violet-600 p-2 rounded-lg"><i data-lucide="history" class="w-5 h-5"></i></span>
                            {{ $model->name }} のモデル履歴
                        </h2>

                        {{-- サマリー --}}
                        <div class="flex flex-wrap gap-3 mb-5 text-sm">
                            @if($model->model_history['first_year'] ?? null)
                                <span class="bg-gray-50 rounded-lg px-3 py-1.5 font-bold text-gray-700">
                                    発売: {{ $model->model_history['first_year'] }}年
                                </span>
                            @endif
                            @if($model->model_history['last_year'] ?? null)
                                <span class="bg-red-50 rounded-lg px-3 py-1.5 font-bold text-red-600">
                                    生産終了: {{ $model->model_history['last_year'] }}年
                                </span>
                            @elseif($model->model_history['is_current'] ?? false)
                                <span class="bg-green-50 rounded-lg px-3 py-1.5 font-bold text-green-600">
                                    現行販売中
                                </span>
                            @endif
                            @if($model->model_history['current_new_price_min'] ?? null)
                                <span class="bg-blue-50 rounded-lg px-3 py-1.5 font-bold text-blue-700">
                                    新車価格: {{ $model->model_history['current_new_price_min'] }}〜{{ $model->model_history['current_new_price_max'] }}万円
                                </span>
                            @endif
                        </div>

                        {{-- 世代タイムライン --}}
                        @if(!empty($model->model_history['generations']))
                        <div class="space-y-0">
                            @foreach($model->model_history['generations'] as $gen)
                            <div class="flex gap-4 items-start group">
                                <div class="w-24 shrink-0 text-right pt-1">
                                    <span class="text-xs font-black text-gray-500 font-mono">{{ $gen['start_year'] ?? '?' }}〜{{ $gen['end_year'] ?? '現在' }}</span>
                                </div>
                                <div class="flex flex-col items-center shrink-0">
                                    <div class="w-3 h-3 rounded-full {{ $loop->last && !($gen['end_year'] ?? null) ? 'bg-green-500' : 'bg-blue-400' }} border-2 border-white shadow"></div>
                                    @if(!$loop->last)
                                        <div class="w-0.5 flex-1 bg-gray-200 min-h-[40px]"></div>
                                    @endif
                                </div>
                                <div class="flex-1 pb-5">
                                    <div class="text-sm font-black text-gray-800">{{ $gen['name'] ?? '' }}</div>
                                    @if($gen['changes'] ?? null)
                                        <div class="text-xs text-gray-500 mt-0.5 leading-relaxed">{{ $gen['changes'] }}</div>
                                    @endif
                                    @if($gen['new_price'] ?? null)
                                        <div class="text-[10px] text-gray-400 mt-1 font-bold">新車価格: 約{{ $gen['new_price'] }}万円</div>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endif

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

                    {{-- 関連する比較（この車種を含む比較ページへの内部リンク・存在ペアのみ・オーファン解消） --}}
                    @if(!empty($relatedCompares) && $relatedCompares->count() > 0)
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="git-compare" class="w-5 h-5 text-blue-600"></i> {{ $model->name }} の比較
                        </h2>
                        <div class="space-y-2">
                            @foreach($relatedCompares as $cmp)
                            <a href="{{ $cmp->url }}" class="flex items-center justify-between gap-2 p-3 rounded-xl border border-gray-100 hover:border-blue-200 hover:bg-blue-50/40 transition-colors">
                                <span class="text-sm font-black text-gray-800 truncate">{{ $cmp->model1->name }} <span class="text-gray-400 font-bold">vs</span> {{ $cmp->model2->name }}</span>
                                <span class="shrink-0 inline-flex items-center gap-1 text-xs font-bold text-blue-600">比較を見る<i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></span>
                            </a>
                            @endforeach
                        </div>
                        <div class="mt-4 text-center">
                            <a href="{{ route('bikes.model_compare_hub') }}" class="inline-flex items-center gap-1.5 text-sm font-bold text-blue-600 hover:underline">
                                他の車種比較を見る<i data-lucide="arrow-right" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>
                    @endif

                    </div>{{-- /tab-panel-overview --}}

                    {{-- ===== タブ2: 相場・価格 ===== --}}
                    <div id="tab-panel-market" class="tab-panel space-y-8" style="display:none">

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
                                <p class="text-[10px] text-gray-400 text-center mb-3">
                                    ※{{ $resale['data_count'] }}件の実売データに基づく予想です。実際の買取額は車両状態や時期により変動します。
                                </p>

                                @if(isset($resale['confidence']))
                                <div class="flex justify-center gap-4 text-[10px] text-gray-500">
                                    <span>信頼度:
                                        @if($resale['confidence'] === 'high') ★★★
                                        @elseif($resale['confidence'] === 'medium') ★★☆
                                        @else ★☆☆
                                        @endif
                                    </span>
                                    @if(isset($resale['factors']['speed_label']) && $resale['factors']['speed_label'] !== 'データ不足')
                                    <span>{{ $resale['factors']['speed_label'] }}</span>
                                    @endif
                                </div>
                                @endif
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

                    {{-- 地域別中古相場（中央値）: 8地方ブロック。日次バッチ事前計算・最低10台ゲート --}}
                    @if(!empty($regionPriceStats['regions']))
                    @php $hasReference = collect($regionPriceStats['regions'])->contains(fn ($r) => empty($r['robust'])); @endphp
                    <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
                        <h2 class="text-lg font-black text-gray-900 mb-2 flex items-center gap-2">
                            <i data-lucide="map" class="w-5 h-5 text-green-500"></i>
                            {{ $model->name }} の地域別中古相場（中央値）
                        </h2>
                        @if(!empty($regionPriceStats['headline']))
                        <p class="text-sm text-gray-500 leading-relaxed mb-5">{{ $regionPriceStats['headline'] }}</p>
                        @endif

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b-2 border-gray-200">
                                        <th class="text-left py-2.5 px-3 font-bold text-gray-500">地域</th>
                                        <th class="text-right py-2.5 px-3 font-bold text-gray-500">中古相場（中央値）</th>
                                        <th class="text-right py-2.5 px-3 font-bold text-gray-500">掲載台数</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($regionPriceStats['regions'] as $r)
                                    <tr>
                                        <td class="py-2.5 px-3 font-bold text-gray-700">
                                            {{ $r['block'] }}
                                            @if(empty($r['robust']))
                                            <span class="ml-1 align-middle text-[10px] font-bold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded">参考</span>
                                            @endif
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-black text-blue-600">{{ $r['median_man'] }}<span class="text-xs text-gray-400 ml-0.5">万円</span></td>
                                        <td class="py-2.5 px-3 text-right font-bold text-gray-500">{{ number_format($r['count']) }}<span class="text-xs text-gray-400 ml-0.5">台</span></td>
                                    </tr>
                                    @endforeach
                                    @if(!empty($regionPriceStats['national']))
                                    <tr class="bg-gray-50">
                                        <td class="py-2.5 px-3 font-black text-gray-800">全国</td>
                                        <td class="py-2.5 px-3 text-right font-black text-gray-900">{{ $regionPriceStats['national']['median_man'] }}<span class="text-xs text-gray-400 ml-0.5">万円</span></td>
                                        <td class="py-2.5 px-3 text-right font-bold text-gray-600">{{ number_format($regionPriceStats['national']['count']) }}<span class="text-xs text-gray-400 ml-0.5">台</span></td>
                                    </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-4">
                            ※支払総額（total_price）の中央値。各セルは掲載台数10台以上のみ表示。1販売店あたりの寄与は最大5台に制限して算出（外れ値・水増し対策）。@if($hasReference)<span class="text-amber-600 font-bold">「参考」</span>は掲載台数が少なく中央値が振れやすい地域の目安値です。@endif
                        </p>
                    </div>
                    @endif

                    {{-- 地域差の解釈本文（spreadからテンプレ生成。robust<2はnullで非表示＝薄い断定を避ける） --}}
                    @if(!empty($regionPriceStats['spread_narrative']))
                    <div class="bg-green-50 rounded-2xl p-6 border border-green-100">
                        <h3 class="text-base font-black text-gray-900 mb-2 flex items-center gap-2">
                            <i data-lucide="map-pinned" class="w-5 h-5 text-green-600"></i>
                            地域で見る {{ $model->name }} の買い方
                        </h3>
                        <p class="text-sm text-gray-600 leading-relaxed">{{ $regionPriceStats['spread_narrative'] }}</p>
                        <div class="mt-4 flex flex-col sm:flex-row gap-3">
                            <a href="{{ route('bikes.search', ['bike_model_id' => $model->id]) }}" class="inline-flex items-center justify-center gap-2 bg-gray-900 hover:bg-gray-800 text-white font-bold text-xs px-5 py-3 rounded-xl transition-colors">
                                <i data-lucide="search" class="w-4 h-4"></i> この車種の在庫を見る
                            </a>
                            {{-- 値下げ・入荷通知CTA（push-manager.js が描画・bike_model_id紐付け） --}}
                            <div class="flex-1" id="push-area-spread-{{ $model->id }}" data-model-id="{{ $model->id }}" data-model-name="{{ $model->name }}"></div>
                        </div>
                        {{-- 地域差ページへのクロスリンク（ゲート該当モデルのみ＝デッドリンク/薄ページ誘導を回避） --}}
                        @if(!empty($regionPriceStats['spread']) && $regionPriceStats['spread']['robust_block_count'] >= 3 && $regionPriceStats['spread']['pct'] >= 20 && $model->slug)
                        <a href="{{ route('bikes.region_price', $model->slug) }}" class="inline-flex items-center gap-1.5 mt-4 text-sm font-bold text-green-700 hover:text-green-900 transition-colors">
                            {{ $model->name }}のエリア別相場をくわしく見る
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                        @endif
                    </div>
                    @endif

                    {{-- 相場を見た直後に「販売中の車両」を出す（相場→在庫の自然な導線・モバイル最下部問題も緩和）。
                         データは既存 $listings を流用（追加クエリなし）。在庫実データは在庫系ブロックに集約の原則どおり。 --}}
                    @if(count($listings) > 0)
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-black text-gray-900 flex items-center gap-2">
                                <span class="bg-gray-100 text-gray-600 p-2 rounded-lg"><i data-lucide="bike" class="w-5 h-5"></i></span>
                                いま販売中の {{ $model->name }}
                            </h2>
                            <a href="{{ route('bikes.search', ['bike_model_id' => $model->id]) }}" class="text-xs font-bold text-blue-600 hover:underline">すべて見る</a>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($listings->take(4) as $bike)
                            <a href="{{ route('bikes.show', $bike['id']) }}" class="flex gap-3 group p-2 rounded-xl hover:bg-gray-50 transition-colors border border-gray-100">
                                <div class="w-24 h-20 shrink-0 rounded-lg overflow-hidden bg-gray-100">
                                    @if(!empty($bike['images'][0]))
                                        <img src="{{ $bike['images'][0] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" decoding="async">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-300"><i data-lucide="bike"></i></div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0 py-1">
                                    <h4 class="text-sm font-black text-gray-800 line-clamp-2 group-hover:text-blue-600 transition-colors mb-1">{{ $bike['name'] }}</h4>
                                    <div class="text-red-500 font-black text-lg">{{ $bike['total_price'] }}<span class="text-xs">万円</span></div>
                                    <div class="text-[11px] text-gray-400 mt-0.5">{{ $bike['prefecture'] }}</div>
                                </div>
                            </a>
                            @endforeach
                        </div>
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <a href="{{ route('bikes.search', ['bike_model_id' => $model->id]) }}" class="block w-full bg-gray-900 hover:bg-gray-800 text-white font-bold text-sm text-center py-3 rounded-xl transition-colors">
                                {{ $model->name }} の在庫をすべて見る（{{ number_format($activeCount) }}台）
                            </a>
                        </div>
                    </div>
                    @endif

                    </div>{{-- /tab-panel-market --}}

                    {{-- ===== タブ3: 在庫・エリア ===== --}}
                    <div id="tab-panel-inventory" class="tab-panel space-y-8" style="display:none">

                    {{-- エリア別リンク：CTR最高の車種×エリアLP(bikes.landing)へ誘導し権威を集中。
                         リンク先は indexable な県(在庫5台以上=sitemap/noindex閾値)のみに限定する。 --}}
                    @php $linkablePrefStocks = (isset($prefectureStocks) ? $prefectureStocks : collect())->where('stock_count', '>=', 5); @endphp
                    @if($linkablePrefStocks->isNotEmpty())
                    <div class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100">
                        <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                            <span class="bg-orange-100 text-orange-600 p-2 rounded-lg"><i data-lucide="map-pin" class="w-5 h-5"></i></span>
                            {{ $model->name }}をエリアから探す
                        </h2>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                            @foreach($linkablePrefStocks as $ps)
                            <a href="{{ route('bikes.landing', ['prefecture' => $ps->prefecture, 'slug' => $model->name]) }}"
                               class="group flex items-center justify-between bg-gray-50 hover:bg-blue-50 border border-gray-100 hover:border-blue-200 rounded-xl px-4 py-3 transition-all duration-200">
                                <span class="text-xs font-black text-gray-800 group-hover:text-blue-700 transition-colors">{{ $ps->prefecture }}</span>
                                <span class="text-[10px] font-bold text-gray-400 group-hover:text-blue-500 bg-white px-2 py-0.5 rounded-full border border-gray-100">{{ $ps->stock_count }}台</span>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- 販売中車両一覧（タブ内） --}}
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-black text-gray-900 flex items-center gap-2">
                                <span class="bg-gray-100 text-gray-600 p-2 rounded-lg"><i data-lucide="bike" class="w-5 h-5"></i></span>
                                {{ $model->name }} の販売中車両
                            </h2>
                            <a href="{{ route('bikes.search', ['bike_model_id' => $model->id]) }}" class="text-xs font-bold text-blue-600 hover:underline">すべて見る</a>
                        </div>
                        <div class="space-y-4">
                            @forelse($listings as $bike)
                                <a href="{{ route('bikes.show', $bike['id']) }}" class="flex gap-3 group p-2 -mx-2 rounded-xl hover:bg-gray-50 transition-colors">
                                    <div class="w-24 h-20 shrink-0 rounded-lg overflow-hidden bg-gray-100 relative">
                                        @if(!empty($bike['images'][0]))
                                            <img src="{{ $bike['images'][0] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" decoding="async">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-gray-300"><i data-lucide="bike"></i></div>
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0 py-1">
                                        <h4 class="text-sm font-black text-gray-800 line-clamp-2 group-hover:text-blue-600 transition-colors mb-1">{{ $bike['name'] }}</h4>
                                        <div class="text-red-500 font-black text-lg">{{ $bike['total_price'] }}<span class="text-xs">万円</span></div>
                                        <div class="text-[11px] text-gray-400 mt-0.5">{{ $bike['prefecture'] }}</div>
                                    </div>
                                </a>
                            @empty
                                <div class="text-center py-8 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                                    <p class="text-sm text-gray-500 font-bold">現在、在庫はありません。</p>
                                </div>
                            @endforelse
                        </div>
                        @if(count($listings) > 0)
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <a href="{{ route('bikes.search', ['bike_model_id' => $model->id]) }}" class="block w-full bg-gray-900 hover:bg-gray-800 text-white font-bold text-sm text-center py-3 rounded-xl transition-colors">
                                {{ $model->name }} の在庫をすべて見る（{{ number_format($activeCount) }}台）
                            </a>
                        </div>
                        @endif
                    </div>

                    </div>{{-- /tab-panel-inventory --}}

                    {{-- ===== タブ4: パーツ ===== --}}
                    @if(!empty($relatedParts) || !empty($fitmentSummary))
                    <div id="tab-panel-parts" class="tab-panel space-y-6" style="display:none">

                    {{-- 整備・消耗品の適合情報（規格サマリ＋適合表への内部リンク。型番は出さず入口に徹する） --}}
                    @if(!empty($fitmentSummary))
                    <div id="fitment-summary" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6">
                        <h3 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="wrench" class="w-5 h-5 text-blue-600"></i> 整備・消耗品の適合情報
                        </h3>
                        <div class="divide-y divide-gray-100">
                            @foreach($fitmentSummary as $fs)
                            <div class="py-3 first:pt-0 last:pb-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div class="min-w-0">
                                    <span class="text-sm font-black text-gray-800">{{ $fs['label'] }}</span>
                                    <span class="block text-xs text-gray-500 mt-0.5">{{ $fs['summary'] }}</span>
                                </div>
                                <a href="{{ $fs['url'] }}"
                                   class="shrink-0 inline-flex items-center gap-1 text-sm font-bold text-blue-600 hover:text-blue-700 hover:underline">
                                    {{ $fs['anchor'] }}
                                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                </a>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- 消耗品チェックリスト --}}
                    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5">
                        <h3 class="text-base font-black text-gray-900 mb-1 flex items-center gap-2">
                            <i data-lucide="clipboard-check" class="w-5 h-5 text-amber-600"></i>
                            {{ $model->name }}の消耗品チェックリスト
                        </h3>
                        <p class="text-xs text-gray-500 mb-3">定期交換が必要なパーツの目安</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                            <div class="flex items-center gap-2 bg-white rounded-lg px-3 py-2 border border-amber-100">
                                <span class="text-lg">🔋</span><span class="text-xs font-bold text-gray-700">バッテリー（2〜3年）</span>
                            </div>
                            <div class="flex items-center gap-2 bg-white rounded-lg px-3 py-2 border border-amber-100">
                                <span class="text-lg">⛽</span><span class="text-xs font-bold text-gray-700">エンジンオイル（3,000km）</span>
                            </div>
                            <div class="flex items-center gap-2 bg-white rounded-lg px-3 py-2 border border-amber-100">
                                <span class="text-lg">🔗</span><span class="text-xs font-bold text-gray-700">チェーン（15,000〜20,000km）</span>
                            </div>
                            <div class="flex items-center gap-2 bg-white rounded-lg px-3 py-2 border border-amber-100">
                                <span class="text-lg">🛞</span><span class="text-xs font-bold text-gray-700">タイヤ（10,000〜20,000km）</span>
                            </div>
                            <div class="flex items-center gap-2 bg-white rounded-lg px-3 py-2 border border-amber-100">
                                <span class="text-lg">🔧</span><span class="text-xs font-bold text-gray-700">ブレーキパッド（10,000km）</span>
                            </div>
                            <div class="flex items-center gap-2 bg-white rounded-lg px-3 py-2 border border-amber-100">
                                <span class="text-lg">⚡</span><span class="text-xs font-bold text-gray-700">プラグ（5,000〜10,000km）</span>
                            </div>
                        </div>
                    </div>

                    {{-- カテゴリ別パーツ（楽天パーツがある時のみ。fitmentのみの場合は非表示） --}}
                    @if(!empty($relatedParts))
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="wrench" class="w-5 h-5 text-blue-500"></i>
                            {{ $model->name }} のパーツを探す
                        </h2>

                        {{-- カテゴリサブタブ --}}
                        <div class="flex overflow-x-auto scrollbar-hide gap-2 mb-5 pb-1">
                            @foreach($relatedParts as $catKey => $category)
                            <button data-parts-tab="{{ $catKey }}"
                                    class="parts-tab-btn px-3 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition-colors
                                           {{ $loop->first ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                                {{ $category['name'] }}（{{ count($category['items']) }}）
                            </button>
                            @endforeach
                        </div>

                        {{-- カテゴリ別商品パネル --}}
                        @foreach($relatedParts as $catKey => $category)
                        <div class="parts-panel {{ $loop->first ? '' : 'hidden' }}" data-parts-panel="{{ $catKey }}">
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                @foreach($category['items'] as $part)
                                @php
                                    $hasCode = !empty($part['jan_code']) || !empty($part['part_number']);
                                    $compareParams = [];
                                    if (!empty($part['jan_code'])) $compareParams['jan'] = $part['jan_code'];
                                    if (!empty($part['part_number'])) $compareParams['partnum'] = $part['part_number'];
                                    $compareParams['keyword'] = \Illuminate\Support\Str::limit($part['name'], 100, '');
                                    $compareUrl = route('parts.compare', $compareParams);
                                @endphp
                                <div class="bg-gray-50 rounded-xl overflow-hidden hover:shadow-md transition-shadow flex flex-col border border-gray-100">
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
                                        <div class="flex items-baseline gap-1.5 mb-1">
                                            <span class="text-sm font-black text-red-600">&yen;{{ number_format($part['price']) }}</span>
                                            @if($part['postageFlag'] == 1)
                                                <span class="text-[10px] font-bold bg-green-100 text-green-700 px-1 py-0.5 rounded">送料無料</span>
                                            @endif
                                        </div>
                                        <div class="text-[10px] text-gray-400 mb-2 space-y-0.5">
                                            <div class="truncate">{{ $part['shopName'] }}</div>
                                            @if($part['reviewCount'] > 0)
                                                <div class="flex items-center gap-0.5">
                                                    <span class="text-yellow-500">★</span>
                                                    <span class="font-bold text-gray-600">{{ $part['reviewAverage'] }}</span>
                                                    <span>（{{ $part['reviewCount'] }}件）</span>
                                                </div>
                                            @endif
                                            @if($part['pointRate'] > 1)
                                                <div class="text-orange-500 font-bold">ポイント{{ $part['pointRate'] }}倍</div>
                                            @endif
                                        </div>
                                        <div class="mt-auto flex flex-col gap-1">
                                            @if($hasCode)
                                                <a href="{{ $compareUrl }}"
                                                    class="block text-center text-[11px] font-bold py-1.5 rounded-lg transition-colors bg-blue-600 hover:bg-blue-700 text-white">
                                                    3社で価格比較
                                                </a>
                                            @else
                                                <a href="{{ $part['url'] }}" target="_blank" rel="noopener noreferrer"
                                                    class="block text-center text-[11px] font-bold py-1.5 rounded-lg transition-colors bg-red-500 hover:bg-red-600 text-white">
                                                    楽天で見る
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endforeach

                        <div class="mt-5 text-center">
                            <a href="{{ route('parts.index', ['bike' => $model->name]) }}"
                                class="inline-flex items-center gap-1 text-sm font-bold text-blue-600 hover:text-blue-800 transition-colors">
                                {{ $model->name }} のパーツをもっと見る
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </a>
                        </div>
                    </div>
                    @endif{{-- /カテゴリ別パーツ --}}

                    </div>{{-- /tab-panel-parts --}}
                    @endif

                    {{-- ===== タブ5: ニュース・動画 ===== --}}
                    @if(!empty($news) || !empty($videos))
                    <div id="tab-panel-media" class="tab-panel space-y-8" style="display:none">

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
                                @php $articleDomain = parse_url($article['url'], PHP_URL_HOST); @endphp
                                <div class="w-20 h-[60px] rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                                    @if(!empty($article['image']))
                                        <img src="{{ $article['image'] }}" alt="" class="w-full h-full object-cover" loading="lazy"
                                             onerror="this.onerror=null;this.parentNode.innerHTML='<div class=\'w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100\'><img src=\'https://www.google.com/s2/favicons?domain={{ $articleDomain }}&sz=64\' class=\'w-5 h-5 rounded\' onerror=\'this.style.display=&quot;none&quot;\'></div>'">
                                    @elseif($articleDomain)
                                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100">
                                            <img src="https://www.google.com/s2/favicons?domain={{ $articleDomain }}&sz=64" alt="" class="w-5 h-5 rounded" loading="lazy" onerror="this.style.display='none'">
                                        </div>
                                    @else
                                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-indigo-50 to-blue-50">
                                            <span class="text-sm font-black text-indigo-200">M</span>
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

                    </div>{{-- /tab-panel-media --}}
                    @endif

                    {{-- ===== タブ6: レビュー・FAQ ===== --}}
                    <div id="tab-panel-community" class="tab-panel space-y-8" style="display:none">

                    {{-- レビューサマリー: レーダーチャート --}}
                    @if(!empty($categoryReviewStats))
                    <div class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100">
                        <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                            <span class="bg-yellow-100 text-yellow-600 p-2 rounded-lg"><i data-lucide="radar" class="w-5 h-5"></i></span>
                            {{ $model->name }} 項目別評価
                        </h2>
                        <div class="flex flex-col sm:flex-row items-center gap-6">
                            <div class="w-full sm:w-1/2 max-w-[300px]">
                                <canvas id="reviewRadarChart"></canvas>
                            </div>
                            <div class="w-full sm:w-1/2">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200">
                                            <th class="text-left py-2 text-xs font-bold text-gray-500">項目</th>
                                            <th class="text-center py-2 text-xs font-bold text-gray-500">平均</th>
                                            <th class="text-center py-2 text-xs font-bold text-gray-500">カテゴリ平均</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $statLabels = [
                                                'design' => 'デザイン',
                                                'engine' => 'エンジン性能',
                                                'handling' => '取り回し',
                                                'fuel_economy' => '燃費',
                                                'cost_performance' => 'コスパ',
                                            ];
                                        @endphp
                                        @foreach($statLabels as $key => $label)
                                        <tr class="border-b border-gray-50">
                                            <td class="py-2 text-xs font-bold text-gray-700">{{ $label }}</td>
                                            <td class="py-2 text-center text-sm font-black text-gray-900">
                                                {{ $categoryReviewStats[$key]['avg'] ?? '-' }}
                                            </td>
                                            <td class="py-2 text-center text-sm font-bold text-gray-400">
                                                {{ $categoryReviewStats[$key]['category_avg'] ?? '-' }}
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <script>
                    (function() {
                        function initRadarChart() {
                        var ctx = document.getElementById('reviewRadarChart');
                        if (!ctx) return;
                        var stats = @json($categoryReviewStats);
                        new Chart(ctx, {
                            type: 'radar',
                            data: {
                                labels: ['デザイン', 'エンジン性能', '取り回し', '燃費', 'コスパ'],
                                datasets: [
                                    {
                                        label: '{{ $model->name }}',
                                        data: [
                                            stats.design.avg || 0,
                                            stats.engine.avg || 0,
                                            stats.handling.avg || 0,
                                            stats.fuel_economy.avg || 0,
                                            stats.cost_performance.avg || 0
                                        ],
                                        backgroundColor: 'rgba(59, 130, 246, 0.15)',
                                        borderColor: 'rgba(59, 130, 246, 0.8)',
                                        borderWidth: 2,
                                        pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                                        pointRadius: 3
                                    },
                                    {
                                        label: 'カテゴリ平均',
                                        data: [
                                            stats.design.category_avg || 0,
                                            stats.engine.category_avg || 0,
                                            stats.handling.category_avg || 0,
                                            stats.fuel_economy.category_avg || 0,
                                            stats.cost_performance.category_avg || 0
                                        ],
                                        backgroundColor: 'rgba(156, 163, 175, 0.1)',
                                        borderColor: 'rgba(156, 163, 175, 0.5)',
                                        borderWidth: 1,
                                        borderDash: [4, 4],
                                        pointBackgroundColor: 'rgba(156, 163, 175, 0.7)',
                                        pointRadius: 2
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: true,
                                scales: {
                                    r: {
                                        min: 0,
                                        max: 5,
                                        ticks: { stepSize: 1, display: false },
                                        pointLabels: { font: { size: 11, weight: 'bold' } }
                                    }
                                },
                                plugins: {
                                    legend: { position: 'bottom', labels: { font: { size: 11 } } }
                                }
                            }
                        });
                        }
                        var attempts = 0;
                        var timer = setInterval(function() {
                            attempts++;
                            if (typeof Chart !== 'undefined') {
                                clearInterval(timer);
                                initRadarChart();
                            } else if (attempts > 100) {
                                clearInterval(timer);
                            }
                        }, 100);
                    })();
                    </script>
                    @endif

                    {{-- 3. ユーザーレビュー --}}
                    <div class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100" id="reviews">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
                            <h2 class="text-xl font-black text-gray-900 flex items-center gap-2">
                                <span class="bg-green-100 text-green-600 p-2 rounded-lg shrink-0"><i data-lucide="message-circle" class="w-5 h-5"></i></span>
                                <span>
                                    {{ $model->name }} オーナーレビュー
                                    <span class="text-sm text-gray-500 font-bold ml-1 inline-block">({{ $reviewStats->count ?? 0 }}件)</span>
                                </span>
                            </h2>
                            <a href="#review-form" class="text-xs font-bold bg-black text-white px-4 py-3 sm:py-2 rounded-full hover:bg-gray-800 transition-colors inline-flex items-center justify-center w-full sm:w-auto">
                                <i data-lucide="pen-tool" class="w-3 h-3 mr-1"></i>投稿する
                            </a>
                        </div>

                        @php
                            // 表示・集計は承認済み(is_approved)のみ＝シード/非承認を除外。既定=参考になった順、切替=新着順。
                            $approvedReviews = $model->reviews->where('is_approved', true);
                            $reviewSort = request('review_sort') === 'new' ? 'created_at' : 'helpful_count';
                            $sortedReviews = $approvedReviews->sortByDesc($reviewSort)->values();
                        @endphp

                        {{-- 集計サマリ（総合★＋件数＋★分布バー） --}}
                        @if(($reviewStats->count ?? 0) > 0)
                        <div class="bg-gray-50 rounded-2xl p-5 mb-6 grid grid-cols-1 sm:grid-cols-3 gap-4 items-center">
                            <div class="text-center sm:border-r border-gray-200">
                                <div class="text-4xl font-black text-gray-900">{{ $reviewStats->avg_rating }}</div>
                                <div class="flex justify-center text-yellow-400 my-1">
                                    @for($i = 1; $i <= 5; $i++)<i data-lucide="star" class="w-4 h-4 {{ $i <= round($reviewStats->avg_rating) ? 'fill-current' : 'text-gray-200' }}"></i>@endfor
                                </div>
                                <div class="text-xs text-gray-400 font-bold">{{ $reviewStats->count }}件のレビュー</div>
                            </div>
                            <div class="sm:col-span-2 space-y-1.5">
                                @foreach($reviewDistribution as $star => $cnt)
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="w-7 text-gray-500 font-bold shrink-0">★{{ $star }}</span>
                                    <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden"><div class="h-full bg-yellow-400 rounded-full" style="width: {{ $reviewStats->count > 0 ? round($cnt / $reviewStats->count * 100) : 0 }}%"></div></div>
                                    <span class="w-6 text-right text-gray-400 shrink-0">{{ $cnt }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        {{-- ソート切替 --}}
                        <div class="flex items-center gap-3 mb-4 text-xs font-bold">
                            <a href="{{ $model->seo_url }}#reviews" class="{{ request('review_sort') !== 'new' ? 'text-blue-600' : 'text-gray-400 hover:text-gray-600' }}">参考になった順</a>
                            <a href="{{ $model->seo_url }}?review_sort=new#reviews" class="{{ request('review_sort') === 'new' ? 'text-blue-600' : 'text-gray-400 hover:text-gray-600' }}">新着順</a>
                        </div>
                        @endif

                        @php $showReviewShare = true; @endphp
                        <div class="space-y-6 mb-12" id="model-review-list">
                            @forelse($sortedReviews as $review)
                                <div class="border-b border-gray-100 pb-6 last:border-0" id="review-{{ $review->id }}" x-data="{ helped: false, count: {{ $review->helpful_count }}, report: false }" x-init="helped = !!localStorage.getItem('review_helpful_{{ $review->id }}')">
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
                                    @if($review->rating_design || $review->rating_engine || $review->rating_handling || $review->rating_fuel_economy || $review->rating_cost_performance)
                                    <div class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-gray-500 mb-2">
                                        @if($review->rating_design)<span>デザイン:<span class="text-yellow-500">★</span>{{ $review->rating_design }}</span>@endif
                                        @if($review->rating_engine)<span>エンジン性能:<span class="text-yellow-500">★</span>{{ $review->rating_engine }}</span>@endif
                                        @if($review->rating_handling)<span>取り回し:<span class="text-yellow-500">★</span>{{ $review->rating_handling }}</span>@endif
                                        @if($review->rating_fuel_economy)<span>燃費:<span class="text-yellow-500">★</span>{{ $review->rating_fuel_economy }}</span>@endif
                                        @if($review->rating_cost_performance)<span>コスパ:<span class="text-yellow-500">★</span>{{ $review->rating_cost_performance }}</span>@endif
                                    </div>
                                    @endif
                                    <p class="text-sm text-gray-600 leading-relaxed mb-2 whitespace-pre-wrap">{{ $review->body }}</p>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <p class="text-xs text-gray-400 font-bold flex items-center gap-1">by {{ $review->nickname }}@if($review->user_id)<span class="inline-flex items-center gap-0.5 text-[10px] font-black text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded"><i data-lucide="badge-check" class="w-3 h-3"></i>ログインユーザー</span>@endif</p>
                                            <button type="button"
                                                    @click="if(!helped){ helped=true; localStorage.setItem('review_helpful_{{ $review->id }}','1'); fetch('{{ route('bikes.review.helpful', $review->id) }}',{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}}).then(r=>r.json()).then(d=>count=d.helpful_count).catch(()=>{}); }"
                                                    class="inline-flex items-center gap-1 text-[11px] font-bold transition-colors" :class="helped ? 'text-blue-600' : 'text-gray-400 hover:text-blue-600'">
                                                <i data-lucide="thumbs-up" class="w-3 h-3"></i>参考になった<span x-show="count>0" x-text="count"></span>
                                            </button>
                                            <button type="button" @click="report = !report" class="inline-flex items-center gap-0.5 text-[11px] font-bold text-gray-300 hover:text-red-500 transition-colors" aria-label="このレビューを報告する">
                                                <i data-lucide="flag" class="w-2.5 h-2.5"></i>報告
                                            </button>
                                        </div>
                                        @if($showReviewShare)
                                        @php
                                            $rvTags = ['#MotoHub', '#バイクレビュー', '#中古バイク', '#バイク乗りと繋がりたい', '#バイク好きと繋がりたい', '#バイクのある生活', '#ツーリング'];

                                            $rvMakerSlug = $model->manufacturer?->slug ?? '';
                                            match ($rvMakerSlug) {
                                                'yamaha' => array_push($rvTags, '#YAMAHAが美しい', '#yamaha'),
                                                'honda' => array_push($rvTags, '#Honda党', '#honda'),
                                                'kawasaki' => array_push($rvTags, '#漢は黙ってカワサキ', '#kawasaki'),
                                                'suzuki' => array_push($rvTags, '#鈴菌', '#suzuki'),
                                                default => $rvMakerSlug ? $rvTags[] = "#{$rvMakerSlug}" : null,
                                            };

                                            $rvTags[] = '#' . str_replace(' ', '', $model->name);

                                            $rvDisplacement = $model->displacement;
                                            if ($rvDisplacement) {
                                                if ($rvDisplacement <= 50) {
                                                    $rvTags[] = '#原付';
                                                } elseif ($rvDisplacement <= 125) {
                                                    $rvTags[] = '#125cc';
                                                } elseif ($rvDisplacement <= 250) {
                                                    $rvTags[] = '#250cc';
                                                } elseif ($rvDisplacement <= 400) {
                                                    $rvTags[] = '#400cc';
                                                } else {
                                                    $rvTags[] = '#大型バイク';
                                                }
                                            }

                                            $rvHashLine = implode(' ', array_unique($rvTags));
                                            $rvShareText = $model->name . 'のレビュー「' . $review->title . '」by ' . $review->nickname . ' ' . str_repeat('★', $review->rating) . str_repeat('☆', 5 - $review->rating) . "\n" . $rvHashLine;

                                            $rvShareUrl = ($model->manufacturer?->slug && $model->slug)
                                                ? route('bikes.model_review_single', ['mfrSlug' => $model->manufacturer->slug, 'modelSlug' => $model->slug, 'reviewId' => $review->id])
                                                : route('bikes.model_review_single_by_id', ['modelId' => $model->id, 'reviewId' => $review->id]);
                                        @endphp
                                        <a href="https://twitter.com/intent/tweet?text={{ urlencode($rvShareText) }}&url={{ urlencode($rvShareUrl) }}"
                                           target="_blank" rel="noopener noreferrer"
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-900 text-white text-xs font-bold rounded-full hover:bg-gray-700 transition">
                                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                            シェア
                                        </a>
                                        @endif
                                    </div>
                                    @include('bikes.partials._qa_report', ['type' => 'review', 'id' => $review->id])
                                </div>
                            @empty
                                {{-- 0件のみ: 一番乗り歓迎トーン（1件でもあれば通常表示） --}}
                                <div class="text-center py-8 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                                    <p class="text-sm text-gray-600 font-bold mb-2">まだこの車種のレビューはありません。</p>
                                    <p class="text-xs text-gray-500 leading-relaxed">最初のオーナーレビューは目立ちます ── 乗ってみた感想を一言どうぞ。</p>
                                </div>
                            @endforelse
                        </div>

                        {{-- 投稿直後: 再読込後に自分の投稿（新着最上部）へスクロール＋一時ハイライト（即反映の実感） --}}
                        @if(session('review_posted'))
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                var list = document.getElementById('model-review-list');
                                var first = list && list.firstElementChild;
                                if (!first) return;
                                first.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                first.style.transition = 'background-color 0.6s ease';
                                first.style.backgroundColor = '#fefce8'; // yellow-50
                                first.style.borderRadius = '1rem';
                                setTimeout(function () { first.style.backgroundColor = ''; }, 2800);
                            });
                        </script>
                        @endif

                        {{-- レビュー一覧ハブへの導線（cluster双方向化） --}}
                        <div class="mt-4 text-center">
                            <a href="{{ route('bikes.reviews_index') }}" class="inline-flex items-center gap-1.5 text-sm font-bold text-blue-600 hover:text-blue-800 hover:underline">
                                他の車種のレビューを見る
                                <i data-lucide="arrow-right" class="w-4 h-4"></i>
                            </a>
                        </div>

                        {{-- 投稿フォーム --}}
                        <div id="review-form" class="bg-gray-50 rounded-2xl p-6 border border-gray-100">
                            <h3 class="text-base font-black text-gray-900 mb-4">レビューを投稿する</h3>
                            
                            @if(session('success'))
                                <div class="mb-4 p-4 bg-green-100 text-green-700 text-sm font-bold rounded-xl border border-green-200">
                                    {{ session('success') }}
                                </div>
                                {{-- レビュー投稿後の導線: ガレージへ（bike_model_id prefill・押し売りしない1枚） --}}
                                <div class="mb-4 p-4 bg-blue-50 border border-blue-100 rounded-xl flex items-center justify-between gap-3">
                                    <p class="text-xs font-bold text-gray-700">この{{ $model->name }}をガレージに登録して、燃費・維持費を記録しませんか？</p>
                                    <a href="{{ route('mybikes.index', ['bike_model_id' => $model->id]) }}" class="shrink-0 inline-flex items-center gap-1.5 text-xs font-black text-white bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg transition-colors">
                                        <i data-lucide="bike" class="w-3.5 h-3.5"></i>@auth 記録する @else ログインして記録 @endauth
                                    </a>
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
                                {{-- ハニーポット（人間には非表示・ボット除け） --}}
                                <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden" style="display:none">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    @include('bikes.partials.review_author_field', [
                                        'inputClass' => 'w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none',
                                        'labelClass' => 'block text-xs font-bold text-gray-500 mb-1',
                                    ])
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
                                {{-- 項目別評価 --}}
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
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
                                        <label class="block text-xs font-bold text-gray-500 mb-1">{{ $fieldLabel }} <span class="text-gray-300">（任意）</span></label>
                                        <input type="hidden" name="{{ $fieldName }}" id="{{ $fieldName }}-value" value="{{ old($fieldName, '') }}">
                                        <div class="flex items-center gap-1">
                                            @for($s = 1; $s <= 5; $s++)
                                            <button type="button" class="detail-star-btn p-0.5 transition-transform hover:scale-110 focus:outline-none {{ $s <= old($fieldName, 0) ? 'text-yellow-400' : 'text-gray-200' }}" data-field="{{ $fieldName }}" data-val="{{ $s }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 transition-colors {{ $s <= old($fieldName, 0) ? 'fill-current' : '' }}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                                </svg>
                                            </button>
                                            @endfor
                                        </div>
                                    </div>
                                    @endforeach
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

                    {{-- この車種のオーナー（実オーナーの公開ガレージ＝維持費・燃費・走行距離が購入判断に効く） --}}
                    @if(isset($owners) && $owners->count() > 0)
                    <div class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100">
                        <h2 class="text-xl font-black text-gray-900 mb-2 flex items-center gap-2">
                            <span class="bg-pink-100 text-pink-600 p-2 rounded-lg">
                                <i data-lucide="users" class="w-5 h-5"></i>
                            </span>
                            {{ $model->name }} のオーナーのガレージ
                            <span class="text-sm text-gray-400 font-bold">({{ number_format($ownersTotal ?? $owners->count()) }}人)</span>
                        </h2>
                        <p class="text-xs text-gray-500 mb-6">実オーナーの走行距離・維持費・燃費を見て購入の参考に。</p>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            @foreach($owners as $owner)
                            {{-- カード→公開ガレージ。オーナー名は別リンク→プロフィール（aの入れ子回避） --}}
                            <div class="group bg-gray-50 rounded-xl p-4 border border-gray-100 hover:border-pink-300 hover:shadow-md transition-all">
                                <a href="{{ route('garage.public.show', $owner['id']) }}" class="block">
                                    <div class="aspect-[4/3] rounded-lg bg-gray-200 overflow-hidden mb-3">
                                        @if($owner['image'])
                                            <img src="{{ $owner['image'] }}" alt="{{ $owner['bike_name'] }}"
                                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                                                 loading="lazy" decoding="async">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-gray-400">
                                                <i data-lucide="bike" class="w-8 h-8"></i>
                                            </div>
                                        @endif
                                    </div>
                                </a>
                                {{-- 公開ハンドルのみ（本名は出さない）→プロフィール --}}
                                @if(!empty($owner['profile_token']))
                                    <a href="{{ route('riders.profile', $owner['profile_token']) }}" class="block text-xs font-bold text-gray-500 hover:text-pink-600 transition-colors truncate">{{ $owner['handle'] }}</a>
                                @else
                                    <p class="text-xs font-bold text-gray-500 truncate">{{ $owner['handle'] }}</p>
                                @endif
                                <a href="{{ route('garage.public.show', $owner['id']) }}" class="block">
                                    <p class="text-sm font-black text-gray-800 truncate">{{ $owner['bike_name'] }}@if($owner['model_year'])<span class="text-[10px] text-gray-400 font-bold ml-1">{{ $owner['model_year'] }}年式</span>@endif</p>
                                    <div class="mt-2 pt-2 border-t border-gray-100 grid grid-cols-3 gap-1 text-center">
                                        <div>
                                            <div class="text-[9px] text-gray-400 font-bold">走行</div>
                                            <div class="text-[11px] font-black text-gray-700 leading-tight">{{ number_format($owner['odometer']) }}<span class="text-[8px] font-bold text-gray-400">km</span></div>
                                        </div>
                                        <div>
                                            <div class="text-[9px] text-gray-400 font-bold">維持費</div>
                                            <div class="text-[11px] font-black text-emerald-600 leading-tight">¥{{ number_format($owner['total_cost']) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-[9px] text-gray-400 font-bold">燃費</div>
                                            <div class="text-[11px] font-black text-blue-600 leading-tight">{{ $owner['avg_efficiency'] !== null ? $owner['avg_efficiency'] : '—' }}<span class="text-[8px] font-bold text-gray-400">km/L</span></div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            @endforeach
                        </div>

                        <div class="mt-6 text-center">
                            <a href="{{ route('garage.public.index') }}" class="text-xs font-bold text-pink-600 hover:underline">
                                みんなの愛車をもっと見る →
                            </a>
                        </div>
                    </div>
                    @endif

                    {{-- オーナーがいなくても登録を促すCTA（cold-start） --}}
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

                    {{-- ===== 統合スレッド型クチコミ・相談（質問には MotoHub必答が即時に付く＝過疎に見せない） ===== --}}
                    <div id="threads" class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100 scroll-mt-20 mb-8">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="bg-blue-100 text-blue-600 p-2 rounded-lg"><i data-lucide="messages-square" class="w-5 h-5"></i></span>
                            <h2 class="text-xl font-black text-gray-900">{{ $model->name }} のクチコミ・相談</h2>
                        </div>
                        <p class="text-xs text-gray-500 mb-5">質問・体験談・カスタム・注意喚起をまとめて。質問には<span class="font-bold text-blue-600">MotoHubがすぐに回答</span>します（ログイン不要）。</p>

                        @if(session('ugc_success') === 'thread')
                        <div class="mb-4 text-sm font-bold text-green-700 bg-green-50 border border-green-200 rounded-xl px-4 py-3">投稿しました。MotoHubの回答が付きます。</div>
                        @endif

                        @if(!empty($modelThreads) && $modelThreads->count() > 0)
                        @php $tUrl = fn ($t) => route('bikes.thread', ['mfrSlug' => $model->manufacturer->slug ?? $model->manufacturer_id, 'modelSlug' => $model->slug ?? $model->id, 'id' => $t->id]); @endphp
                        <div class="space-y-2 mb-6">
                            @foreach($modelThreads as $t)
                            <a href="{{ $tUrl($t) }}" class="flex items-center justify-between gap-3 p-3 rounded-xl border border-gray-100 hover:border-blue-200 hover:bg-blue-50 transition-colors">
                                <span class="text-sm font-bold text-gray-800 truncate">
                                    <span class="text-[10px] font-black px-1.5 py-0.5 rounded mr-1 {{ $t->type === 'question' ? 'text-blue-600 bg-blue-50' : 'text-emerald-700 bg-emerald-50' }}">{{ $t->type_badge_label }}</span>{{ $t->display_title }}
                                </span>
                                {{-- casual は「返信0件」の過疎表示を出さず、名前＋投稿時刻の軽い表現にする --}}
                                <span class="shrink-0 text-xs font-black text-gray-400">
                                    @if($t->type === 'question')返信{{ $t->published_replies_count }}件@else{{ \Illuminate\Support\Str::limit($t->display_name, 8) }}・{{ $t->created_at->diffForHumans() }}@endif
                                </span>
                            </a>
                            @endforeach
                        </div>
                        @else
                        <div class="text-center py-6 bg-gray-50 rounded-2xl border border-dashed border-gray-200 mb-6">
                            <p class="text-sm text-gray-600 font-bold mb-1">この車種のクチコミ・相談はまだありません。</p>
                            <p class="text-xs text-gray-500">「通勤に最高」などひとことでもOK。質問にはMotoHubがすぐ回答します。</p>
                        </div>
                        @endif

                        {{-- 投稿フォーム（type選択・質問なら必答が走る）。開放フラグで制御。 --}}
                        @if(config('ugc.thread_create_open', true))
                        <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100" x-data="{ open: {{ ($modelThreads->count() ?? 0) === 0 ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open" class="w-full flex items-center justify-between text-left">
                                <span class="text-sm font-black text-gray-900 inline-flex items-center gap-1.5"><i data-lucide="message-circle" class="w-4 h-4 text-blue-600"></i>この{{ $model->name }}にひとこと・質問する</span>
                                <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 transition-transform" :class="open && 'rotate-180'"></i>
                            </button>
                            <p class="text-[11px] text-gray-500 mt-1">タイトル無し・本文だけでOK。「通勤に最高」など気軽にどうぞ。</p>
                            @error('title')<p class="text-xs font-bold text-red-500 mt-2">{{ $message }}</p>@enderror
                            @error('body')<p class="text-xs font-bold text-red-500 mt-2">{{ $message }}</p>@enderror
                            <form x-show="open" x-cloak method="POST" action="{{ route('bikes.thread.store', $model->id) }}" class="mt-3 space-y-2" data-qa-push-form>
                                @csrf
                                <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden" style="display:none">
                                <select name="type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                    <option value="chat">ひとこと・相談（気軽に）</option>
                                    <option value="question">質問（MotoHubがすぐ回答）</option>
                                    <option value="custom">カスタム記録</option>
                                    <option value="maintenance">整備・トラブル</option>
                                </select>
                                <textarea name="body" rows="2" maxlength="2000" placeholder="このカブ通勤に最高！ など感想・ひとこと（質問ならここに詳しく）"
                                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">{{ old('body') }}</textarea>
                                <input type="text" name="title" maxlength="120" value="{{ old('title') }}"
                                       placeholder="タイトル（任意／質問なら推奨）"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                <div class="flex gap-2">
                                    @guest
                                    <input type="text" name="nickname" maxlength="50" value="{{ old('nickname') }}" placeholder="お名前（任意）" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                    @endguest
                                    <button type="submit" class="shrink-0 bg-blue-600 hover:bg-blue-700 text-white font-black text-sm px-6 py-2 rounded-lg transition ml-auto">投稿する</button>
                                </div>
                                {{-- 「返信が付いたら通知」オプトイン（人間の返信で通知・MotoHub必答では通知しない）。JS対応環境でのみ表示。 --}}
                                <label data-qa-push-optin class="hidden items-center gap-2 text-xs font-bold text-gray-600 cursor-pointer select-none">
                                    <input type="checkbox" name="qa_notify_optin" value="1" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span>返信が付いたら、このブラウザに通知でお知らせ（無料・登録不要）</span>
                                </label>
                                <input type="hidden" name="push_endpoint" value="">
                                <input type="hidden" name="push_p256dh" value="">
                                <input type="hidden" name="push_auth" value="">
                            </form>
                        </div>
                        @endif
                    </div>

                    </div>{{-- /tab-panel-community --}}

                    {{-- ===== タブ外: 関連コンテンツ（全タブ共通表示） ===== --}}
                    <div class="space-y-8 mt-8">

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

                    {{-- カテゴリLP導線 --}}
                    @php
                        $ccSlugMap = match(true) {
                            ($model->displacement ?? 0) <= 50 => '50',
                            ($model->displacement ?? 0) <= 125 => '125',
                            ($model->displacement ?? 0) <= 250 => '250',
                            ($model->displacement ?? 0) <= 400 => '400',
                            ($model->displacement ?? 0) <= 750 => '750',
                            default => 'over750',
                        };
                        $ccLabelMap = [
                            '50' => '50cc以下', '125' => '51〜125cc', '250' => '126〜250cc',
                            '400' => '251〜400cc', '750' => '401〜750cc', 'over750' => '751cc以上',
                        ];
                        $typeLpMap = [
                            1 => ['slug' => 'mini', 'label' => 'ミニバイク'],
                            2 => ['slug' => 'scooter', 'label' => 'スクーター'],
                            3 => ['slug' => 'scooter', 'label' => 'スクーター'],
                            4 => ['slug' => 'naked', 'label' => 'ネイキッド'],
                            6 => ['slug' => 'tourer', 'label' => 'ツアラー'],
                            8 => ['slug' => 'sport', 'label' => 'スポーツ/レプリカ'],
                            10 => ['slug' => 'american', 'label' => 'アメリカン'],
                            11 => ['slug' => 'offroad', 'label' => 'オフロード'],
                            14 => ['slug' => 'adventure', 'label' => 'アドベンチャー'],
                            20 => ['slug' => 'street-fighter', 'label' => 'ストリートファイター'],
                            21 => ['slug' => 'classic', 'label' => 'クラシック'],
                        ];
                        $typeInfo = $typeLpMap[$model->category_id] ?? null;
                    @endphp
                    <div class="bg-white rounded-2xl shadow-sm p-5 border border-gray-100 flex flex-col sm:flex-row gap-3">
                        @if($model->displacement)
                        <a href="{{ route('bikes.category_cc', ['slug' => $ccSlugMap]) }}"
                           class="flex-1 flex items-center gap-3 px-4 py-3 rounded-xl bg-blue-50 border border-blue-100 hover:bg-blue-100 hover:border-blue-300 transition-colors group">
                            <i data-lucide="gauge" class="w-5 h-5 text-blue-500"></i>
                            <span class="text-sm font-bold text-gray-700 group-hover:text-blue-700">{{ $ccLabelMap[$ccSlugMap] }}クラスのバイク一覧を見る</span>
                            <i data-lucide="arrow-right" class="w-4 h-4 text-blue-400 ml-auto"></i>
                        </a>
                        @endif
                        @if($typeInfo)
                        <a href="{{ route('bikes.category_type', ['slug' => $typeInfo['slug']]) }}"
                           class="flex-1 flex items-center gap-3 px-4 py-3 rounded-xl bg-purple-50 border border-purple-100 hover:bg-purple-100 hover:border-purple-300 transition-colors group">
                            <i data-lucide="layers" class="w-5 h-5 text-purple-500"></i>
                            <span class="text-sm font-bold text-gray-700 group-hover:text-purple-700">{{ $typeInfo['label'] }}のバイク一覧を見る</span>
                            <i data-lucide="arrow-right" class="w-4 h-4 text-purple-400 ml-auto"></i>
                        </a>
                        @endif
                    </div>

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

                    {{-- 「よく比較される車種」は overview タブの「{{ $model->name }} の比較」($relatedCompares)と
                         同一SeoCompareペア・同一URLの重複だったため統合・削除（比較ハブ含む内部リンクは①側に集約済み）。 --}}

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

                    </div>{{-- /タブ外コンテンツ --}}

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
