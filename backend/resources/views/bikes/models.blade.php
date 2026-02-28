<x-layout>
    @php
        // ★メモリ不足エラー（Allowed memory size exhausted）を回避するため、
        // この重い一覧ページを開く時だけ、一時的にPHPのメモリ上限を大幅に引き上げます。
        ini_set('memory_limit', '512M');
    @endphp

    <x-slot:title>車種一覧から探す - MotoHub</x-slot:title>

    <x-slot:scripts>
        <script src="{{ asset('js/bikes/models.js') }}"></script>
        {{-- ★PV向上施策: 閲覧履歴マネージャーを読み込み --}}
        <script src="{{ asset('js/history/manager.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (window.HistoryManager) {
                    const authMeta = document.querySelector('meta[name="auth-check"]');
                    const bodyLoggedIn = document.body.dataset.loggedIn === 'true';
                    const isLoggedIn = (authMeta && authMeta.content === 'true') || bodyLoggedIn;
                    
                    HistoryManager.init(isLoggedIn).then(() => {
                        HistoryManager.render('history-widget').then(() => {
                            const widget = document.getElementById('history-widget');
                            if (widget && widget.children.length > 0) {
                                document.getElementById('history-section').classList.remove('hidden');
                            }
                        });
                    });
                }
            });
        </script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-12 sm:py-20">
        <div class="max-w-4xl mx-auto px-4">
            
            {{-- ヘッダー部分 --}}
            <div class="mb-10 text-center">
                <h1 class="text-2xl sm:text-3xl font-black text-black mb-2 tracking-tighter">
                    車種から探す
                </h1>
                <p class="text-gray-400 text-xs font-bold tracking-widest uppercase">
                    {{ number_format($totalModelsCount) }} MODELS AVAILABLE
                </p>
            </div>

            {{-- ★PV向上施策1: 最近見た車両（行き止まりを作らず、興味を再燃させる） --}}
            <section id="history-section" class="hidden mb-12 bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                <div class="flex items-center gap-2 mb-6">
                    <div class="p-2 bg-gray-100 rounded-lg text-gray-600">
                        <i data-lucide="clock" class="w-5 h-5"></i>
                    </div>
                    <h3 class="text-lg font-black text-gray-900">最近チェックした車両</h3>
                </div>
                
                <div id="history-widget" class="flex gap-4 overflow-x-auto pb-4 snap-x snap-mandatory scrollbar-hide -mx-2 px-2 sm:mx-0 sm:px-0">
                    {{-- JSでカードが挿入されます --}}
                </div>
            </section>

            {{-- ★追加: 今週の人気車種 TOP10 --}}
            @if(isset($trendingBikes) && count($trendingBikes) > 0)
            <section class="mb-12 bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-gray-100 overflow-hidden relative">
                <div class="absolute -right-6 -top-6 text-yellow-50 opacity-50 pointer-events-none">
                    <i data-lucide="crown" class="w-40 h-40"></i>
                </div>
                <div class="relative z-10">
                    <div class="flex items-center gap-2 mb-6">
                        <div class="p-2 bg-yellow-50 rounded-lg text-yellow-500">
                            <i data-lucide="flame" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-gray-900 leading-tight">今週の人気車種 TOP10</h3>
                            <p class="text-[10px] font-bold text-gray-400 mt-0.5">みんなが注目しているバイクをチェック！</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-4 sm:gap-5 overflow-x-auto pb-6 pt-2 snap-x snap-mandatory scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
                        {{-- 取得した人気車種から上位10件だけを表示 --}}
                        @foreach($trendingBikes->take(10) as $index => $bike)
                            <a href="{{ route('bikes.search', ['bike_model_id' => $bike->id]) }}" class="snap-start shrink-0 w-36 sm:w-44 group relative block">
                                
                                {{-- ★修正: 白背景でもくっきり見えるように、メダルの色を濃くして文字色を白で統一 --}}
                                <div class="absolute -top-3 -left-3 z-20 w-8 h-8 rounded-full flex items-center justify-center font-black text-white shadow-lg border-2 border-white
                                    {{ $index === 0 ? 'bg-gradient-to-br from-yellow-400 to-yellow-600 shadow-yellow-500/50' : 
                                      ($index === 1 ? 'bg-gradient-to-br from-slate-400 to-slate-600 shadow-slate-500/50' : 
                                      ($index === 2 ? 'bg-gradient-to-br from-orange-500 to-orange-700 shadow-orange-500/50' : 
                                      'bg-gray-800 shadow-gray-900/50')) }}">
                                    {{ $index + 1 }}
                                </div>
                                
                                <div class="bg-white rounded-2xl overflow-hidden shadow-sm group-hover:shadow-xl transition-all duration-300 border border-gray-100 flex flex-col h-full group-hover:-translate-y-1">
                                    <div class="aspect-[4/3] bg-gray-50 relative overflow-hidden">
                                        @if($bike->image_url)
                                            <img src="{{ $bike->image_url }}" alt="{{ $bike->name }}" 
                                                 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" loading="lazy" decoding="async">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                                <i data-lucide="bike" class="w-8 h-8"></i>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="p-3 sm:p-4 flex flex-col flex-1">
                                        <div class="text-[9px] sm:text-[10px] font-bold text-gray-400 mb-1 uppercase">{{ $bike->manufacturer?->name }}</div>
                                        <h4 class="text-xs sm:text-sm font-black text-gray-800 leading-tight line-clamp-2 group-hover:text-blue-600 transition-colors h-[2.5em]">
                                            {{ $bike->name }}
                                        </h4>
                                        <div class="mt-auto pt-3 border-t border-gray-50 flex items-center justify-between">
                                            <span class="inline-flex items-center text-[9px] sm:text-[10px] font-black bg-blue-50 text-blue-600 px-2 py-1 rounded-md">
                                                {{ number_format($bike->listings_count ?? 0) }}台
                                            </span>
                                            <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-gray-300 group-hover:text-blue-500 transition-colors"></i>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
            @endif

            {{-- メーカー別アコーディオンリスト --}}
            <div class="space-y-4 content-visibility-auto mb-16">
                @foreach($manufacturers as $manufacturer)
                    
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden transition duration-300 hover:shadow-md" id="maker-section-{{ $manufacturer['id'] }}">
                        
                        {{-- アコーディオンヘッダー --}}
                        <button onclick="toggleMaker({{ $manufacturer['id'] }})" class="w-full flex items-center justify-between px-4 sm:px-6 py-4 bg-white hover:bg-gray-50/50 transition-colors text-left group cursor-pointer focus:outline-none">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-full bg-gray-50 border border-gray-100 overflow-hidden flex-shrink-0 flex items-center justify-center group-hover:border-blue-200 transition-colors">
                                    @if($manufacturer['image_url'])
                                        <img src="{{ $manufacturer['image_url'] }}" alt="{{ $manufacturer['name'] }}" class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-500" loading="lazy" decoding="async">
                                    @else
                                        <i data-lucide="bike" class="w-5 h-5 text-gray-300"></i>
                                    @endif
                                </div>

                                <div class="flex flex-col">
                                    <h2 class="text-lg sm:text-xl font-black text-gray-800 tracking-tight group-hover:text-blue-600 transition-colors leading-none mb-1">
                                        {{ $manufacturer['name'] }}
                                    </h2>
                                    <span class="text-[10px] font-bold text-gray-400">
                                        {{ $manufacturer['bike_models_count'] }} Models
                                    </span>
                                </div>
                            </div>
                            
                            <div class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-50 text-gray-400 group-hover:bg-blue-50 group-hover:text-blue-600 transition duration-300 transform" id="maker-icon-{{ $manufacturer['id'] }}">
                                <i data-lucide="chevron-down" class="w-5 h-5"></i>
                            </div>
                        </button>

                        {{-- メーカー詳細エリア --}}
                        <div id="maker-list-{{ $manufacturer['id'] }}" class="hidden border-t border-gray-100 bg-gray-50/30">
                            <div class="p-2 sm:p-4 space-y-2">
                                
                                @foreach($manufacturer['groups'] as $label => $list)
                                    @if(count($list) > 0)
                                        @php $subId = $manufacturer['id'] . '-' . $loop->index; @endphp
                                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden content-visibility-auto">
                                            <button onclick="toggleSubGroup('{{ $subId }}')" class="w-full flex items-center justify-between px-4 py-3 bg-white hover:bg-gray-50 transition-colors text-left group/sub">
                                                <div class="flex items-center gap-3">
                                                    <span class="text-sm font-bold text-gray-700 min-w-[2rem]">{{ $label }}</span>
                                                    <span class="text-[10px] font-medium text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">{{ count($list) }}</span>
                                                </div>
                                                <i data-lucide="chevron-down" class="w-4 h-4 text-gray-300 group-hover/sub:text-blue-500 transition-transform duration-200" id="sub-icon-{{ $subId }}"></i>
                                            </button>

                                            <div id="sub-list-{{ $subId }}" class="hidden border-t border-gray-100 p-3 sm:p-4 bg-gray-50/50">
                                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                                    @foreach($list as $bike)
                                                    @php 
                                                        // 万が一データが配列形式で渡ってきてもエラーにならないよう安全に処理
                                                        $b = is_array($bike) ? (object)$bike : $bike; 
                                                    @endphp
                                                    <a href="{{ route('bikes.search', ['bike_model_id' => $b->id]) }}"
                                                        class="group/item flex items-center p-3 bg-white rounded-xl border border-gray-200 hover:border-blue-400 hover:shadow-md hover:-translate-y-0.5 transition duration-300">
                                                        
                                                        <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-lg bg-gray-50 overflow-hidden flex-shrink-0 mr-3 border border-gray-100 relative">
                                                            @if($b->image_url)
                                                            <img src="{{ $b->image_url }}" alt="{{ $b->name }}" 
                                                                 loading="lazy" decoding="async"
                                                                 class="w-full h-full object-cover transform group-hover/item:scale-110 transition-transform duration-500"
                                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                            <div class="hidden w-full h-full items-center justify-center text-gray-300">
                                                                <i data-lucide="bike" class="w-5 h-5"></i>
                                                            </div>
                                                            @else
                                                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                                                <i data-lucide="bike" class="w-5 h-5"></i>
                                                            </div>
                                                            @endif
                                                        </div>

                                                        <div class="flex-1 min-w-0">
                                                            <h3 class="text-xs font-bold text-gray-700 leading-tight group-hover/item:text-blue-600 transition-colors line-clamp-1 mb-1">
                                                                {{ $b->name }}
                                                            </h3>
                                                            <span class="inline-flex items-center text-[9px] font-medium text-gray-400 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-100">
                                                                <span class="text-blue-500 font-bold mr-0.5">{{ number_format($b->listings_count ?? 0) }}</span>台
                                                            </span>
                                                        </div>
                                                    </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach

                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ★PV向上施策2: 回遊リンク（他の探し方を提案し、直帰率を下げる） --}}
            <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100 mb-12">
                <h3 class="text-lg font-black text-gray-900 mb-6 flex items-center gap-2">
                    <i data-lucide="search" class="w-5 h-5 text-blue-500"></i>
                    他の条件から探す
                </h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                    <div>
                        <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3 border-b border-gray-50 pb-2">排気量別</h4>
                        <ul class="space-y-3">
                            <li><a href="{{ route('bikes.search', ['max_displacement' => 50]) }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-gray-300"></i> 50cc以下（原付）</a></li>
                            <li><a href="{{ route('bikes.search', ['min_displacement' => 51, 'max_displacement' => 125]) }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-gray-300"></i> 51cc〜125cc（小型）</a></li>
                            <li><a href="{{ route('bikes.search', ['min_displacement' => 126, 'max_displacement' => 400]) }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-gray-300"></i> 126cc〜400cc（中型）</a></li>
                            <li><a href="{{ route('bikes.search', ['min_displacement' => 401]) }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-gray-300"></i> 401cc以上（大型）</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3 border-b border-gray-50 pb-2">人気のカテゴリ</h4>
                        <ul class="space-y-3">
                            <li><a href="{{ route('bikes.search', ['category_id' => 1]) }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-gray-300"></i> ネイキッド</a></li>
                            <li><a href="{{ route('bikes.search', ['category_id' => 2]) }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-gray-300"></i> スーパースポーツ</a></li>
                            <li><a href="{{ route('bikes.search', ['category_id' => 3]) }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-gray-300"></i> アメリカン・クルーザー</a></li>
                            <li><a href="{{ route('bikes.search', ['category_id' => 4]) }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-gray-300"></i> オフロード・モタード</a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="mt-8 pt-6 border-t border-gray-50 flex justify-center">
                    <a href="{{ route('bikes.prefectures') }}" class="inline-flex items-center gap-2 bg-gray-900 text-white hover:bg-gray-800 px-6 py-3 rounded-xl text-sm font-black transition-colors shadow-sm">
                        <i data-lucide="map" class="w-4 h-4"></i> 地域から探す
                    </a>
                </div>
            </div>

            {{-- ページ下部バックリンク --}}
            <div class="pt-4 border-t border-gray-200 text-center">
                <a href="{{ route('bikes.index') }}" class="inline-flex items-center gap-2 text-xs font-black text-gray-400 hover:text-black transition-colors uppercase tracking-widest">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> トップページに戻る
                </a>
            </div>
        </div>
    </div>
    
    <style>
        .content-visibility-auto {
            content-visibility: auto;
            contain-intrinsic-size: 100px;
        }
    </style>
</x-layout>