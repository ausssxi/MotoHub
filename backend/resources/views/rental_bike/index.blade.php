<x-layout>
    <x-slot:title>レンタルバイク店舗一覧｜都道府県別 - MotoHub</x-slot:title>
    <x-slot:metaDescription>全国のレンタルバイク店舗を都道府県別に一覧。店舗名・事業者・所在地・電話番号を掲載しています。</x-slot:metaDescription>

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
                    <li><span class="text-gray-800">レンタルバイク</span></li>
                </ol>
            </nav>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h1 class="text-xl font-black text-gray-900 mb-2">レンタルバイク店舗一覧</h1>
                <p class="text-xs text-gray-500 mb-6">全国のレンタルバイク店舗を都道府県別にまとめています。</p>

                {{-- ★総数（「全◯店舗」）は出さない。都道府県ごとにグルーピングして表示。★地図は出さない（重くなる）。 --}}
                <div class="space-y-8">
                    @foreach($byPrefecture as $prefecture => $shops)
                    <section>
                        <div class="flex items-baseline justify-between mb-3">
                            <h2 class="text-base font-black text-gray-900">
                                <a href="{{ route('rental-bike.prefecture', $prefecture) }}" class="text-violet-700 hover:underline">{{ $prefecture }}</a>
                            </h2>
                            <a href="{{ route('rental-bike.prefecture', $prefecture) }}" class="text-[11px] font-bold text-gray-400 hover:text-gray-600">地図で見る ＞</a>
                        </div>
                        <ul class="divide-y divide-gray-100 border border-gray-100 rounded-xl overflow-hidden">
                            @foreach($shops as $shop)
                            <li class="px-4 py-3 hover:bg-gray-50 transition">
                                <a href="{{ route('rental-bike.show', $shop['id']) }}" class="text-sm font-bold text-violet-700 hover:underline">{{ $shop['name'] }}</a>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-[11px] text-gray-500">
                                    <span class="inline-block px-2 py-0.5 bg-gray-100 text-gray-600 rounded font-bold">{{ $shop['company'] }}</span>
                                    @if($shop['city'])
                                    <span><i data-lucide="map-pin" class="inline w-3 h-3"></i> {{ $shop['city'] }}</span>
                                    @endif
                                    {{-- ★電話番号は「ある場合のみ」。無い店舗は項目ごと非表示。 --}}
                                    @if($shop['tel'])
                                    <span><i data-lucide="phone" class="inline w-3 h-3"></i> {{ $shop['tel'] }}</span>
                                    @endif
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    </section>
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
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'レンタルバイク', 'item' => route('rental-bike.index')],
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($ldBreadcrumb, $ldFlags) !!}</script>
</x-layout>
