<x-layout>
    <x-slot:title>バイク免許の種類と選び方｜4区分を実在庫データで比較 | MotoHub</x-slot:title>
    <x-slot:metaDescription>
        原付一種・原付二種・普通二輪・大型二輪の4区分を、乗れる排気量・高速道路の可否・二人乗り条件・手数料で比較。各免許で実際に買える中古バイクの在庫台数と平均価格を実データで掲載しています。
    </x-slot:metaDescription>
    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">

        {{-- ヒーロー --}}
        <div class="bg-gradient-to-br from-slate-900 to-blue-900 text-white pt-10 pb-12 px-4">
            <div class="max-w-4xl mx-auto text-center">
                <div class="text-4xl mb-3">🪪</div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight mb-2">バイク免許の種類と選び方</h1>
                <p class="text-blue-200 text-sm font-bold">
                    4つの区分で「乗れるバイク」がどれだけ変わるのか。制度と、実際の中古在庫データの両方で比較します。
                </p>
                <div class="mt-6 inline-flex items-baseline gap-2 bg-white/10 rounded-xl px-5 py-3">
                    <span class="text-xs text-blue-200 font-bold">掲載中の中古バイク</span>
                    <span class="text-2xl font-black tabular-nums">{{ number_format($totalStock) }}</span>
                    <span class="text-xs text-blue-200 font-bold">台</span>
                </div>
            </div>
        </div>

        <div class="max-w-4xl mx-auto px-4 py-10 space-y-12">

            {{-- 4区分カード --}}
            <section>
                <h2 class="text-xl font-black text-slate-900 mb-1">免許は4区分。上に行くほど選択肢が増える</h2>
                <p class="text-sm text-slate-600 mb-5">在庫台数・平均価格はMotoHub掲載中の実データ（毎時更新）です。</p>

                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach($classes as $c)
                        <a href="{{ $c['url'] }}"
                           class="block bg-white rounded-2xl border border-slate-200 p-5 hover:border-blue-400 hover:shadow-lg transition">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <div class="text-2xl mb-1">{{ $c['emoji'] }}</div>
                                    <div class="font-black text-slate-900 text-lg leading-tight">{{ $c['name'] }}</div>
                                    <div class="text-xs text-slate-500 font-bold mt-0.5">{{ $c['range_label'] }}</div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="text-2xl font-black text-blue-700 tabular-nums leading-none">
                                        {{ number_format($c['stats']['stock']) }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 font-bold">台の在庫</div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-1.5 mb-3">
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $c['highway'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                    高速 {{ $c['highway'] ? '○' : '×' }}
                                </span>
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $c['tandem'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                    二人乗り {{ $c['tandem'] ? '○' : '×' }}
                                </span>
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $c['two_stage'] ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500' }}">
                                    二段階右折 {{ $c['two_stage'] ? '要' : '不要' }}
                                </span>
                            </div>

                            <p class="text-xs text-slate-600 leading-relaxed mb-3">{{ $c['summary'] }}</p>

                            <div class="flex items-center justify-between border-t border-slate-100 pt-3">
                                <span class="text-xs text-slate-500 font-bold">
                                    平均 <span class="text-slate-900">¥{{ number_format($c['stats']['avg_price']) }}</span>
                                </span>
                                <span class="text-xs font-black text-blue-700">詳しく見る →</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>

            {{-- 比較表 --}}
            <section>
                <h2 class="text-xl font-black text-slate-900 mb-4">4区分ひと目で比較</h2>
                <div class="bg-white rounded-2xl border border-slate-200 overflow-x-auto">
                    <table class="w-full text-sm min-w-[640px]">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-black">免許</th>
                                <th class="px-4 py-3 font-black">排気量</th>
                                <th class="px-4 py-3 font-black text-center">高速</th>
                                <th class="px-4 py-3 font-black text-center">二人乗り</th>
                                <th class="px-4 py-3 font-black text-right">在庫</th>
                                <th class="px-4 py-3 font-black text-right">平均価格</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($classes as $c)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3">
                                        <a href="{{ $c['url'] }}" class="font-black text-blue-700 hover:underline">
                                            {{ $c['emoji'] }} {{ $c['name'] }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 font-bold">{{ $c['range_label'] }}</td>
                                    <td class="px-4 py-3 text-center">{{ $c['highway'] ? '○' : '×' }}</td>
                                    <td class="px-4 py-3 text-center">{{ $c['tandem'] ? '○' : '×' }}</td>
                                    <td class="px-4 py-3 text-right font-black tabular-nums">{{ number_format($c['stats']['stock']) }}台</td>
                                    <td class="px-4 py-3 text-right font-bold tabular-nums text-slate-700">¥{{ number_format($c['stats']['avg_price']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-slate-500 mt-2">
                    ※二人乗りには免許経歴などの条件があります。詳細は各免許のページを確認してください。
                </p>
            </section>

            {{-- 新基準原付 --}}
            <section class="bg-amber-50 border border-amber-200 rounded-2xl p-5">
                <h2 class="text-lg font-black text-amber-900 mb-2">⚠️ 2025年4月から「新基準原付」が始まっています</h2>
                <div class="text-sm text-amber-900/90 leading-relaxed space-y-2">
                    <p>
                        排気量125cc以下かつ<strong>最高出力4.0kW以下に制御された二輪車</strong>が、原付一種として扱われるようになりました。
                        原付免許で運転できますが、交通ルールは従来の原付と同じです。
                    </p>
                    <p>
                        つまり<strong>最高速度30km/h、二段階右折、二人乗り禁止、高速道路の通行禁止</strong>が適用されます。
                        「125ccだから原付二種と同じ」ではない点に注意してください。ナンバープレートは白色、軽自動車税は年2,000円です。
                    </p>
                    <p>
                        従来の50cc以下の原付が廃止されたわけではなく、引き続き運転・売買できます。
                    </p>
                </div>
            </section>

            {{-- 選び方 --}}
            <section>
                <h2 class="text-xl font-black text-slate-900 mb-4">目的から選ぶなら</h2>
                <div class="space-y-3">
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <div class="font-black text-slate-900 text-sm mb-1">片道5km以内の通勤・買い物だけ</div>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            原付一種で足ります。ただし30km/h制限と二段階右折が想像以上にストレスになるため、
                            幹線道路を使うなら<a href="{{ route('license.show', 'kogata') }}" class="text-blue-700 font-bold hover:underline">原付二種</a>を強くおすすめします。
                        </p>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <div class="font-black text-slate-900 text-sm mb-1">通勤メイン、たまに二人乗り</div>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            <a href="{{ route('license.show', 'kogata') }}" class="text-blue-700 font-bold hover:underline">原付二種</a>が最もバランスが良い区分です。
                            30km/h制限と二段階右折から解放され、二人乗りもできます。高速道路だけは走れません。
                        </p>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <div class="font-black text-slate-900 text-sm mb-1">ツーリングで遠出したい</div>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            高速道路を走れるのは125cc超からです。<a href="{{ route('license.show', 'futsuu') }}" class="text-blue-700 font-bold hover:underline">普通二輪</a>以上が必要になります。
                        </p>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <div class="font-black text-slate-900 text-sm mb-1">車種の選択肢を最大にしたい</div>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            <a href="{{ route('license.show', 'oogata') }}" class="text-blue-700 font-bold hover:underline">大型二輪</a>なら排気量の制限がなくなり、輸入車を含めすべての二輪車が対象になります。
                        </p>
                    </div>
                </div>
            </section>

            {{-- 関連リンク --}}
            <section>
                <h2 class="text-xl font-black text-slate-900 mb-4">関連記事</h2>
                <div class="grid sm:grid-cols-2 gap-3">
                    <a href="/blog/125cc-all-models-comparison-2026" class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-blue-400 transition">
                        <div class="text-sm font-black text-slate-900">125cc全車種比較</div>
                        <div class="text-xs text-slate-500 mt-1">原付二種で乗れる車種を実データで</div>
                    </a>
                    <a href="/blog/250cc-all-models-comparison-2026" class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-blue-400 transition">
                        <div class="text-sm font-black text-slate-900">250cc全車種比較</div>
                        <div class="text-xs text-slate-500 mt-1">普通二輪の主力クラス</div>
                    </a>
                    <a href="/blog/751cc-all-models-comparison-2026" class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-blue-400 transition">
                        <div class="text-sm font-black text-slate-900">大型バイク(751cc以上)全車種比較</div>
                        <div class="text-xs text-slate-500 mt-1">大型二輪で選べる240モデル</div>
                    </a>
                    <a href="/blog/bike-market-forecast-autumn-2026" class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-blue-400 transition">
                        <div class="text-sm font-black text-slate-900">2026年秋のバイク相場予測</div>
                        <div class="text-xs text-slate-500 mt-1">買い時を実データで読む</div>
                    </a>
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
                    手数料は東京都（警視庁）の金額です。都道府県により異なる場合があるため、お住まいの都道府県警察の公式サイトで確認してください。
                    制度の内容は2026年7月時点のものです。
                </p>
            </section>

        </div>
    </div>
</x-layout>
