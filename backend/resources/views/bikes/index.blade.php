<x-layout>
    <x-slot:title>MotoHub - 中古・新車バイク一括検索</x-slot:title>

    <x-slot:scripts>
        <script src="{{ asset('js/search/suggest.js') }}"></script>
        
        {{-- 閲覧履歴のスクリプトを読み込み、描画を実行 --}}
        <script src="{{ asset('js/history/manager.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (window.HistoryManager) {
                    const bodyLoggedIn = document.body.dataset.loggedIn === 'true';
                    const metaLoggedIn = document.querySelector('meta[name="auth-check"]')?.content === 'true';
                    const isLoggedIn = bodyLoggedIn || metaLoggedIn;
                    
                    HistoryManager.init(isLoggedIn).then(() => {
                        // 指定したIDのコンテナに履歴カードを描画
                        HistoryManager.render('top-history-widget').then(() => {
                            const widget = document.getElementById('top-history-widget');
                            // 履歴が1件以上あればセクション全体を表示する
                            if (widget && widget.children.length > 0) {
                                document.getElementById('top-history-section').classList.remove('hidden');
                            }
                        });
                    });
                }
            });
        </script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="false" />
    </x-slot:navigation>

    {{-- メインビジュアル & 検索ボックス --}}
    <div class="relative bg-black h-[500px] sm:h-[600px] flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0 z-0">
             <img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=2070&auto=format&fit=crop" 
                  alt="Motorcycle Background" 
                  class="w-full h-full object-cover opacity-40">
        </div>
        
        <div class="relative z-10 w-full max-w-4xl px-4 text-center" id="search-container">
            <h1 class="text-3xl sm:text-5xl font-black text-white mb-4 tracking-tight leading-tight">
                掲載台数<span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-cyan-300">&nbsp;No.1！</span>
            </h1>
            <p class="text-gray-300 text-xs sm:text-sm font-bold mb-8 tracking-widest">
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
            <div class="mt-8 flex justify-center">
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

            {{-- クイックリンク＆人気の条件（タグ）の露出強化 --}}
            <div class="mt-8 flex flex-col items-center gap-5">
                
                {{-- コントローラーから渡された $popularTags を使用 --}}
                <div class="flex flex-wrap justify-center items-center gap-2">
                    <span class="text-[10px] font-black text-blue-300 uppercase tracking-widest mr-1">
                        <i data-lucide="zap" class="w-3 h-3 inline-block -mt-0.5 text-yellow-400"></i> トレンド:
                    </span>
                    @foreach($popularTags as $tag)
                        <a href="{{ route('bikes.search', ['tag' => $tag]) }}" 
                           class="px-3 py-1.5 rounded-full bg-blue-500/20 hover:bg-blue-500/40 text-blue-50 text-[10px] font-bold border border-blue-400/30 backdrop-blur-sm transition-all shadow-lg shadow-blue-500/10">
                            #{{ $tag }}
                        </a>
                    @endforeach
                </div>

                {{-- メーカーリンク --}}
                <div class="flex flex-wrap justify-center gap-2">
                    @foreach($manufacturers as $maker)
                        <a href="{{ route('bikes.search', ['manufacturer_id' => $maker->id]) }}" class="px-3 py-1 rounded-full bg-white/5 hover:bg-white/10 text-gray-300 text-[10px] font-bold border border-white/10 backdrop-blur-sm transition-all">
                            {{ $maker->name }}
                        </a>
                    @endforeach
                </div>

            </div>
        </div>
    </div>

    {{-- 特集セクションの先頭にも診断を追加 --}}
    <div class="bg-gray-50 py-16 sm:py-24">
        <div class="max-w-7xl mx-auto px-4">
            
            {{-- 最近見た車両（閲覧履歴）セクション --}}
            <section id="top-history-section" class="mb-20 hidden">
                <div class="flex items-end justify-between mb-6 px-2">
                    <div>
                        <h2 class="text-2xl font-black text-black tracking-tighter mb-1 flex items-center gap-2">
                            <i data-lucide="clock" class="w-6 h-6 text-gray-400"></i>
                            最近見た車両
                        </h2>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Recently Viewed</p>
                    </div>
                </div>
                
                {{-- JSによって、この中に履歴のカードが横スクロールで自動生成されます --}}
                <div id="top-history-widget" class="flex gap-4 overflow-x-auto pb-4 scrollbar-hide snap-x snap-mandatory px-2 -mx-2 sm:mx-0 sm:px-0">
                </div>
            </section>

            {{-- ★修正: 構造が壊れていたおすすめコンテンツを復旧 --}}
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
            </section>

            {{-- タイプから探す --}}
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
                                     class="w-full h-full object-contain transform group-hover:scale-110 transition-transform duration-500">
                            </div>
                            
                            <span class="text-xs font-bold text-gray-700 group-hover:text-blue-600 transition-colors leading-tight">
                                {{ $category->name }}
                            </span>
                        </a>
                        @endif
                    @endforeach
                </div>
            </section>

            {{-- 免許・排気量から探す --}}
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

            {{-- 人気車種セクション --}}
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
                        <a href="{{ route('bikes.search', ['bike_model_id' => $bike->id]) }}" 
                           class="group flex items-center p-3 bg-white rounded-xl border border-gray-100 shadow-sm hover:border-blue-300 hover:shadow-md transition-all duration-300">
                            
                            <div class="w-14 h-14 rounded-lg bg-gray-50 overflow-hidden flex-shrink-0 border border-gray-50 relative">
                                @if($bike->image_url)
                                    <img src="{{ $bike->image_url }}" alt="{{ $bike->name }}" 
                                         class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-500">
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
                        <a href="{{ route('bikes.model_detail', $review->bike_model_id) }}#reviews" 
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

            {{-- 都道府県から探す --}}
            <section>
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
        </div>
    </div>
</x-layout>