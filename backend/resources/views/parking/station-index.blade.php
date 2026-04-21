<x-layout>
    <x-slot:title>駅から探すバイク駐車場・駐輪場【主要{{ $totalStations }}駅】 | MotoHub</x-slot:title>
    <x-slot:metaDescription>全国{{ $totalStations }}の主要駅周辺のバイク駐車場・駐輪場を検索。東京・大阪・名古屋など主要ターミナル駅の料金相場・空き状況を一覧で比較できます。</x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- パンくず --}}
            <nav class="overflow-x-auto text-xs font-bold text-gray-400 mb-6 scrollbar-hide" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 whitespace-nowrap">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('parking.index') }}" class="hover:text-gray-600 transition-colors">駐車場マップ</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">駅から探す</span></li>
                </ol>
            </nav>

            {{-- ヘッダー --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-2">
                    駅から探すバイク駐車場・駐輪場
                </h1>
                <p class="text-sm text-gray-500">
                    全国{{ $totalStations }}の主要駅周辺のバイク駐車場を検索できます。
                </p>
            </div>

            {{-- 都道府県別駅一覧 --}}
            <div class="space-y-6">
                @foreach($stationsByPrefecture as $prefecture => $stations)
                <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6">
                    <h2 class="text-sm font-black text-gray-900 mb-3 flex items-center gap-2">
                        <i data-lucide="map-pin" class="w-4 h-4 text-green-500"></i>
                        {{ $prefecture }}
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach($stations as $station)
                        <a href="{{ route('parking.station.show', $station) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-gray-50 border border-gray-100 text-xs font-bold text-gray-700 hover:bg-green-50 hover:border-green-200 hover:text-green-700 transition-colors">
                            <i data-lucide="train-front" class="w-3 h-3"></i>
                            {{ $station->name }}駅
                            @if($station->bike_parkings_count > 0)
                            <span class="text-[10px] text-gray-400">({{ $station->bike_parkings_count }}件)</span>
                            @endif
                        </a>
                        @endforeach
                    </div>
                </section>
                @endforeach
            </div>

            {{-- 関連リンク --}}
            <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <a href="{{ route('parking.area.index') }}"
                   class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md hover:-translate-y-0.5 transition-all group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-green-50 text-green-600 flex items-center justify-center">
                            <i data-lucide="map" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="text-sm font-black text-gray-800 group-hover:text-green-600 transition-colors">エリアから探す</p>
                            <p class="text-xs text-gray-400">都道府県・市区町村で検索</p>
                        </div>
                    </div>
                </a>
                <a href="{{ route('parking.index') }}"
                   class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md hover:-translate-y-0.5 transition-all group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                            <i data-lucide="square-parking" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="text-sm font-black text-gray-800 group-hover:text-blue-600 transition-colors">駐車場マップ</p>
                            <p class="text-xs text-gray-400">地図から駐車場を探す</p>
                        </div>
                    </div>
                </a>
            </div>

            {{-- データ出典 --}}
            <p class="text-[10px] text-gray-300 mt-6 text-center">駅データ: 国土数値情報（鉄道データ）国土交通省</p>
        </div>
    </div>
</x-layout>
