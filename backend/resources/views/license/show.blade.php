<x-layout>
    <x-slot:title>{{ $c['name'] }}で乗れるバイク｜{{ $c['range_label'] }}の中古在庫{{ number_format($c['stats']['stock']) }}台を実データで | MotoHub</x-slot:title>
    <x-slot:metaDescription>
        {{ $c['name'] }}（{{ $c['formal'] }}）で運転できるのは{{ $c['range_label'] }}。高速道路{{ $c['highway'] ? '可' : '不可' }}、二人乗り{{ $c['tandem'] ? '可（条件あり）' : '不可' }}。MotoHub掲載中の中古在庫{{ number_format($c['stats']['stock']) }}台・平均{{ number_format($c['stats']['avg_price']) }}円の実データと、人気車種を紹介します。
    </x-slot:metaDescription>
    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">

        {{-- ヒーロー --}}
        <div class="bg-gradient-to-br from-slate-900 to-blue-900 text-white pt-8 pb-10 px-4">
            <div class="max-w-3xl mx-auto">
                <nav class="text-xs text-blue-300 font-bold mb-4">
                    <a href="{{ route('license.index') }}" class="hover:underline">バイク免許ガイド</a>
                    <span class="mx-1.5 text-blue-500">/</span>
                    <span class="text-blue-100">{{ $c['name'] }}</span>
                </nav>
                <div class="text-4xl mb-2">{{ $c['emoji'] }}</div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight mb-1">{{ $c['name'] }}で乗れるバイク</h1>
                <p class="text-blue-300 text-xs font-bold mb-4">{{ $c['formal'] }}／{{ $c['range_label'] }}</p>

                <div class="grid grid-cols-3 gap-2">
                    <div class="bg-white/10 rounded-xl px-3 py-3 text-center">
                        <div class="text-xl font-black tabular-nums">{{ number_format($c['stats']['stock']) }}</div>
                        <div class="text-[11px] text-blue-200 font-bold">在庫台数</div>
                    </div>
                    <div class="bg-white/10 rounded-xl px-3 py-3 text-center">
                        <div class="text-xl font-black tabular-nums">{{ number_format(round($c['stats']['avg_price'] / 10000)) }}<span class="text-xs">万</span></div>
                        <div class="text-[11px] text-blue-200 font-bold">平均価格</div>
                    </div>
                    <div class="bg-white/10 rounded-xl px-3 py-3 text-center">
                        <div class="text-xl font-black tabular-nums">{{ number_format(round($c['stats']['min_price'] / 10000)) }}<span class="text-xs">万</span></div>
                        <div class="text-[11px] text-blue-200 font-bold">最安値</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-3xl mx-auto px-4 py-10 space-y-10">

            {{-- 概要 --}}
            <section>
                <p class="text-sm text-slate-700 leading-relaxed">{{ $c['summary'] }}</p>
            </section>

            {{-- 基本ルール --}}
            <section>
                <h2 class="text-xl font-black text-slate-900 mb-4">{{ $c['name'] }}の基本ルール</h2>
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-slate-100">
                            <tr>
                                <th class="px-4 py-3 text-left font-black text-slate-600 bg-slate-50 w-40">運転できる排気量</th>
                                <td class="px-4 py-3 font-bold text-slate-900">{{ $c['range_label'] }}</td>
                            </tr>
                            <tr>
                                <th class="px-4 py-3 text-left font-black text-slate-600 bg-slate-50">最高速度</th>
                                <td class="px-4 py-3 text-slate-700">{{ $c['speed_limit'] }}</td>
                            </tr>
                            <tr>
                                <th class="px-4 py-3 text-left font-black text-slate-600 bg-slate-50">高速道路</th>
                                <td class="px-4 py-3">
                                    @if($c['highway'])
                                        <span class="font-black text-emerald-600">走行できる</span>
                                    @else
                                        <span class="font-black text-slate-500">走行できない</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="px-4 py-3 text-left font-black text-slate-600 bg-slate-50">二人乗り</th>
                                <td class="px-4 py-3">
                                    @if($c['tandem'])
                                        <span class="font-black text-emerald-600">できる</span>
                                        <span class="text-xs text-slate-500 block mt-0.5">一般道は免許取得から1年以上。高速道路は20歳以上かつ二輪免許の経歴が通算3年以上。</span>
                                    @else
                                        <span class="font-black text-slate-500">できない</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="px-4 py-3 text-left font-black text-slate-600 bg-slate-50">二段階右折</th>
                                <td class="px-4 py-3 text-slate-700">{{ $c['two_stage'] ? '必要' : '不要' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- 手数料 --}}
            <section>
                <h2 class="text-xl font-black text-slate-900 mb-1">試験・交付にかかる手数料</h2>
                <p class="text-xs text-slate-500 mb-4">東京都（警視庁）の金額です。都道府県により異なる場合があります。</p>
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-slate-100">
                            @foreach($c['fees'] as $label => $yen)
                                <tr>
                                    <th class="px-4 py-3 text-left font-bold text-slate-600">{{ $label }}</th>
                                    <td class="px-4 py-3 text-right font-black text-slate-900 tabular-nums">{{ number_format($yen) }}円</td>
                                </tr>
                            @endforeach
                            <tr class="bg-slate-50">
                                <th class="px-4 py-3 text-left font-black text-slate-700">合計</th>
                                <td class="px-4 py-3 text-right font-black text-blue-700 tabular-nums">{{ number_format(array_sum($c['fees'])) }}円</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @if(empty($c['school_cost']))
                    <p class="text-xs text-slate-500 mt-3 leading-relaxed">
                        なお、指定自動車教習所に通う場合の教習料金は各教習所が個別に設定しており、
                        全国的な公的統計は公表されていません。実際の金額はお近くの教習所の公式サイトで確認してください。
                    </p>
                @else
                    {{-- 教習所費用スロット（確認済みデータが入ったらここに表示） --}}
                    <div class="mt-4 bg-white rounded-2xl border border-slate-200 p-4">
                        <div class="text-sm font-black text-slate-900 mb-2">教習所の料金例</div>
                        <ul class="space-y-1.5">
                            @foreach($c['school_cost'] as $s)
                                <li class="text-sm text-slate-700 flex justify-between gap-3">
                                    <span>{{ $s['name'] }}</span>
                                    <span class="font-black tabular-nums shrink-0">{{ number_format($s['yen']) }}円</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            {{-- 注意点 --}}
            @if(!empty($c['notes']))
                <section>
                    <h2 class="text-xl font-black text-slate-900 mb-4">知っておきたいポイント</h2>
                    <ul class="space-y-2.5">
                        @foreach($c['notes'] as $note)
                            <li class="bg-white rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-700 leading-relaxed">
                                {{ $note }}
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- 人気車種 --}}
            @if(!empty($c['models']))
                <section>
                    <h2 class="text-xl font-black text-slate-900 mb-1">{{ $c['name'] }}で乗れる人気車種</h2>
                    <p class="text-xs text-slate-500 mb-4">MotoHub掲載中の在庫が多い順。価格は掲載中の平均です。</p>
                    <div class="bg-white rounded-2xl border border-slate-200 overflow-x-auto">
                        <table class="w-full text-sm min-w-[560px]">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr class="text-left">
                                    <th class="px-4 py-3 font-black">車種</th>
                                    <th class="px-4 py-3 font-black text-right">排気量</th>
                                    <th class="px-4 py-3 font-black text-right">在庫</th>
                                    <th class="px-4 py-3 font-black text-right">平均価格</th>
                                    <th class="px-4 py-3 font-black text-right">最安値</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($c['models'] as $m)
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3">
                                            <a href="/bikes/catalog/{{ $m->slug }}" class="font-bold text-blue-700 hover:underline">{{ $m->name }}</a>
                                        </td>
                                        <td class="px-4 py-3 text-right text-slate-600 tabular-nums">{{ $m->displacement }}cc</td>
                                        <td class="px-4 py-3 text-right font-black tabular-nums">{{ number_format($m->stock) }}台</td>
                                        <td class="px-4 py-3 text-right tabular-nums text-slate-700">¥{{ number_format($m->avg_price) }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums text-slate-500">¥{{ number_format($m->min_price) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            {{-- CTA --}}
            <section>
                <a href="/bikes/search?{{ http_build_query($c['search_query']) }}"
                   class="block bg-gradient-to-br from-blue-600 to-blue-800 text-white rounded-2xl px-6 py-6 text-center hover:from-blue-500 hover:to-blue-700 transition">
                    <div class="text-lg font-black mb-1">{{ $c['name'] }}で乗れるバイクを探す</div>
                    <div class="text-blue-200 text-sm font-bold">{{ $c['range_label'] }}・{{ number_format($c['stats']['stock']) }}台の在庫から</div>
                </a>
            </section>

            {{-- ステップアップ導線 --}}
            <section>
                <h2 class="text-xl font-black text-slate-900 mb-4">他の免許区分と比べる</h2>
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach($all as $key => $o)
                        @continue($key === $c['key'])
                        <a href="{{ route('license.show', $key) }}"
                           class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-blue-400 transition">
                            <div class="text-sm font-black text-slate-900">{{ $o['emoji'] }} {{ $o['name'] }}</div>
                            <div class="text-xs text-slate-500 mt-1">{{ $o['range_label'] }}／高速{{ $o['highway'] ? '可' : '不可' }}</div>
                        </a>
                    @endforeach
                </div>
                <div class="mt-3">
                    <a href="{{ route('license.index') }}" class="text-sm font-black text-blue-700 hover:underline">← 4区分の比較に戻る</a>
                </div>
            </section>

            {{-- 出典 --}}
            <section class="border-t border-slate-200 pt-6">
                <h2 class="text-sm font-black text-slate-700 mb-3">出典</h2>
                <ul class="space-y-1.5">
                    @foreach($sources as $s)
                        <li>
                            <a href="{{ $s['url'] }}" target="_blank" rel="noopener nofollow"
                               class="text-xs text-blue-700 hover:underline">{{ $s['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
                <p class="text-[11px] text-slate-400 mt-3 leading-relaxed">
                    制度の内容は2026年7月時点のものです。手数料は東京都の金額であり、都道府県により異なる場合があります。
                    最新かつ正確な情報は、お住まいの都道府県警察の公式サイトで確認してください。
                </p>
            </section>

        </div>
    </div>
</x-layout>
