@props(['nearbyShops', 'latitude' => null, 'longitude' => null])

@if($nearbyShops->isNotEmpty())
<section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-2">
            <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                <i data-lucide="store" class="w-5 h-5"></i>
            </div>
            <h3 class="text-lg font-black text-gray-900">近くのバイクショップ</h3>
        </div>
        @if($latitude && $longitude)
        <a href="{{ route('shops.map', ['lat' => $latitude, 'lng' => $longitude]) }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1 group">
            もっと見る <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
        </a>
        @endif
    </div>

    <div class="flex gap-4 overflow-x-auto pb-4 snap-x snap-mandatory scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
        @foreach($nearbyShops as $s)
        <a href="{{ route('shops.show', $s->id) }}" class="snap-start shrink-0 w-56 sm:w-64 group bg-blue-50/50 rounded-xl p-4 border border-blue-100 hover:border-blue-300 hover:shadow-md transition-all duration-300 hover:-translate-y-0.5 block">
            <h4 class="text-xs font-black text-gray-800 group-hover:text-blue-700 transition-colors line-clamp-2 mb-2">{{ $s->name }}</h4>
            @if($s->prefecture)
            <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded-full mb-2">
                <i data-lucide="map-pin" class="w-3 h-3"></i>
                {{ $s->prefecture }}
            </span>
            @endif
            @if($s->listings_count > 0)
            <p class="text-[10px] font-bold text-blue-600">
                在庫 <span class="text-sm">{{ $s->listings_count }}</span> 台
            </p>
            @else
            <p class="text-[10px] text-gray-400">在庫情報なし</p>
            @endif
        </a>
        @endforeach
    </div>
</section>
@endif
