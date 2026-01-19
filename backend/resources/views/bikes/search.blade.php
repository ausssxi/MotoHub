<x-layout>
    <x-slot:title>
        @if($keyword) 「{{ $keyword }}」の検索結果 @else 車両一覧 @endif - MotoHub
    </x-slot:title>

    <x-slot:styles>
        <style>
            .filter-sidebar-content { position: sticky; top: 80px; height: fit-content; }
            
            @media (max-width: 1023.9px) {
                #filter-sidebar { position: fixed; inset: 0; z-index: 100; visibility: hidden; transition: visibility 0.3s; }
                #filter-sidebar.active { visibility: visible; }
                #filter-overlay { position: absolute; inset: 0; background: rgba(0, 0, 0, 0.5); opacity: 0; transition: opacity 0.3s; }
                #filter-sidebar.active #filter-overlay { opacity: 1; }
                .filter-sidebar-container { position: absolute; bottom: 0; left: 0; right: 0; background: white; border-radius: 24px 24px 0 0; transform: translateY(100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); max-height: 92vh; overflow-y: auto; }
                #filter-sidebar.active .filter-sidebar-container { transform: translateY(0); }
            }

            /* スライダー共通スタイル */
            .range-slider-container { position: relative; width: 100%; height: 40px; margin-top: 8px; }
            .slider-track { position: absolute; width: 100%; height: 4px; background: #f3f4f6; border-radius: 2px; top: 50%; transform: translateY(-50%); }
            .slider-progress { position: absolute; height: 4px; background: #5392f9; border-radius: 2px; top: 50%; transform: translateY(-50%); }
            .range-input { position: absolute; width: 100%; height: 4px; top: 50%; transform: translateY(-50%); background: none; pointer-events: none; -webkit-appearance: none; margin: 0; }
            .range-input::-webkit-slider-thumb { height: 24px; width: 24px; border-radius: 50%; background: #fff; border: 2px solid #5392f9; pointer-events: auto; -webkit-appearance: none; cursor: pointer; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); transition: all 0.2s; }
            .range-input::-webkit-slider-thumb:active { transform: scale(1.2); background: #5392f9; }
        </style>
    </x-slot:styles>

    <x-slot:navigation>
        <x-navigation :totalListingsCount="$totalListingsCount" :showSearch="true" :keyword="$keyword" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-7xl mx-auto px-4 flex flex-col lg:flex-row gap-8">
            
            <!-- サイドバー / モバイルモーダル -->
            <aside id="filter-sidebar" class="w-full lg:w-72 flex-shrink-0">
                <div id="filter-overlay" class="lg:hidden"></div>
                
                <div class="filter-sidebar-container filter-sidebar-content bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col">
                    <div class="p-5 border-b border-gray-50 flex items-center justify-between flex-shrink-0">
                        <h3 class="text-xs font-black text-black flex items-center gap-2 uppercase tracking-widest">
                            <i data-lucide="filter" class="w-4 h-4 text-blue-500"></i> 絞り込み条件
                        </h3>
                        <div class="flex items-center gap-4">
                            <a href="{{ route('bikes.search', ['keyword' => $keyword]) }}" class="text-[10px] font-bold text-gray-400 hover:text-blue-600 transition-colors uppercase">リセット</a>
                            <button id="close-filter" class="lg:hidden p-1 text-gray-400"><i data-lucide="x" class="w-5 h-5"></i></button>
                        </div>
                    </div>

                    <form action="{{ route('bikes.search') }}" method="GET" id="filter-form" class="p-6 space-y-10 overflow-y-auto">
                        <input type="hidden" name="keyword" value="{{ $keyword }}">
                        <input type="hidden" name="prefecture" value="{{ $prefecture }}">
                        <input type="hidden" name="sort" value="{{ $sort }}">

                        <!-- 価格 -->
                        <div class="filter-group">
                            <div class="flex justify-between items-end mb-4">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic">価格</label>
                                <div class="text-xs font-black text-blue-600 tracking-tighter">
                                    <span id="label-min-price"></span> 〜 <span id="label-max-price"></span>
                                </div>
                            </div>
                            <div class="range-slider-container" id="slider-price">
                                <div class="slider-track"></div>
                                <div class="slider-progress"></div>
                                <input type="range" class="range-input range-min" name="min_price" min="0" max="300" value="{{ $filters['min_price'] ?? 0 }}" step="5">
                                <input type="range" class="range-input range-max" name="max_price" min="0" max="300" value="{{ $filters['max_price'] ?? 300 }}" step="5">
                            </div>
                        </div>

                        <!-- 走行距離 -->
                        <div class="filter-group">
                            <div class="flex justify-between items-end mb-4">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic">走行距離</label>
                                <div class="text-xs font-black text-blue-600 tracking-tighter">
                                    <span id="label-min-mileage"></span> 〜 <span id="label-max-mileage"></span>
                                </div>
                            </div>
                            <div class="range-slider-container" id="slider-mileage">
                                <div class="slider-track"></div>
                                <div class="slider-progress"></div>
                                <input type="range" class="range-input range-min" name="min_mileage" min="0" max="50000" value="{{ $filters['min_mileage'] ?? 0 }}" step="1000">
                                <input type="range" class="range-input range-max" name="max_mileage" min="0" max="50000" value="{{ $filters['max_mileage'] ?? 50000 }}" step="1000">
                            </div>
                        </div>

                        <!-- 年式 -->
                        <div class="filter-group">
                            <div class="flex justify-between items-end mb-4">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic">年式</label>
                                <div class="text-xs font-black text-blue-600 tracking-tighter">
                                    <span id="label-min-year"></span> 〜 <span id="label-max-year"></span>
                                </div>
                            </div>
                            <div class="range-slider-container" id="slider-year">
                                <div class="slider-track"></div>
                                <div class="slider-progress"></div>
                                <input type="range" class="range-input range-min" name="min_year" min="1990" max="2026" value="{{ $filters['min_year'] ?? 1990 }}" step="1">
                                <input type="range" class="range-input range-max" name="max_year" min="1990" max="2026" value="{{ $filters['max_year'] ?? 2026 }}" step="1">
                            </div>
                        </div>

                        <!-- モバイル専用：検索ボタン（件数をリアルタイム更新） -->
                        <div class="pt-4 lg:hidden">
                            <button type="submit" id="mobile-search-btn" class="w-full bg-[#5392f9] text-white font-black py-4 rounded-2xl text-[11px] uppercase tracking-[0.1em] shadow-xl shadow-blue-100 hover:bg-blue-600 active:scale-95 transition-all flex items-center justify-center gap-2">
                                <span>この条件で検索</span>
                                <span id="mobile-hit-count" class="bg-white/20 px-2 py-0.5 rounded text-[10px] min-w-[3rem]">
                                    {{ number_format($pagination['total']) }} 件
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </aside>

            <!-- メインコンテンツ -->
            <div class="flex-1">
                <div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-black text-black tracking-tighter italic">
                            @if($keyword) 「{{ $keyword }}」 @else 車両一覧 @endif 
                            <span class="text-xs text-gray-400 font-bold ml-2 not-italic">({{ number_format($pagination['total']) }}台)</span>
                        </h2>
                    </div>
                    
                    <div class="flex items-center gap-3">
                        <button id="open-filter" class="lg:hidden flex items-center gap-2 bg-white border border-gray-100 rounded-xl px-4 py-2.5 text-xs font-black shadow-sm active:bg-gray-50 transition-all">
                            <i data-lucide="sliders-horizontal" class="w-4 h-4 text-blue-500"></i>
                            <span>絞り込み</span>
                        </button>

                        <div class="relative">
                            <select name="sort" onchange="const f=document.getElementById('filter-form'); f.elements['sort'].value=this.value; f.submit();"
                                    class="bg-white border border-gray-100 rounded-xl px-4 py-2.5 text-xs font-black focus:outline-none cursor-pointer hover:border-blue-500 transition-all appearance-none pr-10 shadow-sm">
                                <option value="latest" {{ $sort === 'latest' ? 'selected' : '' }}>新着順</option>
                                <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>価格の安い順</option>
                                <option value="price_desc" {{ $sort === 'price_desc' ? 'selected' : '' }}>価格の高い順</option>
                                <option value="mileage_asc" {{ $sort === 'mileage_asc' ? 'selected' : '' }}>走行距離が少ない</option>
                                <option value="year_desc" {{ $sort === 'year_desc' ? 'selected' : '' }}>年式が新しい</option>
                            </select>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                        </div>
                    </div>
                </div>

                <!-- 結果グリッド -->
                <div id="results-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                    @forelse ($items as $listing)
                        <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-2xl transition-all duration-500 flex flex-col group border border-gray-100 relative cursor-pointer">
                            <a href="{{ $listing['url'] }}" target="_blank" rel="noopener noreferrer" class="absolute inset-0 z-20"></a>
                            <div class="aspect-[4/3] relative overflow-hidden bg-gray-50">
                                @if(!empty($listing['images']) && isset($listing['images'][0]))
                                    <img src="{{ $listing['images'][0] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" alt="">
                                @endif
                                <div class="absolute top-3 right-3 z-10 bg-white/90 backdrop-blur-sm px-2 py-1 rounded-lg flex items-center gap-1.5 border border-white/20">
                                    <img src="https://www.google.com/s2/favicons?domain={{ $listing['source_domain'] }}&sz=32" class="w-3 h-3 rounded-sm" alt="">
                                    <span class="text-[8px] font-black text-gray-500">{{ $listing['source'] }}</span>
                                </div>
                            </div>
                            <div class="p-5 flex-grow flex flex-col">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-[9px] font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded uppercase">{{ $listing['maker'] }}</span>
                                </div>
                                <h3 class="text-sm font-black text-gray-800 mb-4 line-clamp-2 leading-tight group-hover:text-blue-600 transition-colors">{{ $listing['name'] }}</h3>
                                
                                <div class="flex items-center gap-4 text-[10px] font-bold text-gray-400 mb-6">
                                    <div class="flex items-center gap-1.5"><i data-lucide="calendar" class="w-3.5 h-3.5 text-gray-300"></i>{{ $listing['model_year'] }}</div>
                                    <div class="flex items-center gap-1.5"><i data-lucide="gauge" class="w-3.5 h-3.5 text-gray-300"></i>{{ $listing['mileage'] }}</div>
                                </div>

                                <div class="mt-auto bg-gray-50 p-4 rounded-xl border border-gray-100 group-hover:bg-blue-50 transition-all duration-300">
                                    <div class="flex justify-between items-end">
                                        <div>
                                            <span class="text-[8px] font-black text-gray-400 block uppercase tracking-tighter">支払総額</span>
                                            <div class="text-red-500 font-black italic">
                                                <span class="text-2xl tracking-tighter">{{ $listing['total_price'] }}</span><span class="text-[10px] ml-0.5">万円</span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-[8px] font-black text-gray-400 block uppercase">本体価格</span>
                                            <div class="text-gray-700 font-black italic">
                                                <span class="text-lg tracking-tighter">{{ $listing['base_price'] }}</span><span class="text-[9px] ml-0.5">万円</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-40 text-center">
                            <i data-lucide="search-x" class="w-16 h-16 text-gray-200 mx-auto mb-6"></i>
                            <p class="text-gray-400 font-black uppercase tracking-widest text-xs">No matching bikes found</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/range-slider.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('filter-sidebar');
            const openBtn = document.getElementById('open-filter');
            const closeBtn = document.getElementById('close-filter');
            const overlay = document.getElementById('filter-overlay');
            const form = document.getElementById('filter-form');
            const mobileHitCount = document.getElementById('mobile-hit-count');

            const toggle = () => {
                sidebar.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
            };

            [openBtn, closeBtn, overlay].forEach(el => el?.addEventListener('click', toggle));

            // スマホ画面用：スライダー操作時に件数だけを非同期で更新するロジック
            let updateTimer;
            const updateCountOnly = () => {
                if (window.innerWidth >= 1024) return; // PCは即時リロードなので不要

                clearTimeout(updateTimer);
                updateTimer = setTimeout(async () => {
                    const formData = new URLSearchParams(new FormData(form));
                    formData.append('count_only', '1');

                    try {
                        mobileHitCount.innerHTML = '<i data-lucide="loader-2" class="w-3 h-3 animate-spin inline-block"></i>';
                        if (window.lucide) window.lucide.createIcons();

                        const response = await fetch(`${form.action}?${formData.toString()}`, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        
                        if (!response.ok) throw new Error('Network response was not ok');
                        
                        const data = await response.json();
                        mobileHitCount.textContent = `${data.total.toLocaleString()} 件`;
                    } catch (e) {
                        console.error('Count update failed', e);
                        mobileHitCount.textContent = '- 件';
                    }
                }, 300);
            };

            form.querySelectorAll('input[type="range"]').forEach(input => {
                input.addEventListener('input', updateCountOnly);
            });
        });
    </script>
</x-layout>