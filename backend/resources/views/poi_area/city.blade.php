<x-layout>
    <x-slot:title>{{ $prefecture }}{{ $city }}の{{ $label }}（{{ number_format($count) }}件） - MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $prefecture }}{{ $city }}の{{ $label }}{{ number_format($count) }}件を一覧。住所・営業時間・ブランド情報を掲載しています。</x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- パンくず --}}
            <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route($routePrefix.'.index') }}" class="hover:text-gray-600 transition-colors">{{ $label }}</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route($routePrefix.'.prefecture', $prefecture) }}" class="hover:text-gray-600 transition-colors">{{ $prefecture }}</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $city }}</span></li>
                </ol>
            </nav>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h1 class="text-xl font-black text-gray-900 mb-2">{{ $prefecture }}{{ $city }}の{{ $label }}（{{ number_format($count) }}件）</h1>

                {{-- もう一方の種別ページへのリンク（同一市区町村に存在するときだけ・404回避） --}}
                @if($otherCount > 0)
                <p class="mb-6">
                    <a href="{{ route($otherPrefix.'.city', [$prefecture, $city]) }}" class="inline-flex items-center gap-1 text-xs font-bold text-purple-700 hover:underline">
                        <i data-lucide="arrow-left-right" class="w-3.5 h-3.5"></i>
                        {{ $city }}の{{ $otherLabel }}を見る
                    </a>
                </p>
                @else
                <div class="mb-6"></div>
                @endif

                <ul class="divide-y divide-gray-50">
                    @foreach($items as $it)
                    <li class="py-2.5">
                        <p class="text-sm text-gray-900 font-bold">{{ $it['display'] }}</p>
                        @if($it['brand'])
                        <p class="text-[11px] text-gray-500 mt-0.5"><i data-lucide="tag" class="inline w-3 h-3"></i> {{ $it['brand'] }}</p>
                        @endif
                        @if($it['address'])
                        <p class="text-[11px] text-gray-500 mt-0.5"><i data-lucide="map-pin" class="inline w-3 h-3"></i> {{ $it['address'] }}</p>
                        @endif
                        @if($it['opening_hours'])
                        <p class="text-[11px] text-gray-400 mt-0.5"><i data-lucide="clock" class="inline w-3 h-3"></i> {{ $it['opening_hours'] }}</p>
                        @endif
                    </li>
                    @endforeach
                </ul>
            </div>

            {{-- 近隣の市区町村にある最寄り（掲載が少ないページのみ・0件なら非表示） --}}
            @if(!empty($nearby))
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mt-6">
                <h2 class="text-sm font-black text-gray-900 mb-3">近隣の市区町村にある最寄りの{{ $label }}</h2>
                <ul class="divide-y divide-gray-50">
                    @foreach($nearby as $n)
                    <li class="py-2.5">
                        <a href="{{ route($routePrefix.'.city', [$n['prefecture'], $n['city']]) }}" class="text-sm text-purple-700 font-bold hover:underline">{{ $n['prefecture'] }}{{ $n['city'] }}</a>
                        <span class="text-[11px] text-gray-400 ml-1">約{{ number_format($n['km'], 1) }}km</span>
                        <p class="text-[11px] text-gray-500 mt-0.5"><i data-lucide="map-pin" class="inline w-3 h-3"></i> {{ $n['display'] }}</p>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- 回遊リンク --}}
            <div class="mt-6">
                <x-cross-links :crossLinks="$crossLinks" />
            </div>

            {{-- データ出典 --}}
            <div class="mt-6 text-[11px] text-gray-400 leading-relaxed">
                <p>施設情報: © OpenStreetMap contributors</p>
                <p>行政区域: 国土数値情報（行政区域データ）国土交通省</p>
            </div>
        </div>
    </div>

    {{-- JSON-LD: BreadcrumbList --}}
    @php
        $ldFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $ldBreadcrumb = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'HOME', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $label, 'item' => route($routePrefix.'.index')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $prefecture, 'item' => route($routePrefix.'.prefecture', $prefecture)],
                ['@type' => 'ListItem', 'position' => 4, 'name' => $city, 'item' => route($routePrefix.'.city', [$prefecture, $city])],
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($ldBreadcrumb, $ldFlags) !!}</script>
</x-layout>
