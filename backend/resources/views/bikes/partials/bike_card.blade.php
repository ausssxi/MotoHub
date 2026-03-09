<div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-2xl transition duration-500 flex flex-col group border border-gray-100 relative cursor-pointer bike-card">
    
    <a href="{{ route('bikes.show', $listing['id']) }}" class="absolute inset-0 z-10"></a>
    
    <div class="aspect-[4/3] relative overflow-hidden bg-gray-50">
        {{-- ★追加: 最初から見えている画像か、後から読み込む画像かを判定 --}}
        @php
            $isFirstView = $isFirstView ?? false;
            $loadAttr = $isFirstView ? 'fetchpriority="high"' : 'loading="lazy" decoding="async"';
        @endphp

        @if(!empty($listing['images']) && isset($listing['images'][0]))
            {{-- ★修正: 判定した属性($loadAttr)を出力する --}}
            <img src="{{ $listing['images'][0] }}"
                 class="bike-img w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                 alt="{{ $listing['name'] }}"
                 width="400" height="300"
                 onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop'; this.classList.add('grayscale', 'opacity-50');"
                 {!! $loadAttr !!}>
        @else
            <img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop"
                 class="bike-img w-full h-full object-cover grayscale opacity-50 group-hover:scale-105 transition-transform duration-500"
                 alt="No Image"
                 width="400" height="300"
                 {!! $loadAttr !!}>
            <div class="absolute inset-0 flex items-center justify-center">
                <i data-lucide="image-off" class="w-10 h-10 text-white/50"></i>
            </div>
        @endif

        @if($listing['bargain_score'] > 5)
        <div class="absolute bottom-0 left-0 bg-red-600 text-white text-[10px] font-black px-2 py-1.5 rounded-tr-xl shadow-lg z-20 flex items-center gap-1 animate-pulse">
            <i data-lucide="trending-down" class="w-3.5 h-3.5"></i>
            相場より約{{ round($listing['bargain_score']) }}%お得！
        </div>
        @endif
        
        <button class="compare-btn absolute top-3 left-3 z-20 w-10 h-10 bg-white/90 backdrop-blur rounded-full flex items-center justify-center text-gray-400 border border-gray-100 shadow-sm transition hover:scale-110 active:scale-95" data-id="{{ $listing['id'] }}">
            <i data-lucide="layers" class="w-5 h-5"></i>
        </button>
        
        <button class="wishlist-btn absolute top-3 right-3 z-20 w-10 h-10 bg-white/90 backdrop-blur rounded-full flex items-center justify-center text-gray-400 shadow-sm border border-gray-100" data-id="{{ $listing['id'] }}">
            <i data-lucide="heart" class="w-5 h-5"></i>
        </button>
        
        {{-- 掲載元サイトのバッジ --}}
        <div class="absolute bottom-3 right-3 z-20 bg-black/50 backdrop-blur-sm px-2 py-1 rounded-lg flex items-center gap-1.5 border border-white/10 shadow-sm">
            @if(isset($listing['source_icon_key']) && $listing['source_icon_key'] !== 'default')
                {{-- ★最適化: アイコン画像も遅延読み込み --}}
                <img src="{{ asset('images/sites/' . $listing['source_icon_key'] . '.png') }}" class="w-3 h-3 rounded-sm brightness-110" alt="{{ $listing['source'] ?? '外部サイト' }}" loading="lazy" decoding="async">
            @else
                <i data-lucide="external-link" class="w-3 h-3 text-white/80"></i>
            @endif
            <span class="text-[8px] font-black text-white/90">{{ $listing['source'] ?? $listing['site_name'] ?? '外部サイト' }}</span>
        </div>
    </div>

    <div class="p-5 flex-grow flex flex-col">
        <div class="flex items-center gap-2 mb-2 flex-wrap">
            <span class="text-[9px] font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded uppercase">{{ $listing['maker'] }}</span>
            <span class="text-[9px] font-black text-orange-600 bg-orange-50 px-2 py-0.5 rounded uppercase">{{ $listing['category'] }}</span>
            <span class="text-[9px] font-black text-green-600 bg-green-50 px-2 py-0.5 rounded uppercase">{{ $listing['condition'] }}</span>
            <span class="text-[9px] font-black text-purple-600 bg-purple-50 px-2 py-0.5 rounded uppercase">{{ $listing['prefecture'] }}</span>
        </div>

        @if(!empty($listing['tags']) && count($listing['tags']) > 0)
        <div class="flex flex-wrap gap-1 mb-2.5">
            @php
                $tagsArray = is_iterable($listing['tags']) ? (is_object($listing['tags']) ? $listing['tags']->toArray() : $listing['tags']) : [];
                $displayTags = array_slice($tagsArray, 0, 4);
            @endphp
            
            @foreach($displayTags as $tag)
                @php $tagName = is_array($tag) ? ($tag['name'] ?? '') : ($tag->name ?? ''); @endphp
                @if($tagName)
                <span class="inline-flex items-center px-1.5 py-0.5 bg-gray-50 text-gray-500 text-[8px] font-bold rounded border border-gray-100">
                    <span class="text-blue-400 mr-0.5">#</span>{{ $tagName }}
                </span>
                @endif
            @endforeach
            
            @if(count($tagsArray) > 4)
                <span class="inline-flex items-center px-1.5 py-0.5 text-gray-400 text-[8px] font-bold">
                    +{{ count($tagsArray) - 4 }}
                </span>
            @endif
        </div>
        @endif

        <h3 class="text-sm font-black text-gray-800 mb-4 line-clamp-2 leading-tight group-hover:text-blue-600 transition-colors">{{ $listing['name'] }}</h3>
        
        <div class="grid grid-cols-2 gap-y-2.5 gap-x-2 text-[10px] font-bold text-gray-400 mb-6">
            <div class="flex items-center gap-1.5"><i data-lucide="calendar" class="w-3.5 h-3.5 text-gray-300"></i><span>{{ $listing['model_year'] }}</span></div>
            <div class="flex items-center gap-1.5"><i data-lucide="gauge" class="w-3.5 h-3.5 text-gray-300"></i><span>{{ $listing['mileage'] }}</span></div>
            <div class="flex items-center gap-1.5"><i data-lucide="droplet" class="w-3.5 h-3.5 text-gray-300"></i><span>{{ $listing['displacement'] }}</span></div>
            <div class="flex items-center gap-1.5"><i data-lucide="wrench" class="w-3.5 h-3.5 text-gray-300"></i><span class="truncate">修復歴: {{ $listing['repair_history'] }}</span></div>
        </div>

        <div class="mt-auto bg-gray-50 p-4 rounded-xl border border-gray-100 group-hover:bg-blue-50 transition duration-300">
            <div class="flex justify-between items-end mb-3">
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
            <div class="pt-2 border-t border-gray-200/50 flex items-center gap-1.5 text-[9px] text-gray-400 font-bold group-hover:text-blue-400 transition-colors">
                <i data-lucide="store" class="w-3 h-3"></i><span class="truncate">{{ $listing['store_name'] }}</span>
            </div>
        </div>
    </div>
</div>