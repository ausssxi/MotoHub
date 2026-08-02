<x-layout>
    <x-slot:title>中古バイクの相場｜値上がり・値下がり車種ランキングと平均価格｜MotoHub</x-slot:title>
    <x-slot:metaDescription>MotoHub掲載の中古バイク在庫を自社集計。値上がり・値下がりしている車種のランキングと、掲載中の在庫台数・平均価格を実データからまとめています。</x-slot:metaDescription>
    <x-slot:canonical>{{ route('market') }}</x-slot:canonical>
    <x-slot:navigation><x-navigation :showSearch="true" /></x-slot:navigation>

    <x-jsonld.breadcrumb-list :items="[
        ['name' => 'HOME', 'url' => route('bikes.index')],
        ['name' => '中古バイクの相場'],
    ]" />

    <div class="bg-gray-50 min-h-screen">

        {{-- ヒーロー --}}
        <div class="bg-gradient-to-br from-slate-900 to-blue-900 text-white pt-8 pb-10 px-4">
            <div class="max-w-3xl mx-auto">
                <nav class="text-xs text-blue-300 font-bold mb-4">
                    <a href="{{ route('bikes.index') }}" class="hover:underline">HOME</a>
                    <span class="mx-1.5 text-blue-500">/</span>
                    <span class="text-blue-100">中古バイクの相場</span>
                </nav>
                <div class="text-4xl mb-2">📈</div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight mb-1">中古バイクの相場</h1>
                <p class="text-blue-300 text-xs font-bold">MotoHub掲載の在庫を自社集計した、値動きと平均価格</p>
            </div>
        </div>

        <div class="max-w-3xl mx-auto px-4 py-10 space-y-10">

            {{-- 全体サマリ --}}
            <section>
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-white rounded-2xl border border-slate-200 px-4 py-5 text-center">
                        <div class="text-2xl font-black text-slate-900 tabular-nums">{{ number_format($summary['stock']) }}</div>
                        <div class="text-[11px] text-slate-500 font-bold mt-1">掲載中の在庫台数</div>
                    </div>
                    <div class="bg-white rounded-2xl border border-slate-200 px-4 py-5 text-center">
                        <div class="text-2xl font-black text-slate-900 tabular-nums">
                            @if($summary['avg_price'] > 0){{ number_format($summary['avg_price']) }}<span class="text-sm font-bold">円</span>@else—@endif
                        </div>
                        <div class="text-[11px] text-slate-500 font-bold mt-1">平均価格</div>
                    </div>
                </div>

                {{-- 集計期間の明示（period から動的生成・数値はハードコードしない） --}}
                @if(!empty($period['from']) && $period['days'] > 0)
                    <p class="text-xs text-slate-500 mt-3 leading-relaxed">
                        {{ \Illuminate\Support\Carbon::parse($period['from'])->format('Y年n月j日') }}から{{ number_format($period['days']) }}日間・{{ number_format($period['model_count']) }}車種を自社集計しています。データが貯まるほど精度が上がります。
                    </p>
                @endif
            </section>

            {{-- 期間切替（JSを使わず ?days= リンクのみ） --}}
            <section>
                <div class="flex flex-wrap gap-2">
                    @foreach(['30' => '30日', '90' => '90日', 'max' => '最長'] as $key => $label)
                        <a href="{{ route('market', ['days' => $key]) }}"
                           class="inline-flex items-center rounded-full border px-4 py-1.5 text-xs font-black transition
                                  {{ $days === $key ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:border-blue-400' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </section>

            {{-- 値動き行の価格類は TrendService が万円単位（小数1桁）で返す。
                 車種詳細リンクは既存の id→フォールバックルート（/bikes/model/{id}）に倣う。 --}}

            {{-- 値上がりランキング --}}
            <section>
                <h2 class="text-xl font-black text-slate-900 mb-4">値上がりしている車種 TOP5</h2>
                @if(empty($risers))
                    <div class="bg-white rounded-2xl border border-slate-200 px-4 py-6 text-center text-sm text-slate-500">
                        集計対象がありません。
                    </div>
                @else
                    <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100 overflow-hidden">
                        @foreach($risers as $i => $r)
                            <a href="{{ route('bikes.model_detail.fallback', $r['model_id']) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition">
                                <span class="w-6 text-center text-sm font-black text-slate-400 tabular-nums shrink-0">{{ $i + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-black text-slate-900 truncate">{{ $r['maker_name'] }} {{ $r['model_name'] }}</div>
                                    <div class="text-[11px] text-slate-500">平均 {{ $r['current_price'] }}万円 ・ 在庫{{ $r['count'] }}台</div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="text-sm font-black text-rose-600 tabular-nums">+{{ $r['diff'] }}万円</div>
                                    <div class="text-[11px] font-bold text-rose-500 tabular-nums">+{{ $r['rate'] }}%</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                    <a href="{{ route('bikes.trends') }}" class="inline-flex items-center gap-1 mt-3 text-xs font-black text-blue-700 hover:underline">
                        値上がりランキングをすべて見る（30件）<i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                @endif
            </section>

            {{-- 値下がりランキング --}}
            <section>
                <h2 class="text-xl font-black text-slate-900 mb-4">値下がりしている車種 TOP5</h2>
                @if(empty($fallers))
                    <div class="bg-white rounded-2xl border border-slate-200 px-4 py-6 text-center text-sm text-slate-500">
                        集計対象がありません。
                    </div>
                @else
                    <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100 overflow-hidden">
                        @foreach($fallers as $i => $r)
                            <a href="{{ route('bikes.model_detail.fallback', $r['model_id']) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition">
                                <span class="w-6 text-center text-sm font-black text-slate-400 tabular-nums shrink-0">{{ $i + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-black text-slate-900 truncate">{{ $r['maker_name'] }} {{ $r['model_name'] }}</div>
                                    <div class="text-[11px] text-slate-500">平均 {{ $r['current_price'] }}万円 ・ 在庫{{ $r['count'] }}台</div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="text-sm font-black text-emerald-600 tabular-nums">{{ $r['diff'] }}万円</div>
                                    <div class="text-[11px] font-bold text-emerald-500 tabular-nums">{{ $r['rate'] }}%</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                    <a href="{{ route('bikes.trends') }}" class="inline-flex items-center gap-1 mt-3 text-xs font-black text-blue-700 hover:underline">
                        値下がりランキングをすべて見る（30件）<i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                @endif
            </section>

            {{-- 関連ページ（theft ハブと同じ $crossLinks 方式） --}}
            <x-cross-links :crossLinks="$crossLinks" />

            {{-- 補足 --}}
            <section class="border-t border-slate-200 pt-6">
                <p class="text-[11px] text-slate-400 leading-relaxed">
                    本ページの相場はMotoHub掲載の中古車在庫を自社集計したものです。値動きは各車種の平均価格を過去の記録と比較して算出しており、実際の売買価格や個体差を保証するものではありません。集計対象は出品状況により変動します。
                </p>
            </section>

        </div>
    </div>
</x-layout>
