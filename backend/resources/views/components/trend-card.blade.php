<a href="{{ route('bikes.search', ['bike_model_id' => $item['model_id']]) }}" class="bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-gray-100 hover:shadow-md hover:border-blue-200 transition-all group flex items-center gap-4 relative overflow-hidden">
    
    {{-- ランキングバッジ --}}
    <div class="absolute top-0 left-0 w-8 h-8 flex items-center justify-center font-black text-xs rounded-br-xl z-10 {{ $rankColor() }}">
        {{ $index + 1 }}
    </div>

    {{-- 画像 --}}
    <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl bg-gray-50 overflow-hidden flex-shrink-0 relative border border-gray-100 mt-2 ml-2">
        @if($item['image_url'])
            <img src="{{ $item['image_url'] }}" alt="{{ $item['model_name'] }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
        @else
            <div class="w-full h-full flex items-center justify-center text-gray-300">
                <i data-lucide="bike" class="w-8 h-8"></i>
            </div>
        @endif
    </div>

    {{-- 情報 --}}
    <div class="flex-1 min-w-0 py-2">
        <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-0.5">{{ $item['maker_name'] }}</div>
        <h3 class="text-sm sm:text-base font-black text-gray-900 truncate group-hover:text-blue-600 transition-colors mb-3">
            {{ $item['model_name'] }}
        </h3>
        
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-2">
            <div>
                <span class="text-[10px] font-bold text-gray-400">現在平均:</span>
                <span class="text-lg font-black text-gray-900 ml-1">{{ $item['current_price'] }}<span class="text-[10px] font-bold text-gray-500 ml-0.5">万円</span></span>
            </div>
            <div class="text-left sm:text-right">
                <div class="{{ $bgClass() }} {{ $colorClass() }} px-2 py-1 rounded-lg inline-flex items-center gap-1">
                    <i data-lucide="{{ $icon() }}" class="w-3.5 h-3.5"></i>
                    <span class="text-sm font-black">{{ $sign() }}{{ $item['diff'] }}<span class="text-[10px] ml-0.5">万円</span></span>
                </div>
                <div class="text-[9px] font-bold text-gray-400 mt-1 sm:text-right">
                    過去比: {{ $sign() }}{{ $item['rate'] }}%
                </div>
            </div>
        </div>
    </div>
</a>