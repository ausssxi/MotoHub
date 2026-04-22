<x-layout>
    <x-slot:title>駅から探すバイク駐車場・駐輪場【{{ $totalMajor + $totalOthers }}駅対応】 | MotoHub</x-slot:title>
    <x-slot:metaDescription>全国{{ $totalMajor + $totalOthers }}駅周辺のバイク駐車場・駐輪場を検索。東京・大阪・名古屋など主要{{ $totalMajor }}駅に加え、駐車場が充実した{{ $totalOthers }}駅の料金相場・空き状況を比較できます。</x-slot:metaDescription>

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
                    主要{{ $totalMajor }}駅 + その他{{ $totalOthers }}駅、合計{{ $totalMajor + $totalOthers }}駅の周辺駐車場を検索できます。
                </p>
            </div>

            {{-- ================================================================ --}}
            {{-- セクション1: 主要駅 --}}
            {{-- ================================================================ --}}
            <div class="mb-10">
                <div class="flex items-center gap-2 mb-4">
                    <span class="inline-flex items-center gap-1.5 bg-green-600 text-white text-[10px] font-black px-2.5 py-1 rounded-full uppercase tracking-wider">
                        <i data-lucide="star" class="w-3 h-3"></i> MAJOR
                    </span>
                    <h2 class="text-base font-black text-gray-900">主要{{ $totalMajor }}駅</h2>
                </div>

                <div class="space-y-4">
                    @foreach($majorByPrefecture as $prefecture => $stations)
                    <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6">
                        <h3 class="text-sm font-black text-gray-900 mb-3 flex items-center gap-2">
                            <i data-lucide="map-pin" class="w-4 h-4 text-green-500"></i>
                            {{ $prefecture }}
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach($stations as $station)
                            <a href="{{ route('parking.station.show', $station) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-green-50 border border-green-100 text-xs font-bold text-green-800 hover:bg-green-100 hover:border-green-300 transition-colors">
                                <i data-lucide="train-front" class="w-3 h-3"></i>
                                {{ $station->name }}駅
                                @if($station->bike_parkings_count > 0)
                                <span class="text-[10px] text-green-500">({{ $station->bike_parkings_count }})</span>
                                @endif
                            </a>
                            @endforeach
                        </div>
                    </section>
                    @endforeach
                </div>
            </div>

            {{-- ================================================================ --}}
            {{-- セクション2: その他の駅（駐車場5件以上） --}}
            {{-- ================================================================ --}}
            @if($othersByPrefecture->isNotEmpty())
            <div class="mb-10">
                <div class="flex items-center gap-2 mb-2">
                    <span class="inline-flex items-center gap-1.5 bg-gray-500 text-white text-[10px] font-black px-2.5 py-1 rounded-full uppercase tracking-wider">
                        <i data-lucide="train-front" class="w-3 h-3"></i> MORE
                    </span>
                    <h2 class="text-base font-black text-gray-900">その他の駅（駐車場5件以上）</h2>
                </div>
                <p class="text-xs text-gray-400 mb-4 ml-1">下記の駅にも駐車場情報が充実しています</p>

                <div class="space-y-2" x-data="{ openPref: null }">
                    @foreach($othersByPrefecture as $prefecture => $stations)
                    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                        <button type="button"
                                @click="openPref = openPref === '{{ $prefecture }}' ? null : '{{ $prefecture }}'"
                                class="w-full flex items-center justify-between px-4 py-3 sm:px-5 sm:py-3.5 text-left hover:bg-gray-50 transition-colors group">
                            <span class="text-sm font-black text-gray-800 group-hover:text-green-700 transition-colors flex items-center gap-2">
                                <i data-lucide="map-pin" class="w-3.5 h-3.5 text-gray-400"></i>
                                {{ $prefecture }}
                                <span class="text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded-full">{{ $stations->count() }}駅</span>
                            </span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 transition-transform duration-200"
                               x-bind:class="{ 'rotate-180': openPref === '{{ $prefecture }}' }"></i>
                        </button>
                        <div x-show="openPref === '{{ $prefecture }}'"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             style="display: none;"
                             class="px-4 pb-4 sm:px-5 sm:pb-5">
                            <div class="flex flex-wrap gap-1.5 pt-1 border-t border-gray-50">
                                @foreach($stations as $station)
                                <a href="{{ route('parking.station.show', $station) }}"
                                   class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md bg-gray-50 border border-gray-100 text-[11px] font-bold text-gray-600 hover:bg-green-50 hover:border-green-200 hover:text-green-700 transition-colors">
                                    {{ $station->name }}駅
                                    @if($station->bike_parkings_count > 0)
                                    <span class="text-[9px] text-gray-400">({{ $station->bike_parkings_count }})</span>
                                    @endif
                                </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- 関連リンク --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
