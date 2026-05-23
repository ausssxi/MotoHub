<x-layout>
    <x-slot:title>週間バイク売れ筋ランキング【{{ $startDate->format('n/j') }}〜{{ $endDate->format('n/j') }}】| MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $startDate->format('Y年n月j日') }}〜{{ $endDate->format('n月j日') }}の週間バイク売れ筋ランキング。販売台数{{ number_format($ranking['totalSold']) }}台のデータから人気車種をランキング。</x-slot:metaDescription>
    <x-slot:canonical>{{ route('ranking.weekly') }}</x-slot:canonical>
    <x-slot:publishedTime>2025-04-01T00:00:00+09:00</x-slot:publishedTime>
    <x-slot:modifiedTime>{{ now()->toIso8601String() }}</x-slot:modifiedTime>
    <x-slot:navigation><x-navigation :showSearch="true" /></x-slot:navigation>

    <div class="max-w-4xl mx-auto px-4 py-6 sm:py-10">
        {{-- パンくず --}}
        <nav class="flex text-xs font-bold text-gray-400 mb-6">
            <ol class="flex items-center space-x-2">
                <li><a href="{{ route('bikes.index') }}" class="hover:text-black transition">HOME</a></li>
                <li><span class="text-gray-300">/</span></li>
                <li><a href="{{ route('ranking.index') }}" class="hover:text-black transition">ランキング</a></li>
                <li><span class="text-gray-300">/</span></li>
                <li class="text-gray-600">週間</li>
            </ol>
        </nav>

        <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-2 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
            バイク売れ筋ランキング
        </h1>
        <p class="text-sm text-gray-500 mb-6">週間の販売データランキング</p>

        {{-- タブ切替 --}}
        @include('ranking._tabs', ['active' => 'weekly'])

        {{-- 期間ヘッダー --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <div>
                    <p class="text-xs font-bold text-gray-400">{{ $startDate->format('Y年n月j日') }}〜{{ $endDate->format('n月j日') }}</p>
                    <p class="text-3xl font-black text-gray-900">{{ number_format($ranking['totalSold']) }}<span class="text-base text-gray-400 ml-1">台販売</span></p>
                </div>
            </div>
        </div>

        {{-- CSVダウンロードボタン --}}
        @include('ranking._download_button', ['downloadUrl' => route('ranking.download', ['period' => 'weekly'])])

        {{-- 車種ランキング --}}
        @include('ranking._model_ranking', ['modelRanking' => $ranking['modelRanking'], 'limit' => 30])

        {{-- メーカー別 --}}
        @include('ranking._maker_ranking', ['makerRanking' => $ranking['makerRanking']])

        {{-- チャート（メーカー別シェア + 価格帯別） --}}
        @include('ranking._charts')

        {{-- ショップ別販売ランキング --}}
        @include('ranking._shop_ranking')

        {{-- 注記 --}}
        <p class="text-[10px] text-gray-400 mt-6 text-center">※MotoHub掲載車両の売り切れデータに基づく集計です</p>

        {{-- データ引用セクション --}}
        @include('ranking._citation')

        @include('ranking._related_links')
    </div>
</x-layout>
