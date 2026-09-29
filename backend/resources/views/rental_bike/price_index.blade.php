<x-layout>
    <x-slot:title>レンタルバイクの料金比較｜車格別の参考価格 - MotoHub</x-slot:title>
    <x-slot:metaDescription>原付・125cc・250cc・400cc・大型ごとに、レンタルバイク事業者の参考料金を並べて比較。参考価格・取得日を明記し、最新の料金・保険は各公式でご確認ください。</x-slot:metaDescription>

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
                    <li><span class="text-gray-800">料金比較</span></li>
                </ol>
            </nav>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h1 class="text-xl font-black text-gray-900 mb-2">レンタルバイクの料金比較</h1>
                <p class="text-xs text-gray-500 mb-6 leading-relaxed">
                    車格（原付・125cc・250cc・400cc・大型）ごとに、事業者の参考料金を並べています。
                    事業者によって時間の数え方（例：24時間／1日）が異なるため、条件を添えて掲載しています。
                </p>

                {{-- 車格カード --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($cards as $card)
                    <a href="{{ route('rental-bike.price.show', $card['slug']) }}"
                       class="flex items-center justify-between px-4 py-4 border border-gray-100 rounded-2xl hover:bg-violet-50 hover:border-violet-200 transition">
                        <span>
                            <span class="block text-base font-black text-gray-900">{{ $card['vehicle_class'] }}</span>
                            <span class="block text-[11px] text-gray-500 mt-0.5">
                                @if($card['providers'] > 0)
                                    {{ $card['providers'] }}社の参考料金を掲載
                                @else
                                    公式リンクから確認
                                @endif
                            </span>
                        </span>
                        <i data-lucide="chevron-right" class="w-5 h-5 text-violet-400"></i>
                    </a>
                    @endforeach
                </div>

                {{-- 注記（必須） --}}
                <ul class="mt-6 space-y-0.5 text-[11px] text-gray-500">
                    <li>・掲載額はすべて参考価格です。最新の料金は各公式サイトでご確認ください。</li>
                    <li>・保険・補償は別途です。</li>
                    <li>・レンタル819・AJ OSAKA は店舗ごとに料金が異なるため、公式ページへの導線のみ掲載しています。</li>
                </ul>
            </div>

            {{-- 店舗を探す --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mt-6">
                <h2 class="text-sm font-black text-gray-900 mb-2">店舗から探す</h2>
                <a href="{{ route('rental-bike.index') }}" class="text-sm font-bold text-violet-700 hover:underline">
                    全国のレンタルバイク店舗一覧 ＞
                </a>
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
                ['@type' => 'ListItem', 'position' => 3, 'name' => '料金比較', 'item' => route('rental-bike.price.index')],
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($ldBreadcrumb, $ldFlags) !!}</script>
</x-layout>
