<x-layout>
    <x-slot:title>エリアからバイク駐車場を探す | MotoHub</x-slot:title>
    <x-slot:metaDescription>全国{{ $totalCount }}件のバイク駐車場・駐輪場を都道府県別に一覧表示。料金相場や件数を確認して、お近くの駐車場を探せます。</x-slot:metaDescription>

    <x-slot:styles>
        @php
            $breadcrumbSchema = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'ホーム', 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'バイク駐車場マップ', 'item' => route('parking.index')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => 'エリアから探す'],
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
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- パンくず --}}
            <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('parking.index') }}" class="hover:text-gray-600 transition-colors">駐車場マップ</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">エリアから探す</span></li>
                </ol>
            </nav>

            {{-- ヘッダー --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-3">
                    エリアからバイク駐車場を探す
                </h1>
                <p class="text-sm text-gray-500">
                    全国<span class="font-bold text-green-600">{{ number_format($totalCount) }}</span>件のバイク駐車場・駐輪場を都道府県別に掲載しています。
                </p>
            </div>

            {{-- 地方ブロック別 --}}
            @foreach($regions as $regionName => $prefs)
            <section class="mb-8">
                <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="map-pin" class="w-5 h-5 text-green-500"></i>
                    {{ $regionName }}
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach($prefs as $pref => $coords)
                    @php $count = $prefCounts[$pref] ?? 0; @endphp
                    <a href="{{ $count > 0 ? route('parking.area.prefecture', $pref) : '#' }}"
                       class="bg-white rounded-xl border border-gray-100 p-4 hover:border-green-200 hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 block {{ $count === 0 ? 'opacity-50 pointer-events-none' : '' }}">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-black text-gray-800">{{ $pref }}</span>
                            <span class="text-xs font-bold {{ $count > 0 ? 'text-green-600' : 'text-gray-300' }}">
                                {{ number_format($count) }}件
                            </span>
                        </div>
                    </a>
                    @endforeach
                </div>
            </section>
            @endforeach

            {{-- マップへのリンク --}}
            <div class="text-center mt-8">
                <a href="{{ route('parking.index') }}"
                   class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-bold text-sm py-3 px-8 rounded-xl transition-colors shadow-sm">
                    <i data-lucide="map" class="w-4 h-4"></i>
                    マップで駐車場を探す
                </a>
            </div>
        </div>
    </div>
</x-layout>
