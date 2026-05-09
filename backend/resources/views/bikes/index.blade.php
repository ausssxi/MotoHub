<x-layout>
    <x-slot:title>MotoHub - 中古・新車バイク一括検索</x-slot:title>
    <x-slot:metaDescription>MotoHubは全国{{ number_format($totalListings) }}台以上の中古・新車バイクを一括検索できるバイクポータルサイト。価格比較、車種判定AI、39,000件のバイク駐車場マップなど、バイク選びに必要な全てが揃います。</x-slot:metaDescription>

    <x-slot:styles>
        <link rel="preload" as="image" href="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=2070&auto=format&fit=crop" fetchpriority="high">
    </x-slot:styles>

    <x-slot:scripts>
        <script src="{{ asset('js/search/suggest.js') }}?v={{ filemtime(public_path('js/search/suggest.js')) }}"></script>

        {{-- 閲覧履歴の描画（manager.js は layout.blade.php で読み込み済み） --}}
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (window.HistoryManager) {
                    const bodyLoggedIn = document.body.dataset.loggedIn === 'true';
                    const metaLoggedIn = document.querySelector('meta[name="auth-check"]')?.content === 'true';
                    const isLoggedIn = bodyLoggedIn || metaLoggedIn;

                    HistoryManager.init(isLoggedIn).then(() => {
                        HistoryManager.render('top-history-widget').then(() => {
                            const widget = document.getElementById('top-history-widget');
                            if (widget && widget.children.length > 0) {
                                document.getElementById('top-history-section').classList.remove('hidden');
                            }
                        });
                    });
                }
            });
        </script>

        {{-- スティッキータブナビゲーション --}}
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var tabBar = document.getElementById('top-tab-bar');
                if (!tabBar) return;

                var tabs = tabBar.querySelectorAll('[data-tab-target]');
                var sectionIds = ['section-search', 'section-market', 'section-community'];
                var sections = sectionIds.map(function(id) { return document.getElementById(id); }).filter(Boolean);
                if (sections.length === 0) return;

                var navHeight = 64;
                var totalOffset = navHeight + tabBar.offsetHeight;

                // クリックでスムーススクロール
                tabs.forEach(function(tab) {
                    tab.addEventListener('click', function() {
                        var target = document.getElementById(this.dataset.tabTarget);
                        if (target) {
                            var top = target.getBoundingClientRect().top + window.pageYOffset - totalOffset - 8;
                            window.scrollTo({ top: top, behavior: 'smooth' });
                        }
                    });
                });

                // スクロールでアクティブタブ更新
                function updateActiveTab() {
                    var currentId = sectionIds[0];
                    for (var i = 0; i < sections.length; i++) {
                        if (sections[i].getBoundingClientRect().top <= totalOffset + 60) {
                            currentId = sections[i].id;
                        }
                    }
                    tabs.forEach(function(tab) {
                        var isActive = tab.dataset.tabTarget === currentId;
                        tab.classList.toggle('border-blue-600', isActive);
                        tab.classList.toggle('text-blue-600', isActive);
                        tab.classList.toggle('border-transparent', !isActive);
                        tab.classList.toggle('text-gray-500', !isActive);
                    });
                }

                window.addEventListener('scroll', updateActiveTab, { passive: true });
                updateActiveTab();
            });
        </script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="false" />
    </x-slot:navigation>

    {{-- メインビジュアル & 検索ボックス（縮小版） --}}
    <div class="relative bg-black h-[320px] sm:h-[400px] flex items-center justify-center overflow-visible py-6 sm:py-8">
        <div class="absolute inset-0 z-0 overflow-hidden">
             <img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=2070&auto=format&fit=crop"
                  alt="Motorcycle Background"
                  class="w-full h-full object-cover opacity-40"
                  width="2070" height="1380"
                  fetchpriority="high" decoding="async">
        </div>

        <div class="relative z-10 w-full max-w-4xl px-4 text-center" id="search-container">
            <h1 class="text-3xl sm:text-5xl font-black text-white mb-4 tracking-tight leading-tight">
                掲載台数<span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-cyan-300">&nbsp;No.1！</span>
            </h1>
            <p class="text-gray-300 text-xs sm:text-sm font-bold mb-6 tracking-widest">
                日本最大級のバイク検索・比較プラットフォーム
            </p>

            <form action="{{ route('bikes.search') }}" method="GET" class="relative max-w-2xl mx-auto" id="search-form" autocomplete="off">
                <div class="relative group">
                    <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
                        <i data-lucide="search" class="w-5 h-5 text-gray-400 group-focus-within:text-blue-500 transition-colors"></i>
                    </div>
                    <input type="text" name="keyword" id="search-input"
                        class="w-full h-14 pl-12 pr-4 rounded-full border-none focus:ring-4 focus:ring-blue-500/30 text-base font-bold shadow-2xl placeholder:text-gray-400 transition-all"
                        placeholder="車種名、メーカー名、キーワードを入力..." autocomplete="off">
                    <button type="submit" class="absolute right-2 top-2 h-10 px-6 bg-black text-white rounded-full text-xs font-black hover:bg-gray-800 transition-all flex items-center gap-2">
                        検索
                    </button>
                </div>

                <div id="suggest-results" class="absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden hidden z-[110] text-left">
                    <div id="suggest-list" class="py-2 max-h-[400px] overflow-y-auto"></div>
                </div>
            </form>

            {{-- バイク診断へのクイック導線バナー --}}
            <div class="mt-6 flex justify-center">
                <a href="/shindan" class="group relative inline-flex items-center gap-3 px-6 py-3 bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl hover:bg-white/20 transition-all shadow-xl">
                    <div class="flex -space-x-2">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white border-2 border-white/20 shadow-lg">
                            <i data-lucide="sparkles" class="w-4 h-4 fill-current"></i>
                        </div>
                    </div>
                    <div class="text-left">
                        <p class="text-[10px] font-black text-blue-300 uppercase tracking-tighter leading-none mb-1">AI Style Match</p>
                        <p class="text-sm font-black text-white leading-none">あなたにぴったりの1台を診断する <i data-lucide="chevron-right" class="w-4 h-4 inline-block group-hover:translate-x-1 transition-transform"></i></p>
                    </div>
                </a>
            </div>
        </div>
    </div>

    {{-- ライブ統計バー --}}
    <div class="bg-white border-b border-gray-100 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 py-3 text-center" style="display:grid;grid-template-columns:repeat(5,1fr);border-collapse:collapse">
            <a href="{{ route('bikes.search') }}" class="px-2 block hover:bg-gray-50 rounded-lg transition-colors">
                <span class="text-[10px] font-bold text-gray-400 block">掲載</span>
                <span class="text-sm font-black text-gray-900 tabular-nums">{{ number_format($totalListings) }}<span class="text-[10px] font-bold text-gray-400 ml-0.5">台</span></span>
            </a>
            <a href="{{ route('bikes.price_drops') }}" class="px-2 block hover:bg-gray-50 rounded-lg transition-colors" style="border-left:1px solid #e5e7eb">
                <span class="text-[10px] font-bold text-gray-400 block">本日値下げ</span>
                <span class="text-sm font-black text-red-600 tabular-nums">{{ number_format($priceDropCount) }}<span class="text-[10px] font-bold text-gray-400 ml-0.5">台</span></span>
            </a>
            <a href="{{ route('bikes.new_arrivals') }}" class="px-2 block hover:bg-gray-50 rounded-lg transition-colors" style="border-left:1px solid #e5e7eb">
                <span class="text-[10px] font-bold text-gray-400 block">新着</span>
                <span class="text-sm font-black text-green-600 tabular-nums">{{ number_format($newListingsCount) }}<span class="text-[10px] font-bold text-gray-400 ml-0.5">台</span></span>
            </a>
            <a href="{{ route('bikes.bargains') }}" class="px-2 block hover:bg-gray-50 rounded-lg transition-colors" style="border-left:1px solid #e5e7eb">
                <span class="text-[10px] font-bold text-gray-400 block">お買い得</span>
                <span class="text-sm font-black text-purple-600 tabular-nums">{{ number_format($bargainsCount) }}<span class="text-[10px] font-bold text-gray-400 ml-0.5">台</span></span>
            </a>
            <a href="{{ route('ranking.index') }}" class="px-2 block hover:bg-gray-50 rounded-lg transition-colors" style="border-left:1px solid #e5e7eb">
                <span class="text-[10px] font-bold text-gray-400 block">本日販売</span>
                <span class="text-sm font-black text-orange-600 tabular-nums">{{ number_format($todaySoldCount) }}<span class="text-[10px] font-bold text-gray-400 ml-0.5">台</span></span>
            </a>
        </div>
    </div>

    {{-- スティッキータブナビゲーション --}}
    <div id="top-tab-bar" class="sticky top-16 z-40 bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center justify-center">
                <button type="button" data-tab-target="section-search"
                    class="flex-1 sm:flex-none px-6 py-3 text-sm font-bold text-gray-500 border-b-2 border-transparent hover:text-blue-600 transition-all text-center whitespace-nowrap">
                    🔍 探す
                </button>
                <button type="button" data-tab-target="section-market"
                    class="flex-1 sm:flex-none px-6 py-3 text-sm font-bold text-gray-500 border-b-2 border-transparent hover:text-blue-600 transition-all text-center whitespace-nowrap">
                    📊 相場
                </button>
                <button type="button" data-tab-target="section-community"
                    class="flex-1 sm:flex-none px-6 py-3 text-sm font-bold text-gray-500 border-b-2 border-transparent hover:text-blue-600 transition-all text-center whitespace-nowrap">
                    👥 コミュニティ
                </button>
            </div>
        </div>
    </div>


    {{-- ======================= --}}
    {{-- 🔍 探す セクション      --}}
    {{-- ======================= --}}


    {{-- お得な車両カルーセル（人気車種）--}}
    <div id="section-search" class="bg-gray-50 py-10 sm:py-16">
        <div class="max-w-7xl mx-auto px-4">
            {{-- 🔍探す: 人気車種 --}}
            <section class="mb-20">
                <div class="flex items-end justify-between mb-8 px-2">
                    <div>
                        <h2 class="text-2xl font-black text-black tracking-tighter mb-1">
                            人気車種
                        </h2>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Popular Models</p>
                    </div>
                    <a href="{{ route('bikes.models') }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1 group">
                        すべて見る <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                    </a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($popularBikes as $bike)
                        <a href="{{ $bike->seo_url }}"
                           class="group flex items-center p-3 bg-white rounded-xl border border-gray-100 shadow-sm hover:border-blue-300 hover:shadow-md transition-all duration-300">

                            <div class="w-14 h-14 rounded-lg bg-gray-50 overflow-hidden flex-shrink-0 border border-gray-50 relative">
                                @if($bike->image_url)
                                    <img src="{{ $bike->image_url }}" alt="{{ $bike->name }}"
                                         class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-500"
                                         loading="lazy" decoding="async"
                                         onerror="handleImageError(this)">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-300">
                                        <i data-lucide="bike" class="w-6 h-6"></i>
                                    </div>
                                @endif
                            </div>

                            <div class="ml-3 flex-1 min-w-0">
                                <p class="text-[9px] font-bold text-gray-400 mb-0.5">{{ $bike->manufacturer?->name }}</p>
                                <h3 class="text-sm font-black text-gray-800 leading-tight truncate group-hover:text-blue-600 transition-colors">
                                    {{ $bike->name }}
                                </h3>
                                <div class="mt-1">
                                    <span class="inline-flex items-center text-[9px] font-bold bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded">
                                        {{ number_format($bike->listings_count) }}台
                                    </span>
                                </div>
                            </div>

                            <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300 group-hover:text-blue-400 group-hover:translate-x-1 transition-all"></i>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
    </div>

    {{-- 🏆 売れ筋ランキング TOP5 --}}
    @if($rankingTop5->isNotEmpty())
    <div class="bg-gray-50 pb-10 sm:pb-16">
        <div class="max-w-7xl mx-auto px-4">
            <section>
                <div class="flex items-end justify-between mb-8 px-2">
                    <div>
                        <h2 class="text-2xl font-black text-black tracking-tighter mb-1">
                            売れ筋ランキング
                        </h2>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Best Selling</p>
                    </div>
                    <a href="{{ route('ranking.index') }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1 group">
                        ランキングをもっと見る <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                    </a>
                </div>

                <div class="flex gap-3 overflow-x-auto pb-2 scrollbar-hide snap-x snap-mandatory">
                    @foreach($rankingTop5 as $i => $rk)
                    @php $rank = $i + 1; $medal = match($rank) { 1 => "\u{1F947}", 2 => "\u{1F948}", 3 => "\u{1F949}", default => null }; @endphp
                    <a href="{{ route('ranking.model_stats', $rk['bike_model_id']) }}"
                       class="snap-start shrink-0 w-40 sm:w-44 group bg-white rounded-2xl border border-gray-100 shadow-sm hover:border-blue-300 hover:shadow-lg transition-all duration-300 overflow-hidden flex flex-col">
                        <div class="aspect-[4/3] bg-gray-100 overflow-hidden relative">
                            @if($rk['image_url'])
                            <img src="{{ $rk['image_url'] }}" alt="{{ $rk['name'] }}"
                                 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                 loading="lazy" decoding="async" onerror="this.style.display='none'">
                            @endif
                            <span class="absolute top-2 left-2 bg-black/70 text-white text-xs font-black px-2 py-1 rounded-lg">
                                @if($medal) {{ $medal }} @else {{ $rank }}位 @endif
                            </span>
                        </div>
                        <div class="p-3 flex-1 flex flex-col">
                            <p class="text-[9px] font-bold text-gray-400 mb-0.5">{{ $rk['manufacturer'] }}</p>
                            <h3 class="text-sm font-black text-gray-800 leading-tight truncate group-hover:text-blue-600 transition-colors mb-1">
                                {{ $rk['name'] }}
                            </h3>
                            <p class="text-xs font-black text-orange-600 mt-auto">{{ number_format($rk['sold_count']) }}台<span class="text-[10px] font-bold text-gray-400 ml-0.5">販売</span></p>
                        </div>
                    </a>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
    @endif

    {{-- 🎮 バイクゲーム --}}
    <div class="bg-gray-50 pb-10 sm:pb-16">
        <div class="max-w-7xl mx-auto px-4">
            <section>
                <div class="flex items-end justify-between mb-8 px-2">
                    <div>
                        <h2 class="text-2xl font-black text-black tracking-tighter mb-1">
                            MotoHubのバイクゲーム
                        </h2>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Bike Games</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    {{-- バイクガレージパズル --}}
                    <a href="{{ route('games.subaracity') }}"
                       class="group relative overflow-hidden rounded-2xl border border-gray-100 shadow-sm hover:shadow-lg hover:border-orange-300 transition-all duration-300">
                        <div class="overflow-hidden">
                            <img src="{{ asset('images/subaracity/ogp.png') }}" alt="バイクガレージパズル"
                                 class="w-full h-48 object-cover object-top rounded-t-xl group-hover:scale-105 transition-transform duration-500"
                                 loading="lazy" decoding="async">
                        </div>
                        <div class="p-4 bg-white">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-lg">{!! '&#x1F3CD;' !!}</span>
                                <h3 class="text-sm font-black text-gray-800 group-hover:text-orange-600 transition-colors">バイクガレージパズル</h3>
                            </div>
                            <p class="text-xs text-gray-500 leading-relaxed">同じ色のバイクを合体させてガレージを育てよう</p>
                        </div>
                    </a>

                    {{-- わらしべ長者 --}}
                    <a href="{{ route('warashibe') }}"
                       class="group relative overflow-hidden rounded-2xl border border-gray-100 shadow-sm hover:shadow-lg hover:border-amber-300 transition-all duration-300">
                        <div class="overflow-hidden">
                            <img src="{{ asset('images/warashibe/title_screen.png') }}" alt="バイクわらしべ長者"
                                 class="w-full h-48 object-cover object-top rounded-t-xl group-hover:scale-105 transition-transform duration-500"
                                 loading="lazy" decoding="async">
                        </div>
                        <div class="p-4 bg-white">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-lg">🔄</span>
                                <h3 class="text-sm font-black text-gray-800 group-hover:text-amber-600 transition-colors">バイクわらしべ長者</h3>
                            </div>
                            <p class="text-xs text-gray-500 leading-relaxed">カブ50からスタート！ライダーとバイクを交換してハヤブサを目指す交換パズルゲーム</p>
                        </div>
                    </a>

                    {{-- バイク4択クイズ --}}
                    <a href="{{ route('quiz') }}"
                       class="group relative overflow-hidden rounded-2xl border border-gray-100 shadow-sm hover:shadow-lg hover:border-blue-300 transition-all duration-300">
                        <div class="h-48 bg-gray-900 rounded-t-xl overflow-hidden flex items-start justify-center">
                            <img src="{{ asset('images/quiz/title_screen.png') }}" alt="バイク4択クイズ"
                                 class="w-auto h-full object-contain"
                                 loading="lazy" decoding="async">
                        </div>
                        <div class="p-4 bg-white">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-lg">❓</span>
                                <h3 class="text-sm font-black text-gray-800 group-hover:text-blue-600 transition-colors">バイク4択クイズ</h3>
                            </div>
                            <p class="text-xs text-gray-500 leading-relaxed">実データを使ったバイククイズ！中古価格・売れ筋・売却スピードを当てよう</p>
                        </div>
                    </a>

                    {{-- バイク2048パズル --}}
                    <a href="{{ route('puzzle') }}"
                       class="group relative overflow-hidden rounded-2xl border border-gray-100 shadow-sm hover:shadow-lg hover:border-orange-300 transition-all duration-300">
                        <div class="overflow-hidden">
                            <img src="{{ asset('images/puzzle/title_screen.png') }}" alt="バイク2048パズル"
                                 class="w-full h-48 object-cover object-top rounded-t-xl group-hover:scale-105 transition-transform duration-500"
                                 loading="lazy" decoding="async">
                        </div>
                        <div class="p-4 bg-white">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-lg">🧩</span>
                                <h3 class="text-sm font-black text-gray-800 group-hover:text-orange-600 transition-colors">バイク2048パズル</h3>
                            </div>
                            <p class="text-xs text-gray-500 leading-relaxed">カブ50から合体！目指せゴールドウイング</p>
                        </div>
                    </a>
                </div>
            </section>
        </div>
    </div>

    {{-- 最近見た車両（パーソナルコンテンツ / タブセクションの上に常時表示） --}}
    <section id="top-history-section" class="bg-gray-50 hidden">
        <div class="max-w-7xl mx-auto px-4 pt-10 sm:pt-16 pb-6">
            <div class="flex items-end justify-between mb-6 px-2">
                <div>
                    <h2 class="text-2xl font-black text-black tracking-tighter mb-1 flex items-center gap-2">
                        <i data-lucide="clock" class="w-6 h-6 text-gray-400"></i>
                        最近見た車両
                    </h2>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Recently Viewed</p>
                </div>
            </div>
            <div id="top-history-widget" class="flex gap-4 overflow-x-auto pb-4 scrollbar-hide snap-x snap-mandatory px-2 -mx-2 sm:mx-0 sm:px-0">
            </div>
        </div>
    </section>

    {{-- トレンドタグ & メーカーリンク --}}
    <div class="bg-gray-50 border-b border-gray-100 py-6">
        <div class="max-w-7xl mx-auto px-4 space-y-4">
            {{-- トレンドタグ --}}
            <div class="flex flex-wrap justify-center items-center gap-2">
                <span class="text-[10px] font-black text-gray-500 uppercase tracking-widest mr-1">
                    <i data-lucide="zap" class="w-3 h-3 inline-block -mt-0.5 text-yellow-500"></i> トレンド:
                </span>
                @foreach($popularTags as $tag)
                    <a href="{{ route('bikes.search', ['tag' => $tag]) }}"
                       class="px-3 py-1.5 rounded-full bg-white hover:bg-blue-50 text-gray-700 hover:text-blue-600 text-[10px] font-bold border border-gray-200 hover:border-blue-300 transition-all shadow-sm">
                        #{{ $tag }}
                    </a>
                @endforeach
            </div>

            {{-- メーカーリンク --}}
            <div class="flex flex-wrap justify-center gap-2">
                @foreach($manufacturers as $maker)
                    <a href="{{ route('bikes.search', ['manufacturer_id' => $maker->id]) }}" class="px-3 py-1 rounded-full bg-white hover:bg-blue-50 text-gray-600 hover:text-blue-600 text-[10px] font-bold border border-gray-200 hover:border-blue-300 transition-all shadow-sm">
                        {{ $maker->name }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- メインコンテンツ --}}
    <div class="bg-gray-50 py-16 sm:py-24">
        <div class="max-w-7xl mx-auto px-4">

            {{-- 🔍探す: タイプから探す --}}
            <section class="mb-20">
                <div class="flex items-end justify-between mb-8 px-2">
                    <div>
                        <h2 class="text-2xl font-black text-black tracking-tighter mb-1">
                            タイプから探す
                        </h2>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Search by Body Type</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-3 sm:gap-4">
                    @foreach($categories as $category)
                        @if($category->display_icon_url)
                        <a href="{{ route('bikes.search', ['category_id' => $category->id]) }}"
                           class="group bg-white rounded-2xl p-4 shadow-sm border border-gray-100 hover:border-blue-300 hover:shadow-lg transition-all duration-300 flex flex-col items-center text-center h-full">

                            <div class="w-16 h-12 sm:w-20 sm:h-14 mb-3 relative flex items-center justify-center">
                                <img src="{{ $category->display_icon_url }}"
                                     alt="{{ $category->name }}"
                                     class="w-full h-full object-contain transform group-hover:scale-110 transition-transform duration-500"
                                     loading="lazy" decoding="async">
                            </div>

                            <span class="text-xs font-bold text-gray-700 group-hover:text-blue-600 transition-colors leading-tight">
                                {{ $category->name }}
                            </span>
                        </a>
                        @endif
                    @endforeach
                </div>
            </section>

            {{-- 🔍探す: 免許・排気量から探す --}}
            <section class="mb-20">
                <div class="flex items-end justify-between mb-8 px-2">
                    <div>
                        <h2 class="text-2xl font-black text-black tracking-tighter mb-1">
                            免許・排気量から探す
                        </h2>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Search by License</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach($licenses as $license)
                        <a href="{{ route('bikes.search', ['min_displacement' => $license['min_cc'], 'max_displacement' => $license['max_cc']]) }}"
                           class="group relative overflow-hidden rounded-2xl p-6 {{ $license['color'] }} transition-all duration-300 hover:shadow-lg border border-transparent hover:border-current flex flex-col items-center justify-center text-center h-32">

                            {{-- 背景の装飾アイコン --}}
                            <div class="absolute -right-4 -bottom-4 opacity-10 transform group-hover:scale-125 transition-transform duration-500">
                                <i data-lucide="{{ $license['icon'] }}" class="w-24 h-24"></i>
                            </div>

                            <div class="relative z-10">
                                <div class="mb-2 opacity-80 group-hover:scale-110 transition-transform duration-300">
                                    <i data-lucide="{{ $license['icon'] }}" class="w-8 h-8 mx-auto"></i>
                                </div>
                                <span class="text-sm font-black tracking-tight block">
                                    {{ $license['label'] }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>

            {{-- 🔍探す: 駐車場マップへの導線 --}}
            <section class="mb-20">
                <a href="{{ route('parking.index') }}" class="group relative overflow-hidden rounded-3xl p-8 sm:p-10 block shadow-lg hover:shadow-2xl transition-all duration-300" style="background: linear-gradient(to right, #16a34a, #059669);">
                    <div class="absolute -right-8 -bottom-8 opacity-10 transform group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="square-parking" class="w-48 h-48 text-white"></i>
                    </div>
                    <div class="relative z-10 flex items-center justify-between">
                        <div class="text-white">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-white/60 mb-2">Parking Map</p>
                            <h2 class="text-xl sm:text-2xl font-black mb-2">バイク駐車場マップ</h2>
                            <p class="text-xs sm:text-sm text-white/80 font-medium">全国1,000件以上の駐車場をマップで検索。料金・設備情報も掲載。</p>
                        </div>
                        <div class="hidden sm:flex items-center justify-center w-14 h-14 bg-white/20 rounded-full group-hover:bg-white/30 transition-colors shrink-0 ml-6">
                            <i data-lucide="arrow-right" class="w-6 h-6 text-white group-hover:translate-x-1 transition-transform"></i>
                        </div>
                    </div>
                </a>
            </section>

            {{-- 🔍探す: パーツ・用品カテゴリ --}}
            <section class="mb-20">
                <div class="flex items-end justify-between mb-8 px-2">
                    <div>
                        <h2 class="text-2xl font-black text-black tracking-tighter mb-1">
                            パーツ・用品を探す
                        </h2>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Parts &amp; Accessories</p>
                    </div>
                    <a href="{{ route('parts.index') }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1 group">
                        すべて見る <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                    </a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    @foreach(array_slice(config('parts-categories', []), 0, 5) as $partsCat)
                        <a href="{{ route('parts.category', $partsCat['slug']) }}"
                           class="group bg-white rounded-2xl p-4 shadow-sm border border-gray-100 hover:border-blue-300 hover:shadow-lg transition-all duration-300 text-center">
                            <div class="w-10 h-10 mx-auto mb-2 rounded-full bg-blue-50 flex items-center justify-center group-hover:bg-blue-100 transition-colors">
                                <i data-lucide="wrench" class="w-5 h-5 text-blue-500"></i>
                            </div>
                            <span class="text-xs font-bold text-gray-700 group-hover:text-blue-600 transition-colors">
                                {{ $partsCat['name'] }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>

            {{-- 🔍探す: 都道府県から探す --}}
            <section class="mb-20">
                <div class="bg-gray-900 rounded-3xl p-8 sm:p-12 text-center relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-gray-800 to-gray-900 pointer-events-none"></div>
                    <div class="absolute -top-24 -left-24 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="absolute -bottom-24 -right-24 w-64 h-64 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>

                    <div class="relative z-10">
                        <h2 class="text-2xl font-black text-white mb-2 tracking-tighter">地域から探す</h2>
                        <p class="text-gray-400 text-xs font-bold uppercase tracking-widest mb-8">Search by Area</p>

                        <div class="flex flex-wrap justify-center gap-3">
                            @foreach(['東京', '神奈川', '埼玉', '千葉', '大阪', '愛知', '福岡', '北海道'] as $pref)
                                <a href="{{ route('bikes.search', ['prefecture' => $pref]) }}"
                                   class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white text-white hover:text-black font-bold text-xs transition-all border border-white/20">
                                    {{ $pref }}
                                </a>
                            @endforeach
                        </div>

                        <div class="mt-8">
                            <a href="{{ route('bikes.prefectures') }}" class="text-gray-400 text-xs font-bold hover:text-white transition-colors inline-flex items-center justify-center gap-2">
                                <i data-lucide="map" class="w-4 h-4"></i> すべての地域を見る
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            {{-- 🌍 海外バイクバナー --}}
            @php
                $overseasStats = app(\App\Services\Bike\OverseasBikeService::class)->getIndexData()['stats'] ?? null;
            @endphp
            @if($overseasStats && $overseasStats['total_count'] > 0)
            <section class="mb-20">
                <a href="{{ route('bikes.overseas') }}" class="group relative overflow-hidden rounded-3xl p-8 sm:p-10 block shadow-lg hover:shadow-2xl transition-all duration-300" style="background: linear-gradient(135deg, #1e293b, #334155);">
                    <div class="absolute -right-8 -bottom-8 opacity-10 transform group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="globe" class="w-48 h-48 text-white"></i>
                    </div>
                    <div class="relative z-10 flex items-center justify-between">
                        <div class="text-white">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-white/60 mb-2">Imported Motorcycles</p>
                            <h2 class="text-xl sm:text-2xl font-black mb-2">海外メーカーの中古バイクを探す</h2>
                            <p class="text-xs sm:text-sm text-white/80 font-medium">{{ $overseasStats['maker_count'] }}ブランド・{{ number_format($overseasStats['total_count']) }}台掲載</p>
                        </div>
                        <div class="hidden sm:flex items-center justify-center w-14 h-14 bg-white/20 rounded-full group-hover:bg-white/30 transition-colors shrink-0 ml-6">
                            <i data-lucide="arrow-right" class="w-6 h-6 text-white group-hover:translate-x-1 transition-transform"></i>
                        </div>
                    </div>
                </a>
            </section>
            @endif

            {{-- 🕰 絶版車バナー --}}
            @php
                $discontinuedCount = \App\Models\BikeModel::where('is_discontinued', true)->count();
            @endphp
            @if($discontinuedCount > 0)
            <section class="mb-20">
                <a href="{{ route('bikes.discontinued') }}" class="group relative overflow-hidden rounded-3xl p-8 sm:p-10 block shadow-lg hover:shadow-2xl transition-all duration-300" style="background: linear-gradient(135deg, #451a1a, #78350f);">
                    <div class="absolute -right-8 -bottom-8 opacity-10 transform group-hover:scale-110 transition-transform duration-500">
                        <svg class="w-48 h-48 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/><path d="M12 2a10 10 0 0 0-7 17"/>
                        </svg>
                    </div>
                    <div class="relative z-10 flex items-center justify-between">
                        <div class="text-white">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-white/60 mb-2">Discontinued Models</p>
                            <h2 class="text-xl sm:text-2xl font-black mb-2">絶版バイク・生産終了モデルを探す</h2>
                            <p class="text-xs sm:text-sm text-white/80 font-medium">{{ number_format($discontinuedCount) }}車種・中古在庫あり</p>
                        </div>
                        <div class="hidden sm:flex items-center justify-center w-14 h-14 bg-white/20 rounded-full group-hover:bg-white/30 transition-colors shrink-0 ml-6">
                            <i data-lucide="arrow-right" class="w-6 h-6 text-white group-hover:translate-x-1 transition-transform"></i>
                        </div>
                    </div>
                </a>
            </section>
            @endif

            {{-- ======================= --}}
            {{-- 📊 相場 セクション      --}}
            {{-- ======================= --}}
            <div id="section-market">

            {{-- おすすめコンテンツ --}}
            <section class="mb-20">
                <div class="flex items-end justify-between mb-8 px-2">
                    <div>
                        <h2 class="text-2xl font-black text-black tracking-tighter mb-1">
                            おすすめコンテンツ
                        </h2>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Special Features</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {{-- 診断カード --}}
                    <a href="/shindan"
                       class="group relative overflow-hidden rounded-3xl p-6 bg-gradient-to-br from-blue-600 to-indigo-700 shadow-lg shadow-blue-500/20 hover:shadow-xl hover:shadow-blue-500/30 transition-all duration-300 flex flex-col justify-between h-32 sm:h-40 border border-white/10">
                        <div class="absolute -right-6 -bottom-6 opacity-20 transform group-hover:scale-110 group-hover:-rotate-12 transition-transform duration-500">
                            <i data-lucide="sparkles" class="w-32 h-32 text-white"></i>
                        </div>
                        <div class="relative z-10 text-white flex-1 flex flex-col justify-between">
                            <div class="flex justify-between items-start">
                                <i data-lucide="zap" class="w-6 h-6 mb-2 text-yellow-300 fill-current"></i>
                                <span class="bg-white/20 text-[8px] font-black px-2 py-1 rounded-full uppercase">New Tool</span>
                            </div>
                            <div>
                                <h3 class="text-base sm:text-lg font-black leading-tight drop-shadow-sm group-hover:-translate-y-1 transition-transform duration-300">
                                    30秒でわかる<br>あなたの相棒バイク診断
                                </h3>
                            </div>
                        </div>
                    </a>

                    @foreach($features as $feature)
                        <a href="{{ $feature['url'] }}"
                           class="group relative overflow-hidden rounded-2xl p-6 {{ $feature['color'] }} shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between h-32 sm:h-40 border border-white/20">
                            <div class="absolute -right-6 -bottom-6 opacity-20 transform group-hover:scale-110 group-hover:-rotate-12 transition-transform duration-500">
                                <i data-lucide="{{ $feature['icon'] }}" class="w-32 h-32 text-white"></i>
                            </div>
                            <div class="relative z-10 text-white flex-1 flex flex-col justify-between">
                                <i data-lucide="{{ $feature['icon'] }}" class="w-6 h-6 mb-2 opacity-80"></i>
                                <h3 class="text-sm sm:text-base font-black leading-snug drop-shadow-sm group-hover:-translate-y-1 transition-transform duration-300">
                                    {{ $feature['title'] }}
                                </h3>
                            </div>
                        </a>
                    @endforeach
                </div>

                {{-- SEO特集ページへの導線 --}}
                @if($seoFeatures->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                        @foreach($seoFeatures as $sf)
                            <a href="{{ $sf->url }}"
                               class="group relative overflow-hidden rounded-2xl p-6 bg-gray-800 {{ $sf->color ?? '' }} shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between h-32 sm:h-40 border border-white/10">
                                <div class="absolute -right-6 -bottom-6 opacity-20 transform group-hover:scale-110 group-hover:-rotate-12 transition-transform duration-500">
                                    <i data-lucide="{{ $sf->icon ?? 'bookmark' }}" class="w-32 h-32 text-white"></i>
                                </div>
                                <div class="relative z-10 text-white flex-1 flex flex-col justify-between">
                                    <i data-lucide="{{ $sf->icon ?? 'bookmark' }}" class="w-6 h-6 mb-2 opacity-80"></i>
                                    <h3 class="text-sm sm:text-base font-black leading-snug drop-shadow-sm group-hover:-translate-y-1 transition-transform duration-300">
                                        {{ $sf->title }}
                                    </h3>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-6 text-center">
                        <a href="{{ route('features.index') }}"
                           class="group inline-flex items-center gap-2 px-8 py-4 bg-gray-900 text-white font-black text-sm rounded-2xl hover:bg-gray-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                            <i data-lucide="layout-grid" class="w-5 h-5"></i>
                            すべての特集・おすすめバイクを見る
                            <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i>
                        </a>
                    </div>
                @endif
            </section>

            </div>{{-- /section-market --}}

            {{-- ======================= --}}
            {{-- 👥 コミュ セクション    --}}
            {{-- ======================= --}}
            <div id="section-community">

            {{-- 新着ユーザーレビュー --}}
            @if($latestReviews->isNotEmpty())
            <section class="mb-20">
                <div class="flex items-end justify-between mb-8 px-2">
                    <div>
                        <h2 class="text-2xl font-black text-black tracking-tighter mb-1">
                            新着レビュー
                        </h2>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">User Reviews</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($latestReviews as $review)
                        <a href="{{ $review->bikeModel->seo_url }}#reviews"
                           class="group bg-white rounded-2xl p-5 shadow-sm border border-gray-100 hover:border-blue-300 hover:shadow-lg transition-all duration-300 flex flex-col h-full">

                            {{-- ヘッダー: 車種名と評価 --}}
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <span class="text-[9px] font-bold text-gray-400 block mb-0.5">
                                        {{ $review->bikeModel->manufacturer->name }}
                                    </span>
                                    <h3 class="text-sm font-black text-gray-800 group-hover:text-blue-600 transition-colors">
                                        {{ $review->bikeModel->name }}
                                    </h3>
                                </div>
                                <div class="flex text-yellow-400 shrink-0">
                                    <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                                    <span class="ml-1 text-sm font-black text-gray-700">{{ $review->rating }}</span>
                                </div>
                            </div>

                            {{-- 本文 --}}
                            <div class="mb-4 flex-grow">
                                <h4 class="text-xs font-bold text-gray-900 mb-1 line-clamp-1">
                                    {{ $review->title }}
                                </h4>
                                <p class="text-[11px] text-gray-500 leading-relaxed line-clamp-3">
                                    {{ $review->body }}
                                </p>
                            </div>

                            {{-- フッター: 投稿者と日付 --}}
                            <div class="pt-3 border-t border-gray-50 flex items-center justify-between text-[10px] text-gray-400">
                                <span class="font-bold flex items-center gap-1">
                                    <i data-lucide="user" class="w-3 h-3"></i>
                                    {{ $review->nickname }}
                                </span>
                                <span>{{ $review->created_at->diffForHumans() }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
            @endif

            {{-- みんなの愛車 --}}
            @if(isset($latestMyBikes) && $latestMyBikes->isNotEmpty())
            <section class="mb-20">
                <div class="flex items-end justify-between mb-8 px-2">
                    <div>
                        <h2 class="text-2xl font-black text-black tracking-tighter mb-1">
                            みんなの愛車
                        </h2>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Owner's Garage</p>
                    </div>
                    <a href="{{ route('garage.public.index') }}" class="text-xs font-bold text-pink-600 hover:underline">
                        もっと見る →
                    </a>
                </div>
                <div class="flex gap-4 overflow-x-auto pb-4 snap-x scrollbar-hide">
                    @foreach($latestMyBikes as $myBike)
                    <a href="{{ route('garage.public.show', $myBike->id) }}" class="snap-start shrink-0 w-48 group">
                        <div class="aspect-[4/3] rounded-xl bg-gray-100 overflow-hidden mb-2">
                            @if($myBike->display_image)
                                <img src="{{ $myBike->display_image }}" alt="{{ $myBike->display_name }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy" decoding="async">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-300">
                                    <i data-lucide="bike" class="w-6 h-6"></i>
                                </div>
                            @endif
                        </div>
                        <p class="text-[10px] font-bold text-gray-400">{{ $myBike->bikeModel->manufacturer->name ?? '' }}</p>
                        <p class="text-sm font-black text-gray-800 line-clamp-1 group-hover:text-pink-600 transition-colors">{{ $myBike->display_name }}</p>
                        <p class="text-[10px] text-gray-400">{{ $myBike->user->name ?? '名無しライダー' }}</p>
                    </a>
                    @endforeach
                </div>
            </section>
            @endif

            </div>{{-- /section-community --}}

        </div>
    </div>
</x-layout>