<div class="space-y-16">
    {{-- 関連車両（同じ車種） --}}
    @if(!empty($relatedListings))
    <section>
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2">
                <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                    <i data-lucide="copy" class="w-5 h-5"></i>
                </div>
                <h3 class="text-lg font-black text-gray-900">この車種の他の車両</h3>
            </div>
            @if($listing->bike_model_id)
            <a href="{{ route('bikes.search', ['bike_model_id' => $listing->bike_model_id]) }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1 group">
                すべて見る <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
            </a>
            @endif
        </div>
        
        <div class="flex gap-4 overflow-x-auto pb-4 snap-x snap-mandatory scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
            @foreach($relatedListings as $rel)
                <a href="{{ route('bikes.show', $rel['id']) }}" class="snap-start shrink-0 w-40 sm:w-48 group bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition border border-gray-100 flex flex-col relative block">
                    <div class="aspect-[4/3] bg-gray-50 relative overflow-hidden">
                        <img src="{{ $rel['images'][0] ?? 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop' }}"
                             onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop'; this.classList.add('grayscale', 'opacity-50');"
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500 {{ empty($rel['images']) ? 'grayscale opacity-50' : '' }}" alt="{{ $rel['name'] }}"
                             loading="lazy" decoding="async">
                        
                        {{-- お買い得バッジ --}}
                        @if(isset($rel['bargain_score']) && $rel['bargain_score'] > 5)
                            <div class="absolute bottom-0 left-0 bg-red-600 text-white text-[9px] font-black px-1.5 py-1 rounded-tr-xl shadow-lg z-10 flex items-center gap-1">
                                <i data-lucide="trending-down" class="w-3 h-3"></i>
                                約{{ round($rel['bargain_score']) }}%お得！
                            </div>
                        @endif

                        <div class="absolute bottom-2 right-2 bg-black/60 backdrop-blur-sm text-white px-2 py-0.5 rounded text-[10px] font-black">
                            {{ $rel['total_price'] }}万円
                        </div>
                    </div>
                    <div class="p-3 flex flex-col flex-1">
                        <div class="flex gap-2 text-[9px] font-bold text-gray-400 mb-1">
                            <span>{{ $rel['model_year'] }}</span>
                            <span>{{ $rel['mileage'] }}</span>
                        </div>
                        <h4 class="text-xs font-black text-gray-800 leading-tight line-clamp-2 mb-2 group-hover:text-blue-600 transition-colors h-[2.5em]">
                            {{ $rel['name'] }}
                        </h4>
                        <div class="mt-auto text-[9px] text-gray-400 flex items-center gap-1 border-t border-gray-100 pt-2">
                            <i data-lucide="map-pin" class="w-3 h-3"></i> <span class="truncate">{{ $rel['prefecture'] }}</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
    @endif

    {{-- 類似車両（同じメーカーの別車種など） --}}
    @if(!empty($similarListings))
    <section>
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2">
                <div class="p-2 bg-orange-50 rounded-lg text-orange-600">
                    <i data-lucide="git-branch" class="w-5 h-5"></i>
                </div>
                <h3 class="text-lg font-black text-gray-900">似ている条件の車両</h3>
            </div>
        </div>
        
        <div class="flex gap-4 overflow-x-auto pb-4 snap-x snap-mandatory scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
            @foreach($similarListings as $sim)
                <a href="{{ route('bikes.show', $sim['id']) }}" class="snap-start shrink-0 w-40 sm:w-48 group bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition border border-gray-100 flex flex-col relative block">
                    <div class="aspect-[4/3] bg-gray-50 relative overflow-hidden">
                        <img src="{{ $sim['images'][0] ?? 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop' }}"
                             onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop'; this.classList.add('grayscale', 'opacity-50');"
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500 {{ empty($sim['images']) ? 'grayscale opacity-50' : '' }}" alt="{{ $sim['name'] }}"
                             loading="lazy" decoding="async">
                        
                        {{-- お買い得バッジ --}}
                        @if(isset($sim['bargain_score']) && $sim['bargain_score'] > 5)
                            <div class="absolute bottom-0 left-0 bg-red-600 text-white text-[9px] font-black px-1.5 py-1 rounded-tr-xl shadow-lg z-10 flex items-center gap-1">
                                <i data-lucide="trending-down" class="w-3 h-3"></i>
                                約{{ round($sim['bargain_score']) }}%お得！
                            </div>
                        @endif

                        <div class="absolute bottom-2 right-2 bg-black/60 backdrop-blur-sm text-white px-2 py-0.5 rounded text-[10px] font-black">
                            {{ $sim['total_price'] }}万円
                        </div>
                    </div>
                    <div class="p-3 flex flex-col flex-1">
                        <div class="flex gap-2 text-[9px] font-bold text-gray-400 mb-1">
                            <span>{{ $sim['model_year'] }}</span>
                            <span>{{ $sim['mileage'] }}</span>
                        </div>
                        <h4 class="text-xs font-black text-gray-800 leading-tight line-clamp-2 mb-2 group-hover:text-blue-600 transition-colors h-[2.5em]">
                            {{ $sim['name'] }}
                        </h4>
                        <div class="mt-auto text-[9px] text-gray-400 flex items-center gap-1 border-t border-gray-100 pt-2">
                            <i data-lucide="map-pin" class="w-3 h-3"></i> <span class="truncate">{{ $sim['prefecture'] }}</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
    @endif

    {{-- 最近見た車両（JSで動的生成） --}}
    <section id="history-section" class="hidden">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2">
                <div class="p-2 bg-gray-100 rounded-lg text-gray-600">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                </div>
                <h3 class="text-lg font-black text-gray-900">最近見た車両</h3>
            </div>
        </div>
        
        <div id="history-widget" class="flex gap-4 overflow-x-auto pb-4 snap-x snap-mandatory scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
            {{-- ここにJSでカードが挿入されます --}}
        </div>
    </section>

    {{-- PV・SEO爆上げ：関連する条件で探す（掛け合わせリンク） --}}
    @if(isset($dynamicLinks) && count($dynamicLinks) > 0)
    <section>
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2">
                <div class="p-2 bg-green-50 rounded-lg text-green-600">
                    <i data-lucide="search" class="w-5 h-5"></i>
                </div>
                <h3 class="text-lg font-black text-gray-900">関連する条件で探す</h3>
            </div>
        </div>
        
        <div class="flex flex-wrap gap-2 sm:gap-3">
            @foreach($dynamicLinks as $link)
                <a href="{{ $link['url'] }}" class="inline-flex items-center bg-white border border-gray-200 text-gray-600 hover:text-blue-600 hover:border-blue-400 hover:bg-blue-50 hover:shadow-md px-4 py-2 sm:py-2.5 rounded-xl text-xs font-bold transition group">
                    <i data-lucide="{{ $link['icon'] ?? 'search' }}" class="w-3.5 h-3.5 inline-block mr-1.5 text-gray-400 group-hover:text-blue-500 transition-colors"></i>
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>
    </section>
    @endif
</div>