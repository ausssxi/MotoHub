<x-layout>
    <x-slot:title>{{ $targetDate->format('Y年n月j日') }}のバイク売れ筋ランキング | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $targetDate->format('Y年n月j日') }}に売れたバイクTOP20。販売台数{{ number_format($ranking['totalSold']) }}台のデータから人気車種をランキング。</x-slot:metaDescription>
    <x-slot:canonical>{{ route('ranking.daily', $targetDate->toDateString()) }}</x-slot:canonical>
    <x-slot:navigation><x-navigation :showSearch="true" /></x-slot:navigation>

    <div class="max-w-4xl mx-auto px-4 py-6 sm:py-10">
        {{-- パンくず --}}
        <nav class="flex text-xs font-bold text-gray-400 mb-6">
            <ol class="flex items-center space-x-2">
                <li><a href="{{ route('bikes.index') }}" class="hover:text-black transition">HOME</a></li>
                <li><span class="text-gray-300">/</span></li>
                <li><a href="{{ route('ranking.index') }}" class="hover:text-black transition">ランキング</a></li>
                <li><span class="text-gray-300">/</span></li>
                <li class="text-gray-600">日別</li>
            </ol>
        </nav>

        <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-2 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
            バイク売れ筋ランキング
        </h1>
        <p class="text-sm text-gray-500 mb-6">日別の販売データランキング</p>

        {{-- タブ切替 --}}
        @include('ranking._tabs', ['active' => 'daily'])

        {{-- 直近7日間ナビ --}}
        @php
            $prevWeekDate = $weekStart->copy()->subDays(7)->toDateString();
            $nextWeekDate = $weekEnd->copy()->addDay()->toDateString();
            $canGoNext = $weekEnd->copy()->addDay()->lte(\Carbon\Carbon::today());
        @endphp
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 mb-6">
            {{-- 週切替ヘッダー --}}
            <div class="flex items-center justify-between mb-4">
                <a href="{{ route('ranking.daily', $prevWeekDate) }}" class="p-2 rounded-lg hover:bg-gray-100 transition" title="前の週">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h2 class="text-sm font-black text-gray-900">
                    {{ $weekStart->format('Y年n月j日') }}〜{{ $weekEnd->format('n月j日') }}
                </h2>
                @if($canGoNext)
                <a href="{{ route('ranking.daily', $nextWeekDate) }}" class="p-2 rounded-lg hover:bg-gray-100 transition" title="次の週">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
                @else
                <div class="p-2 w-9"></div>
                @endif
            </div>

            {{-- 7日間の横並び --}}
            <div class="grid grid-cols-7 gap-1 sm:gap-2">
                @foreach($weekDays as $day)
                @php
                    $isSelected = $targetDate->toDateString() === $day['date'];
                    $isSun = $day['dow'] === '日';
                    $isSat = $day['dow'] === '土';
                @endphp
                @if($day['isFuture'])
                <div class="flex flex-col items-center justify-center py-2 sm:py-3 rounded-xl text-gray-300">
                    <span class="text-[10px] font-bold">{{ $day['dow'] }}</span>
                    <span class="text-sm font-black">{{ $day['label'] }}</span>
                    <span class="text-[10px] font-bold mt-0.5">-</span>
                </div>
                @else
                <a href="{{ route('ranking.daily', $day['date']) }}"
                   class="flex flex-col items-center justify-center py-2 sm:py-3 rounded-xl transition {{ $isSelected ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-gray-50' }}">
                    <span class="text-[10px] font-bold {{ $isSelected ? 'text-blue-200' : ($isSun ? 'text-red-400' : ($isSat ? 'text-blue-400' : 'text-gray-400')) }}">{{ $day['dow'] }}</span>
                    <span class="text-sm font-black {{ $isSelected ? '' : 'text-gray-900' }}">{{ $day['label'] }}</span>
                    <span class="text-[10px] font-bold mt-0.5 {{ $isSelected ? 'text-blue-100' : 'text-gray-400' }}">
                        {{ $day['count'] > 0 ? number_format($day['count']) . '台' : '-' }}
                    </span>
                </a>
                @endif
                @endforeach
            </div>
        </div>

        {{-- 日付ヘッダー --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <p class="text-xs font-bold text-gray-400">{{ $targetDate->format('Y年n月j日') }}（{{ ['日','月','火','水','木','金','土'][$targetDate->dayOfWeek] }}）</p>
                    <p class="text-3xl font-black text-gray-900">{{ number_format($ranking['totalSold']) }}<span class="text-base text-gray-400 ml-1">台販売</span></p>
                </div>
            </div>
        </div>

        @if($ranking['totalSold'] > 0)
        {{-- 車種ランキング --}}
        @include('ranking._model_ranking', ['modelRanking' => $ranking['modelRanking'], 'limit' => 20])

        {{-- メーカー別 --}}
        @include('ranking._maker_ranking', ['makerRanking' => $ranking['makerRanking']])

        {{-- 価格帯別 --}}
        @if($ranking['priceRanges']->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                価格帯別
            </h2>
            @php $maxPrice = $ranking['priceRanges']->max('cnt') ?: 1; @endphp
            <div class="space-y-2">
                @foreach($ranking['priceRanges'] as $range)
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-gray-600 w-24 text-right flex-shrink-0">{{ $range->price_range }}</span>
                    <div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
                        <div class="bg-green-400 h-full rounded-full flex items-center justify-end pr-2 transition-all"
                             style="width: {{ max(($range->cnt / $maxPrice) * 100, 5) }}%">
                            <span class="text-[10px] font-black text-white">{{ number_format($range->cnt) }}</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- 排気量帯別 --}}
        @if($ranking['displacementRanges']->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                排気量帯別
            </h2>
            @php $maxCc = $ranking['displacementRanges']->max('cnt') ?: 1; @endphp
            <div class="space-y-2">
                @foreach($ranking['displacementRanges'] as $range)
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-gray-600 w-24 text-right flex-shrink-0">{{ $range->cc_range }}</span>
                    <div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
                        <div class="bg-purple-400 h-full rounded-full flex items-center justify-end pr-2 transition-all"
                             style="width: {{ max(($range->cnt / $maxCc) * 100, 5) }}%">
                            <span class="text-[10px] font-black text-white">{{ number_format($range->cnt) }}</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
        @else
        <div class="text-center py-16 text-gray-400">
            <p class="text-lg font-bold">この日のデータはありません</p>
            <p class="text-sm mt-2">別の日付を選んでください</p>
        </div>
        @endif

        {{-- 注記 --}}
        <p class="text-[10px] text-gray-400 mt-6 text-center">※MotoHub掲載車両の売り切れデータに基づく集計です</p>

        @include('ranking._related_links')
    </div>
</x-layout>
