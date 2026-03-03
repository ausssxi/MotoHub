<x-layout>
    @php
        // ★メモリ不足エラー（Allowed memory size exhausted）を回避するため、
        // この重い一覧ページを開く時だけ、一時的にPHPのメモリ上限を大幅に引き上げます。
        ini_set('memory_limit', '512M');
    @endphp

    <x-slot:title>車種一覧から探す - MotoHub</x-slot:title>

    <x-slot:scripts>
        <script src="{{ asset('js/bikes/models.js') }}"></script>
        {{-- 閲覧履歴マネージャーを読み込み --}}
        <script src="{{ asset('js/history/manager.js') }}"></script>
        
        {{-- ★修正: DOMの読み込みを待たずに即座にAPI通信を開始して表示を高速化 --}}
        <script>
            (async function() {
                // manager.js の読み込みを待つ
                while (typeof HistoryManager === 'undefined') {
                    await new Promise(resolve => setTimeout(resolve, 50));
                }

                const authMeta = document.querySelector('meta[name="auth-check"]');
                const bodyLoggedIn = document.body.dataset.loggedIn === 'true';
                const isLoggedIn = (authMeta && authMeta.content === 'true') || bodyLoggedIn;
                
                // 裏側で通信を開始
                await HistoryManager.init(isLoggedIn);
                
                // HTML(DOM)が生成されるのを待ってから描画
                const renderInterval = setInterval(() => {
                    const widget = document.getElementById('history-widget');
                    if (widget) {
                        clearInterval(renderInterval);
                        HistoryManager.render('history-widget').then(() => {
                            if (widget.children.length > 0) {
                                document.getElementById('history-section').classList.remove('hidden');
                            }
                        });
                    }
                }, 100);
            })();
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

            {{-- 急上昇トレンド車種 TOP10 --}}
            @if(isset($trendingBikes) && count($trendingBikes) > 0)
            <section class="mb-12 bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-gray-100 overflow-hidden relative">
                <div class="absolute -right-6 -top-6 text-yellow-50 opacity-50 pointer-events-none">
                    <i data-lucide="trending-up" class="w-40 h-40"></i>
                </div>
                <div class="relative z-10">
                    <div class="flex items-center gap-2 mb-6">
                        <div class="p-2 bg-yellow-50 rounded-lg text-yellow-500">
                            <i data-lucide="flame" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-gray-900 leading-tight">急上昇トレンド車種 TOP10</h3>
                            <p class="text-[10px] font-bold text-gray-400 mt-0.5">昨日・今日でみんなが熱い視線を送っている大注目モデル！</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-4 sm:gap-5 overflow-x-auto pb-6 pt-2 snap-x snap-mandatory scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
                        @foreach($trendingBikes->take(10) as $index => $bike)
                            <div class="snap-start shrink-0 w-36 sm:w-44 group relative block">
                                
                                {{-- ★メインリンク --}}
                                <a href="{{ route('bikes.search', ['bike_model_id' => $bike->id]) }}" class="absolute inset-0 z-10"></a>

                                {{-- 順位メダルバッジ --}}
                                <div class="absolute -top-3 -left-3 z-20 w-8 h-8 rounded-full flex items-center justify-center font-black text-white border-2 border-white"
                                     style="{{ $index === 0 ? 'background: linear-gradient(135deg, #facc15, #d97706); box-shadow: 0 4px 10px rgba(217, 119, 6, 0.4);' : 
                                              ($index === 1 ? 'background: linear-gradient(135deg, #9ca3af, #4b5563); box-shadow: 0 4px 10px rgba(75, 85, 99, 0.4);' : 
                                              ($index === 2 ? 'background: linear-gradient(135deg, #ea580c, #9a3412); box-shadow: 0 4px 10px rgba(154, 52, 18, 0.4);' : 
                                              'background: #1f2937; box-shadow: 0 4px 10px rgba(31, 41, 55, 0.4);')) }}">
                                    {{ $index + 1 }}
                                </div>
                                
                                <div class="bg-white rounded-2xl overflow-hidden shadow-sm group-hover:shadow-xl transition-all duration-300 border border-gray-100 flex flex-col h-full group-hover:-translate-y-1 relative">
                                    <div class="aspect-[4/3] bg-gray-50 relative overflow-hidden">
                                        @if($bike->image_url)
                                            <img src="{{ Str::startsWith($bike->image_url, ['http://', 'https://']) ? $bike->image_url : asset($bike->image_url) }}" alt="{{ $bike->name }}" 
                                                 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" decoding="async">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                                <i data-lucide="bike" class="w-8 h-8"></i>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="p-3 sm:p-4 flex flex-col flex-1 relative">
                                        <div class="text-[9px] sm:text-[10px] font-bold text-gray-400 mb-1 uppercase">{{ $bike->manufacturer?->name }}</div>
                                        <h4 class="text-xs sm:text-sm font-black text-gray-800 leading-tight line-clamp-2 group-hover:text-blue-600 transition-colors h-[2.5em]">
                                            {{ $bike->name }}
                                        </h4>
                                        <div class="mt-auto pt-3 border-t border-gray-50 flex items-center justify-between relative z-20">
                                            <span class="inline-flex items-center text-[9px] sm:text-[10px] font-black bg-blue-50 text-blue-600 px-2 py-1 rounded-md">
                                                {{ number_format($bike->listings_count ?? 0) }}台
                                            </span>
                                            {{-- ★修正: onclick="event.stopPropagation();" を追加して親のリンク発火を防止 --}}
                                            <a href="{{ $bike->seo_url }}#reviews" 
                                               onclick="event.stopPropagation();"
                                               class="inline-flex items-center gap-1 text-[9px] sm:text-[10px] font-bold text-yellow-600 bg-yellow-50 hover:bg-yellow-100 px-2 py-1 rounded-md border border-yellow-200 transition-colors shadow-sm" title="オーナーの口コミを見る">
                                                @if(isset($bike->reviews_avg_rating) && $bike->reviews_count > 0)
                                                    <i data-lucide="star" class="w-3 h-3 fill-current text-yellow-500"></i>
                                                    {{ number_format($bike->reviews_avg_rating, 1) }}
                                                @else
                                                    <i data-lucide="message-square-quote" class="w-3 h-3"></i> 口コミ
                                                @endif
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
            @endif

            {{-- メーカー別アコーディオンリスト --}}
            {{-- ★修正: content-visibility-auto クラスを削除して画像の透明化バグを防ぐ --}}
            <div class="space-y-4 mb-16">
                @foreach($manufacturers as $manufacturer)
                    
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden transition duration-300 hover:shadow-md" id="maker-section-{{ $manufacturer['id'] }}">
                        
                        {{-- アコーディオンヘッダー --}}
                        <button onclick="toggleMaker({{ $manufacturer['id'] }})" class="w-full flex items-center justify-between px-4 sm:px-6 py-4 bg-white hover:bg-gray-50/50 transition-colors text-left group cursor-pointer focus:outline-none">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-full bg-gray-50 border border-gray-100 overflow-hidden flex-shrink-0 flex items-center justify-center group-hover:border-blue-200 transition-colors">
                                    {{-- ★修正: オブジェクトでも配列でも対応できるようにし、loading="lazy" を削除 --}}
                                    @php $mImageUrl = $manufacturer['image_url'] ?? $manufacturer['logo_url'] ?? null; @endphp
                                    @if($mImageUrl)
                                        <img src="{{ Str::startsWith($mImageUrl, ['http://', 'https://']) ? $mImageUrl : asset($mImageUrl) }}" 
                                             alt="{{ $manufacturer['name'] }}" 
                                             class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-500" 
                                             decoding="async"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                        <i data-lucide="bike" class="w-5 h-5 text-gray-300 hidden"></i>
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
                                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
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
                                                    @php $b = is_array($bike) ? (object)$bike : $bike; @endphp
                                                    <div class="group/item relative flex items-center p-3 bg-white rounded-xl border border-gray-200 hover:border-blue-400 hover:shadow-md hover:-translate-y-0.5 transition duration-300">
                                                        
                                                        {{-- ★メインリンク --}}
                                                        <a href="{{ route('bikes.search', ['bike_model_id' => $b->id]) }}" class="absolute inset-0 z-10"></a>

                                                        <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-lg bg-gray-50 overflow-hidden flex-shrink-0 mr-3 border border-gray-100 relative">
                                                            @if($b->image_url)
                                                            <img src="{{ Str::startsWith($b->image_url, ['http://', 'https://']) ? $b->image_url : asset($b->image_url) }}" alt="{{ $b->name }}" 
                                                                 decoding="async"
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

                                                        <div class="flex-1 min-w-0 pr-2 pointer-events-none relative z-0">
                                                            <h3 class="text-xs font-bold text-gray-700 leading-tight group-hover/item:text-blue-600 transition-colors line-clamp-1 mb-1">
                                                                {{ $b->name }}
                                                            </h3>
                                                            <span class="inline-flex items-center text-[9px] font-medium text-gray-400 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-100">
                                                                <span class="text-blue-500 font-bold mr-0.5">{{ number_format($b->listings_count ?? 0) }}</span>台
                                                            </span>
                                                        </div>

                                                        {{-- ★修正: onclick="event.stopPropagation();" を追加して親のリンク発火を防止 --}}
                                                        <a href="{{ $b->seo_url }}#reviews" 
                                                           onclick="event.stopPropagation();"
                                                           class="relative z-20 shrink-0 inline-flex items-center justify-center w-11 h-11 sm:w-auto sm:h-auto sm:px-3 sm:py-2 bg-yellow-50 hover:bg-yellow-100 text-yellow-600 rounded-lg border border-yellow-200 transition-colors shadow-sm group-hover/item:border-yellow-300 flex-col sm:flex-row gap-0.5 sm:gap-1.5" title="オーナーの口コミを見る">
                                                            @if(isset($b->reviews_avg_rating) && $b->reviews_count > 0)
                                                                <i data-lucide="star" class="w-4 h-4 sm:w-3 sm:h-3 fill-current text-yellow-500"></i>
                                                                <span class="text-[8px] sm:text-[10px] font-bold leading-none">{{ number_format($b->reviews_avg_rating, 1) }}<span class="hidden sm:inline"> ({{ $b->reviews_count }})</span></span>
                                                            @else
                                                                <i data-lucide="message-square-quote" class="w-4 h-4 sm:w-3 sm:h-3"></i>
                                                                <span class="text-[8px] sm:text-[10px] font-bold leading-none">口コミ</span>
                                                            @endif
                                                        </a>
                                                    </div>
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

            {{-- PV向上施策2: 回遊リンク --}}
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
    
    {{-- ★修正: ここにあった <style>...</style> の不具合原因コードを削除しました --}}
</x-layout>