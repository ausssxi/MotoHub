{{-- 車種ランキング --}}
@if($modelRanking->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
    <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
        車種ランキング TOP{{ $limit }}
    </h2>

    @php $maxSold = $modelRanking->max('sold_count') ?: 1; @endphp

    <div class="space-y-1">
        @foreach($modelRanking->take($limit) as $i => $item)
        @php
            $rank = $i + 1;
            $medal = match($rank) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => null };
        @endphp
        <div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-gray-50 transition-colors group">
            {{-- 順位 --}}
            <div class="w-8 text-center flex-shrink-0">
                @if($medal)
                <span class="text-lg">{{ $medal }}</span>
                @else
                <span class="text-sm font-black {{ $rank <= 10 ? 'text-gray-700' : 'text-gray-400' }}">{{ $rank }}</span>
                @endif
            </div>

            @php
                $modelLink = $item['bike_model_id'] ? route('ranking.model_stats', $item['bike_model_id']) : ($item['seo_url'] ?? '#');
            @endphp

            {{-- 車両画像 --}}
            <a href="{{ $modelLink }}" class="w-12 h-12 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0 block">
                @if($item['image_url'])
                <img src="{{ $item['image_url'] }}" alt="" class="w-full h-full object-cover" loading="lazy" onerror="this.style.display='none'">
                @endif
            </a>

            {{-- 車種名・メーカー --}}
            <div class="flex-1 min-w-0">
                <a href="{{ $modelLink }}" class="text-sm font-bold text-gray-900 truncate block group-hover:text-blue-600 transition-colors">{{ $item['name'] }}</a>
                <p class="text-[11px] text-gray-400 font-bold">{{ $item['manufacturer'] }}</p>
            </div>

            {{-- 販売台数 --}}
            <div class="text-right flex-shrink-0">
                <span class="text-sm font-black {{ $rank <= 3 ? 'text-yellow-600' : 'text-gray-700' }}">{{ number_format($item['sold_count']) }}</span>
                <span class="text-[10px] text-gray-400">台</span>
            </div>

            {{-- バー --}}
            <div class="w-16 sm:w-24 bg-gray-100 rounded-full h-2 flex-shrink-0 hidden sm:block">
                <div class="h-full rounded-full {{ $rank <= 3 ? 'bg-yellow-400' : 'bg-gray-300' }}"
                     style="width: {{ max(($item['sold_count'] / $maxSold) * 100, 3) }}%"></div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif
