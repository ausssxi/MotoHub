{{-- メーカー別販売台数 --}}
@if($makerRanking->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
    <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        メーカー別販売台数
    </h2>

    @php $maxMaker = $makerRanking->max('sold_count') ?: 1; @endphp

    <div class="space-y-3">
        @foreach($makerRanking as $maker)
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg overflow-hidden bg-gray-50 flex-shrink-0 flex items-center justify-center">
                @if($maker['logo_url'])
                <img src="{{ $maker['logo_url'] }}" alt="" class="w-full h-full object-contain p-0.5" loading="lazy" onerror="this.style.display='none'">
                @endif
            </div>
            <span class="text-xs font-bold text-gray-700 w-20 flex-shrink-0">{{ $maker['name'] }}</span>
            <div class="flex-1 bg-gray-100 rounded-full h-7 overflow-hidden">
                <div class="bg-blue-400 h-full rounded-full flex items-center justify-end pr-2 transition-all"
                     style="width: {{ max(($maker['sold_count'] / $maxMaker) * 100, 8) }}%">
                    <span class="text-[10px] font-black text-white whitespace-nowrap">{{ number_format($maker['sold_count']) }}台</span>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif
