<x-layout>
    <x-slot:title>レンタルバイク {{ $vehicleClass }}の料金比較｜事業者別の参考価格 - MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $vehicleClass }}のレンタルバイクの参考料金を事業者別に比較{{ $pricesFetchedAt ? '（'.$pricesFetchedAt->format('Y年n月').'時点）' : '' }}。参考価格・保険別、時間の数え方は事業者ごとに異なります。最新は各公式でご確認ください。</x-slot:metaDescription>

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
                    <li><a href="{{ route('rental-bike.index') }}" class="hover:text-gray-600 transition-colors">レンタルバイク</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('rental-bike.price.index') }}" class="hover:text-gray-600 transition-colors">料金比較</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $vehicleClass }}</span></li>
                </ol>
            </nav>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h1 class="text-xl font-black text-gray-900 mb-2">レンタルバイク {{ $vehicleClass }} の料金比較</h1>
                <p class="text-xs text-gray-500 mb-4 leading-relaxed">
                    {{ $vehicleClass }}のレンタルバイクを借りられる事業者の参考料金をまとめています。
                    事業者ごとに時間の数え方（例：24時間／1日）が異なるため、条件を添えています。
                </p>

                @if($rows->isNotEmpty())
                {{-- 条件差の注意（★時間制と日数制は等価でない＝単純比較しない） --}}
                <div class="flex items-start gap-2 px-3 py-2.5 bg-amber-50 border border-amber-100 rounded-xl mb-4 text-[11px] text-amber-800">
                    <i data-lucide="info" class="w-4 h-4 shrink-0 mt-0.5"></i>
                    <span>事業者ごとに時間の数え方が異なります（例：24時間／1日・当日返却）。金額は「料金の条件」列とあわせてご覧ください。単純な比較はできません。</span>
                </div>

                {{-- 比較テーブル。★安い順だが条件が違うため「最安」表記はしない。 --}}
                <div class="overflow-x-auto -mx-1 px-1">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-[11px] text-gray-400 border-b border-gray-100">
                                <th class="text-left font-bold py-2 pr-2">事業者</th>
                                <th class="text-right font-bold py-2 px-2 whitespace-nowrap">参考料金</th>
                                <th class="text-left font-bold py-2 px-2 whitespace-nowrap">料金の条件</th>
                                <th class="text-right font-bold py-2 pl-2">公式</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($rows as $r)
                            <tr>
                                <td class="py-3 pr-2 align-top">
                                    <span class="block font-bold text-gray-900">{{ $r['company'] }}</span>
                                    <span class="block text-[11px] mt-0.5">
                                        @if($r['shop_count'] >= $storeCountMin)
                                        <span class="text-gray-400">全国{{ $r['shop_count'] }}店舗・</span>
                                        @endif
                                        <a href="{{ route('rental-bike.index') }}" class="text-violet-600 hover:underline font-bold">店舗を探す ＞</a>
                                    </span>
                                </td>
                                <td class="py-3 px-2 text-right align-top whitespace-nowrap">
                                    <span class="font-black text-gray-900">¥{{ number_format($r['price_yen']) }}{{ $r['price_is_from'] ? '〜' : '' }}</span>
                                    @if($r['note'])
                                    <span class="block text-[10px] text-gray-400">{{ $r['note'] }}</span>
                                    @endif
                                </td>
                                <td class="py-3 px-2 align-top text-gray-600 text-xs whitespace-nowrap">{{ $r['plan_label'] }}</td>
                                <td class="py-3 pl-2 text-right align-top">
                                    @if($r['official_url'])
                                    <a href="{{ $r['official_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-violet-600 hover:underline text-xs font-bold whitespace-nowrap">
                                        料金表 <i data-lucide="external-link" class="w-3 h-3"></i>
                                    </a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- 必須注記 --}}
                <ul class="mt-4 space-y-0.5 text-[11px] text-gray-500">
                    <li>・上記はすべて<strong class="font-bold">参考価格</strong>です（税込）。@if($pricesFetchedAt){{ $pricesFetchedAt->format('Y年n月j日') }}時点。@endif</li>
                    <li>・最新の料金は各公式サイトでご確認ください。</li>
                    <li>・保険・補償は別途（公式でご確認ください）。</li>
                </ul>
                @else
                {{-- 鮮度内の料金が無いとき（自動更新停止など）。公式リンクで拾う。 --}}
                <p class="px-3 py-4 bg-gray-50 border border-gray-100 rounded-xl text-xs text-gray-500">
                    現在、{{ $vehicleClass }}の参考料金を掲載できる事業者がありません。各事業者の公式サイトでご確認ください。
                </p>
                @endif

                {{-- 料金は店舗ごと（819・AJ）＝公式リンクのみ --}}
                @if(!empty($perShop))
                <div class="mt-6 pt-5 border-t border-gray-100">
                    <h2 class="text-sm font-black text-gray-900 mb-1">店舗ごとに料金が異なる事業者</h2>
                    <p class="text-[11px] text-gray-400 mb-3">以下は車格別の統一料金表がなく、店舗ごとに料金が異なります。各店舗の公式ページでご確認ください。</p>
                    <div class="border border-gray-100 rounded-xl divide-y divide-gray-100 overflow-hidden">
                        @foreach($perShop as $ps)
                        <div class="flex items-center justify-between px-4 py-3">
                            <span class="text-sm font-bold text-gray-900">{{ $ps['company'] }}</span>
                            <span class="text-[11px] text-right">
                                @if($ps['shop_count'] >= $storeCountMin)
                                <span class="text-gray-400 mr-2">全国{{ $ps['shop_count'] }}店舗</span>
                                @endif
                                <a href="{{ route('rental-bike.index') }}" class="text-violet-600 hover:underline font-bold">店舗を探す ＞</a>
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            {{-- FAQ（★実データと事実だけ。JSON-LD と同一文言） --}}
            @if(!empty($faqs))
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mt-6">
                <h2 class="text-sm font-black text-gray-900 mb-3">よくある質問</h2>
                <dl class="space-y-4">
                    @foreach($faqs as $faq)
                    <div>
                        <dt class="text-sm font-bold text-gray-900 mb-1">{{ $faq['q'] }}</dt>
                        <dd class="text-xs text-gray-600 leading-relaxed">{{ $faq['a'] }}</dd>
                    </div>
                    @endforeach
                </dl>
            </div>
            @endif

            {{-- 他の車格・店舗を探す --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mt-6">
                <h2 class="text-sm font-black text-gray-900 mb-3">他の車格の料金を見る</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($otherClasses as $slug => $vc)
                    <a href="{{ route('rental-bike.price.show', $slug) }}"
                       class="px-3 py-1.5 border border-gray-100 rounded-lg text-xs font-bold transition {{ $slug === $classSlug ? 'bg-violet-50 border-violet-200 text-violet-700' : 'text-violet-700 hover:bg-violet-50' }}">
                        {{ $vc }}
                    </a>
                    @endforeach
                </div>
                <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                    <a href="{{ route('rental-bike.index') }}" class="font-bold text-violet-700 hover:underline">全国の店舗一覧 ＞</a>
                    <a href="{{ route('license.index') }}" class="font-bold text-violet-700 hover:underline">バイク免許ガイド ＞</a>
                </div>
            </div>

            {{-- 回遊リンク --}}
            <div class="mt-6">
                <x-cross-links :crossLinks="$crossLinks" />
            </div>
        </div>
    </div>

    {{-- JSON-LD: BreadcrumbList + FAQPage（表示と同一文言） --}}
    @php
        $ldFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $ldBreadcrumb = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'HOME', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'レンタルバイク', 'item' => route('rental-bike.index')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => '料金比較', 'item' => route('rental-bike.price.index')],
                ['@type' => 'ListItem', 'position' => 4, 'name' => $vehicleClass, 'item' => route('rental-bike.price.show', $classSlug)],
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($ldBreadcrumb, $ldFlags) !!}</script>
    @if(!empty($faqs))
    @php
        $ldFaq = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => [],
        ];
        foreach ($faqs as $faq) {
            $ldFaq['mainEntity'][] = [
                '@type' => 'Question',
                'name' => $faq['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
            ];
        }
    @endphp
    <script type="application/ld+json">{!! json_encode($ldFaq, $ldFlags) !!}</script>
    @endif
</x-layout>
