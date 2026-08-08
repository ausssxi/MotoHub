<x-layout>
    <x-slot:title>{{ $prefecture }}の{{ $label }}（{{ number_format($count) }}件）｜市区町村別 - MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $prefecture }}の{{ $label }}{{ number_format($count) }}件を市区町村別に一覧。市区町村ごとの{{ $label }}の件数と一覧を掲載。</x-slot:metaDescription>

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
                    <li><span class="text-gray-800">{{ $prefecture }}</span></li>
                </ol>
            </nav>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h1 class="text-xl font-black text-gray-900 mb-6">{{ $prefecture }}の{{ $label }}（{{ number_format($count) }}件）</h1>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    @foreach($cities as $c)
                    <a href="{{ route($routePrefix.'.city', [$prefecture, $c['city']]) }}" class="flex items-baseline justify-between gap-1 px-3 py-2 border border-gray-100 rounded-lg text-sm hover:bg-purple-50 transition">
                        <span class="text-purple-700 font-bold">{{ $c['city'] }}</span>
                        <span class="text-[10px] text-gray-400 shrink-0">{{ number_format($c['count']) }}件</span>
                    </a>
                    @endforeach
                </div>
            </div>

            {{-- 他の都道府県から探す（全47都道府県への内部リンク） --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mt-6">
                <h2 class="text-sm font-black text-gray-900 mb-3">他の都道府県から探す</h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach($allPrefectures as $p)
                        @if($p['count'] > 0)
                        <a href="{{ route($routePrefix.'.prefecture', $p['prefecture']) }}" class="flex items-baseline justify-between gap-1 px-2.5 py-1.5 border border-gray-100 rounded-lg text-xs hover:bg-purple-50 transition {{ $p['prefecture'] === $prefecture ? 'bg-purple-50 border-purple-200' : '' }}">
                            <span class="text-purple-700 font-bold">{{ $p['prefecture'] }}</span>
                            <span class="text-[10px] text-gray-400 shrink-0">{{ number_format($p['count']) }}</span>
                        </a>
                        @else
                        <span class="flex items-baseline justify-between gap-1 px-2.5 py-1.5 border border-gray-100 rounded-lg text-xs text-gray-300">
                            <span>{{ $p['prefecture'] }}</span>
                            <span class="text-[10px] shrink-0">0</span>
                        </span>
                        @endif
                    @endforeach
                </div>
            </div>

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
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($ldBreadcrumb, $ldFlags) !!}</script>
</x-layout>
