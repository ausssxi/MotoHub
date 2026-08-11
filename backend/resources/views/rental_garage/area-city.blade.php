<x-layout>
    <x-slot:title>{{ $prefecture }}{{ $city }}のレンタルガレージ・バイク保管場所（{{ number_format($count) }}件） - MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $prefecture }}{{ $city }}のレンタルガレージ・バイクコンテナ{{ number_format($count) }}件を一覧。月額料金・区画サイズ・24時間出入りや電源などの設備を掲載しています。</x-slot:metaDescription>

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
                    <li><a href="{{ route('rental-garage.area.index') }}" class="hover:text-gray-600 transition-colors">レンタルガレージ</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('rental-garage.area.prefecture', $prefecture) }}" class="hover:text-gray-600 transition-colors">{{ $prefecture }}</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $city }}</span></li>
                </ol>
            </nav>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h1 class="text-xl font-black text-gray-900 mb-6">{{ $prefecture }}{{ $city }}のレンタルガレージ（{{ number_format($count) }}件）</h1>

                <ul class="divide-y divide-gray-50">
                    @foreach($items as $it)
                    <li class="py-3">
                        <a href="{{ route('rental-garage.show', $it['id']) }}" class="text-sm text-purple-700 font-bold hover:underline">{{ $it['name'] }}</a>
                        <span class="inline-block ml-1.5 px-2 py-0.5 bg-violet-50 text-violet-700 text-[10px] font-bold rounded-md align-middle">{{ $it['typeLabel'] }}</span>

                        @if($it['operator'])
                        <p class="text-[11px] text-gray-500 mt-0.5"><i data-lucide="building-2" class="inline w-3 h-3"></i> {{ $it['operator'] }}</p>
                        @endif
                        @if($it['address'])
                        <p class="text-[11px] text-gray-500 mt-0.5"><i data-lucide="map-pin" class="inline w-3 h-3"></i> {{ $it['address'] }}</p>
                        @endif

                        {{-- 月額・区画サイズ。どちらも未取得の行があるため、ある項目だけ出す --}}
                        <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-0.5">
                            @if($it['feeText'])
                            <span class="text-[11px] text-gray-700 font-bold"><i data-lucide="japanese-yen" class="inline w-3 h-3 text-gray-400"></i> 月額{{ $it['feeText'] }}</span>
                            @endif
                            @if($it['sizeText'])
                            <span class="text-[11px] text-gray-500"><i data-lucide="ruler" class="inline w-3 h-3"></i> {{ $it['sizeText'] }}</span>
                            @endif
                        </div>

                        {{-- 設備バッジ。true のものだけ表示（false=なし / null=不明 は出さない） --}}
                        @if(!empty($it['facilities']))
                        <div class="flex flex-wrap gap-1 mt-1">
                            @foreach($it['facilities'] as $facility)
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-purple-700 bg-purple-50 border border-purple-100 rounded-full px-2 py-0.5"><i data-lucide="check" class="w-3 h-3"></i>{{ $facility }}</span>
                            @endforeach
                        </div>
                        @endif
                    </li>
                    @endforeach
                </ul>
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
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'レンタルガレージ', 'item' => route('rental-garage.area.index')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $prefecture, 'item' => route('rental-garage.area.prefecture', $prefecture)],
                ['@type' => 'ListItem', 'position' => 4, 'name' => $city, 'item' => route('rental-garage.area.city', [$prefecture, $city])],
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($ldBreadcrumb, $ldFlags) !!}</script>
</x-layout>
