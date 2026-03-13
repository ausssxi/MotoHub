<x-layout>
    <x-slot:title>{{ $prefecture }}のバイク駐車場・駐輪場一覧 | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $prefecture }}のバイク駐車場・駐輪場を{{ $parkings->count() }}件掲載。料金・設備・レビューで比較できます。</x-slot:metaDescription>

    <x-slot:styles>
        @php
            $breadcrumbSchema = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'ホーム', 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'バイク駐車場マップ', 'item' => route('parking.index')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $prefecture],
                ],
            ];
        @endphp
        <script type="application/ld+json">
            {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
        </script>
    </x-slot:styles>

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
                    <li><a href="{{ route('parking.index') }}" class="hover:text-gray-600 transition-colors">駐車場マップ</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $prefecture }}</span></li>
                </ol>
            </nav>

            {{-- ヘッダー --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-4">
                    {{ $prefecture }}のバイク駐車場・駐輪場一覧
                </h1>
                <div class="flex flex-wrap gap-4">
                    <div class="flex items-center gap-2 bg-green-50 text-green-700 text-sm font-bold px-4 py-2 rounded-xl border border-green-100">
                        <i data-lucide="square-parking" class="w-4 h-4"></i>
                        {{ $parkings->count() }} 件
                    </div>
                    @php $freeCount = $parkings->where('is_free', true)->count(); @endphp
                    @if($freeCount > 0)
                    <div class="flex items-center gap-2 bg-yellow-50 text-yellow-700 text-sm font-bold px-4 py-2 rounded-xl border border-yellow-100">
                        <i data-lucide="circle-check" class="w-4 h-4"></i>
                        無料 {{ $freeCount }} 件
                    </div>
                    @endif
                </div>
                <div class="mt-4">
                    <a href="{{ route('parking.index', ['lat' => $parkings->avg('latitude'), 'lng' => $parkings->avg('longitude')]) }}"
                       class="inline-flex items-center gap-2 text-xs font-bold text-green-600 hover:text-green-700 transition-colors">
                        <i data-lucide="map" class="w-4 h-4"></i>
                        {{ $prefecture }}の駐車場をマップで見る
                    </a>
                </div>
            </div>

            {{-- 市区町村別グループ --}}
            @php
                $grouped = $parkings->groupBy('city')->sortKeys();
            @endphp

            @foreach($grouped as $city => $cityParkings)
            <section class="mb-8">
                <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="map-pin" class="w-5 h-5 text-green-500"></i>
                    {{ $city ?: $prefecture }}
                    <span class="text-sm font-bold text-gray-400">({{ $cityParkings->count() }}件)</span>
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($cityParkings as $parking)
                    <a href="{{ route('parking.show', $parking) }}"
                       class="group bg-white rounded-2xl shadow-sm border border-gray-100 hover:border-green-200 hover:shadow-md p-5 transition-all duration-200 hover:-translate-y-0.5 block">
                        <div class="flex items-start justify-between mb-2">
                            <h3 class="text-sm font-black text-gray-900 group-hover:text-green-700 transition-colors line-clamp-2 flex-1">{{ $parking->name }}</h3>
                            @if($parking->avg_rating > 0)
                            <span class="shrink-0 ml-2 text-sm font-black text-yellow-500 flex items-center gap-0.5">
                                <i data-lucide="star" class="w-3.5 h-3.5 fill-current"></i>
                                {{ number_format($parking->avg_rating, 1) }}
                            </span>
                            @endif
                        </div>

                        <p class="text-xs text-gray-400 line-clamp-1 mb-3">{{ $parking->address }}</p>

                        <div class="flex flex-wrap gap-2 mb-3">
                            <span class="inline-flex items-center gap-1 bg-green-50 text-green-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-green-100">
                                {{ $parking->getParkingTypeLabel() }}
                            </span>
                            @if($parking->is_free)
                            <span class="inline-flex items-center gap-1 bg-yellow-50 text-yellow-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-yellow-100">
                                無料
                            </span>
                            @endif
                            @if($parking->is_covered)
                            <span class="inline-flex items-center gap-1 bg-gray-50 text-gray-600 text-[10px] font-bold px-2 py-0.5 rounded-full border border-gray-100">
                                屋根あり
                            </span>
                            @endif
                            @if($parking->available_24h)
                            <span class="inline-flex items-center gap-1 bg-gray-50 text-gray-600 text-[10px] font-bold px-2 py-0.5 rounded-full border border-gray-100">
                                24h
                            </span>
                            @endif
                        </div>

                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-gray-600">{{ $parking->getPriceDisplay() }}</span>
                            <span class="text-gray-300 group-hover:text-green-500 transition-colors">
                                <i data-lucide="chevron-right" class="w-4 h-4"></i>
                            </span>
                        </div>
                    </a>
                    @endforeach
                </div>
            </section>
            @endforeach

            {{-- 回遊リンク --}}
            <div class="mt-12">
                <x-cross-links :crossLinks="$crossLinks" />
            </div>
        </div>
    </div>
</x-layout>
