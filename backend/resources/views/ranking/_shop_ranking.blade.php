{{-- ショップ別販売ランキング --}}
@if(!empty($ranking['shopRanking']))
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
    <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        販売力のあるショップ TOP20
    </h2>
    <div class="overflow-x-auto -mx-6 px-6">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="pb-2 text-left text-[10px] font-bold text-gray-400 w-10">順位</th>
                    <th class="pb-2 text-left text-[10px] font-bold text-gray-400">ショップ名</th>
                    <th class="pb-2 text-left text-[10px] font-bold text-gray-400 hidden sm:table-cell">都道府県</th>
                    <th class="pb-2 text-right text-[10px] font-bold text-gray-400">販売台数</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ranking['shopRanking'] as $i => $shop)
                <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                    <td class="py-2.5 font-black text-gray-{{ $i < 3 ? '900' : '500' }} text-xs">
                        @if($i === 0) 🥇
                        @elseif($i === 1) 🥈
                        @elseif($i === 2) 🥉
                        @else {{ $i + 1 }}
                        @endif
                    </td>
                    <td class="py-2.5">
                        <a href="{{ route('shops.show', $shop['shop_id']) }}" class="text-xs font-bold text-gray-800 hover:text-blue-600 transition-colors">
                            {{ $shop['name'] }}
                        </a>
                    </td>
                    <td class="py-2.5 text-xs text-gray-400 hidden sm:table-cell">{{ $shop['prefecture'] }}</td>
                    <td class="py-2.5 text-right">
                        <span class="text-xs font-black {{ $i < 3 ? 'text-emerald-600' : 'text-gray-700' }}">{{ number_format($shop['sold_count']) }}</span>
                        <span class="text-[10px] text-gray-400">台</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
