<x-layout>
    <x-slot:title>{{ $chain['name'] }}の中古バイク在庫一覧 - 全国{{ $shops->count() }}店舗 | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $chain['name'] }}全国{{ $shops->count() }}店舗のバイク在庫を一覧表示。現在{{ number_format($totalStock) }}台販売中。店舗ごとの在庫数・住所を確認して、お近くの{{ $chain['name'] }}を探せます。</x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- パンくず --}}
            <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('shops.map') }}" class="hover:text-gray-600 transition-colors">ショップマップ</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $chain['name'] }}</span></li>
                </ol>
            </nav>

            {{-- ヘッダー --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-4">
                    {{ $chain['name'] }}の中古バイク在庫一覧
                </h1>
                <div class="flex flex-wrap gap-4">
                    <div class="flex items-center gap-2 bg-blue-50 text-blue-700 text-sm font-bold px-4 py-2 rounded-xl border border-blue-100">
                        <i data-lucide="store" class="w-4 h-4"></i>
                        全国 {{ $shops->count() }} 店舗
                    </div>
                    <div class="flex items-center gap-2 bg-green-50 text-green-700 text-sm font-bold px-4 py-2 rounded-xl border border-green-100">
                        <i data-lucide="bike" class="w-4 h-4"></i>
                        在庫 {{ number_format($totalStock) }} 台
                    </div>
                </div>
            </div>

            {{-- 都道府県別グループ --}}
            @php
                $grouped = $shops->groupBy('prefecture')->sortKeys();
            @endphp

            @foreach($grouped as $prefecture => $prefShops)
            <section class="mb-8">
                <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="map-pin" class="w-5 h-5 text-blue-500"></i>
                    {{ $prefecture ?: '不明' }}
                    <span class="text-sm font-bold text-gray-400">({{ $prefShops->count() }}店舗)</span>
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($prefShops->sortByDesc('listings_count') as $shop)
                    <a href="{{ route('shops.show', $shop->id) }}"
                       class="group bg-white rounded-2xl shadow-sm border border-gray-100 hover:border-blue-200 hover:shadow-md p-5 transition-all duration-200 hover:-translate-y-0.5 flex gap-4">
                        {{-- 店舗画像 --}}
                        <div class="shrink-0 w-14 h-14 rounded-xl bg-gray-100 overflow-hidden flex items-center justify-center border border-gray-50">
                            @if($shop->display_image_url)
                                <img src="{{ $shop->display_image_url }}" alt="{{ $shop->name }}" class="w-full h-full object-cover" loading="lazy" decoding="async">
                            @else
                                <i data-lucide="store" class="w-6 h-6 text-gray-300"></i>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-black text-gray-900 group-hover:text-blue-700 transition-colors line-clamp-1">{{ $shop->name }}</p>
                            <p class="text-xs text-gray-400 mt-1 line-clamp-1">{{ $shop->address }}</p>
                            <div class="flex items-center gap-1 mt-2">
                                <span class="inline-flex items-center gap-1 bg-green-50 text-green-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-green-100">
                                    <i data-lucide="bike" class="w-3 h-3"></i>
                                    {{ number_format($shop->listings_count) }}台
                                </span>
                            </div>
                        </div>

                        <div class="shrink-0 flex items-center text-gray-300 group-hover:text-blue-500 transition-colors">
                            <i data-lucide="chevron-right" class="w-5 h-5"></i>
                        </div>
                    </a>
                    @endforeach
                </div>
            </section>
            @endforeach

            @if($shops->isEmpty())
            <div class="py-16 text-center text-gray-400 font-bold text-sm bg-white rounded-2xl border border-dashed border-gray-200">
                現在、該当する店舗が見つかりません。
            </div>
            @endif

            {{-- 回遊リンク --}}
            <div class="mt-12">
                <x-cross-links :crossLinks="$crossLinks" />
            </div>
        </div>
    </div>
</x-layout>
