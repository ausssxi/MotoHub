<x-layout>
    <x-slot:title>全国の道の駅一覧（全{{ number_format($total) }}駅）｜都道府県別 - MotoHub</x-slot:title>
    <x-slot:metaDescription>全国{{ number_format($total) }}駅の道の駅を都道府県別に一覧。地方区分から探せます。地図・アクセス・周辺のガソリンスタンド・コンビニ情報も掲載。</x-slot:metaDescription>

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
                    <li><span class="text-gray-800">道の駅</span></li>
                </ol>
            </nav>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h1 class="text-xl font-black text-gray-900 mb-6">全国の道の駅一覧（全{{ number_format($total) }}駅）</h1>

                @foreach($regions as $region => $prefs)
                <div class="mb-6">
                    <h2 class="text-sm font-black text-gray-900 mb-3 pb-1 border-b border-gray-100">{{ $region }}</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        @foreach($prefs as $p)
                            @if($p['count'] > 0)
                            <a href="{{ route('michinoeki.area', $p['prefecture']) }}" class="flex items-baseline justify-between gap-1 px-3 py-2 border border-gray-100 rounded-lg text-sm hover:bg-purple-50 transition">
                                <span class="text-purple-700 font-bold">{{ $p['prefecture'] }}</span>
                                <span class="text-[10px] text-gray-400 shrink-0">{{ $p['count'] }}駅</span>
                            </a>
                            @else
                            <span class="flex items-baseline justify-between gap-1 px-3 py-2 border border-gray-100 rounded-lg text-sm text-gray-300">
                                <span>{{ $p['prefecture'] }}</span>
                                <span class="text-[10px] shrink-0">0駅</span>
                            </span>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endforeach
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
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($ldBreadcrumb, $ldFlags) !!}</script>
</x-layout>
