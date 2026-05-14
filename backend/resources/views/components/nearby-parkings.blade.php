@props(['nearbyParkings', 'latitude' => null, 'longitude' => null, 'prefecture' => null])

@if($nearbyParkings->isNotEmpty())
<section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-8">
    <div class="flex items-center justify-between mb-4 sm:mb-6">
        <div class="flex items-center gap-2">
            <div class="p-2 bg-green-50 rounded-lg text-green-600">
                <i data-lucide="square-parking" class="w-5 h-5"></i>
            </div>
            <h3 class="text-lg font-black text-gray-900">近くのバイク駐車場</h3>
        </div>
        @if($prefecture)
        <a href="{{ route('parking.area.prefecture', $prefecture) }}" class="text-xs font-bold text-green-600 hover:text-green-800 flex items-center gap-1 group">
            もっと見る <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
        </a>
        @elseif($latitude && $longitude)
        <a href="{{ route('parking.index', ['lat' => $latitude, 'lng' => $longitude]) }}" class="text-xs font-bold text-green-600 hover:text-green-800 flex items-center gap-1 group">
            もっと見る <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
        </a>
        @endif
    </div>

    <div class="flex gap-3 overflow-x-auto pb-3 snap-x snap-mandatory scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
        @foreach($nearbyParkings as $p)
        <a href="{{ route('parking.show', $p->id) }}" class="snap-start shrink-0 w-48 sm:w-64 group bg-green-50/50 rounded-xl p-3 sm:p-4 border border-green-100 hover:border-green-300 hover:shadow-md transition-all duration-300 hover:-translate-y-0.5 block">
            <div class="flex items-start justify-between mb-1.5">
                <h4 class="text-[11px] sm:text-xs font-black text-gray-800 group-hover:text-green-700 transition-colors line-clamp-1 flex-1 mr-2">{{ $p->name }}</h4>
                @if($p->avg_rating > 0)
                <div class="shrink-0 flex items-center gap-0.5 bg-yellow-50 text-yellow-600 text-[10px] font-black px-1.5 py-0.5 rounded">
                    <i data-lucide="star" class="w-3 h-3 fill-current"></i>
                    {{ number_format($p->avg_rating, 1) }}
                </div>
                @endif
            </div>
            <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 text-[10px] font-bold px-2 py-0.5 rounded-full mb-2">
                {{ $p->getParkingTypeLabel() }}
            </span>
            <p class="text-[10px] text-gray-500 mb-2 line-clamp-1">{{ $p->getPriceDisplay() ?: '料金情報なし' }}</p>
            <div class="flex items-center gap-2 text-[9px] text-gray-400">
                @if($p->is_covered)<span class="flex items-center gap-0.5"><i data-lucide="umbrella" class="w-3 h-3"></i>屋根</span>@endif
                @if($p->is_locked)<span class="flex items-center gap-0.5"><i data-lucide="lock" class="w-3 h-3"></i>施錠</span>@endif
                @if($p->reviews_count > 0)<span>レビュー{{ $p->reviews_count }}件</span>@endif
            </div>
        </a>
        @endforeach
    </div>
</section>
@endif
