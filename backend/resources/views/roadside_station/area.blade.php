<x-layout>
    <x-slot:title>{{ $prefecture }}の道の駅一覧（{{ number_format($count) }}駅）｜市区町村別 - MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $prefecture }}の道の駅{{ number_format($count) }}駅を市区町村別に一覧。各駅の地図・アクセス・周辺のガソリンスタンド・コンビニ・洗車場情報を掲載。</x-slot:metaDescription>

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
                    <li><a href="{{ route('michinoeki.index') }}" class="hover:text-gray-600 transition-colors">道の駅</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $prefecture }}</span></li>
                </ol>
            </nav>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h1 class="text-xl font-black text-gray-900 mb-6">{{ $prefecture }}の道の駅一覧（{{ number_format($count) }}駅）</h1>

                @foreach($byCity as $city => $stations)
                <div class="mb-6">
                    <h2 class="text-sm font-black text-gray-900 mb-2 pb-1 border-b border-gray-100">{{ $city !== '' ? $city : 'その他' }}</h2>
                    <ul class="divide-y divide-gray-50">
                        @foreach($stations as $s)
                        <li class="py-2">
                            <a href="{{ route('michinoeki.show', $s['station_code']) }}" class="text-sm text-purple-700 font-bold hover:underline">{{ $s['name'] }}</a>
                            @if($s['nickname'])
                            <span class="text-[11px] text-gray-400 ml-1">{{ $s['nickname'] }}</span>
                            @endif
                            @if($s['address'])
                            <p class="text-[11px] text-gray-500 mt-0.5"><i data-lucide="map-pin" class="inline w-3 h-3"></i> {{ $s['address'] }}</p>
                            @endif
                            @if($s['route'])
                            <p class="text-[11px] text-gray-400 mt-0.5"><i data-lucide="milestone" class="inline w-3 h-3"></i> {{ $s['route'] }}</p>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </div>

            {{-- 他の都道府県から探す（全47都道府県への内部リンク） --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mt-6">
                <h2 class="text-sm font-black text-gray-900 mb-3">他の都道府県から探す</h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach($allPrefectures as $p)
                        @if($p['count'] > 0)
                        <a href="{{ route('michinoeki.area', $p['prefecture']) }}" class="flex items-baseline justify-between gap-1 px-2.5 py-1.5 border border-gray-100 rounded-lg text-xs hover:bg-purple-50 transition {{ $p['prefecture'] === $prefecture ? 'bg-purple-50 border-purple-200' : '' }}">
                            <span class="text-purple-700 font-bold">{{ $p['prefecture'] }}</span>
                            <span class="text-[10px] text-gray-400 shrink-0">{{ $p['count'] }}</span>
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
                ['@type' => 'ListItem', 'position' => 2, 'name' => '道の駅', 'item' => route('michinoeki.index')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $prefecture, 'item' => route('michinoeki.area', $prefecture)],
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($ldBreadcrumb, $ldFlags) !!}</script>
</x-layout>
