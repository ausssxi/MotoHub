<x-layout>
    @php $updated = now()->format('Y年n月j日'); @endphp
    <x-slot:title>バイク保険の選び方と維持費（税金・自賠責・任意保険）｜MotoHub</x-slot:title>
    <x-slot:metaDescription>バイクの維持費（軽自動車税・自賠責保険料）の排気量別早見表と、任意保険・ファミリーバイク特約の選び方をMotoHubがまとめて解説。任意保険は一括見積もりで比較できます。</x-slot:metaDescription>
    <x-slot:canonical>{{ route('hoken') }}</x-slot:canonical>

    <x-slot:navigation><x-navigation :showSearch="true" /></x-slot:navigation>

    @php
        $breadcrumb = [
            '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'トップ', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'バイク保険・維持費', 'item' => route('hoken')],
            ],
        ];
        $faqSchema = [
            '@context' => 'https://schema.org', '@type' => 'FAQPage',
            'mainEntity' => collect($faqs)->map(fn ($f) => [
                '@type' => 'Question', 'name' => $f['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
            ])->all(),
        ];
        // 内部リンク（排気量別ページ = category_cc）。ハブ→cc の出リンク。
        $ccLinks = [
            ['slug' => '50', 'label' => '〜50cc'],
            ['slug' => '125', 'label' => '〜125cc'],
            ['slug' => '250', 'label' => '〜250cc'],
            ['slug' => '400', 'label' => '〜400cc'],
            ['slug' => 'over750', 'label' => '大型'],
        ];
        $ctaUrl = $affiliate['url'] ?? '';
    @endphp
    <script type="application/ld+json">{!! json_encode($breadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    <div class="bg-gray-50 min-h-screen py-10 sm:py-16">
        <div class="max-w-4xl mx-auto px-4">

            {{-- ヒーロー --}}
            <div class="text-center mb-10">
                <h1 class="text-3xl sm:text-4xl font-black text-black mb-3 tracking-tighter">バイクの保険と維持費</h1>
                <p class="text-sm text-gray-500 font-bold max-w-2xl mx-auto">税金・自賠責の固定費（排気量別の早見表）と、任意保険・ファミリーバイク特約の選び方。</p>
                <p class="text-[11px] text-gray-400 font-bold mt-2">最終確認 {{ $lastVerified }}</p>
            </div>

            {{-- 自賠責と任意の違い --}}
            <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h2 class="text-xl font-black text-gray-800 mb-4 flex items-center gap-3"><span class="w-1.5 h-6 bg-emerald-500 rounded-full"></span>自賠責保険と任意保険の違い</h2>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-4">
                        <p class="text-sm font-black text-emerald-800 mb-1">自賠責保険（強制）</p>
                        <p class="text-[12px] text-gray-600 leading-relaxed">全車両に加入義務。補償は<strong>対人賠償のみ・上限あり</strong>（物損や自分のケガは対象外）。保険料は全国一律の固定額で、排気量と契約期間で決まります。</p>
                    </div>
                    <div class="bg-blue-50 border border-blue-100 rounded-2xl p-4">
                        <p class="text-sm font-black text-blue-800 mb-1">任意保険（任意）</p>
                        <p class="text-[12px] text-gray-600 leading-relaxed">自賠責でカバーできない<strong>物損・自分のケガ・上限超の対人賠償</strong>に備える上乗せ。保険料は年齢・等級・補償内容で変わります（一律の目安はありません）。</p>
                    </div>
                </div>
            </section>

            {{-- 排気量別 固定費早見表（軽自動車税×自賠責） --}}
            <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h2 class="text-xl font-black text-gray-800 mb-2 flex items-center gap-3"><span class="w-1.5 h-6 bg-emerald-500 rounded-full"></span>排気量別・固定費の早見表</h2>
                <p class="text-xs text-gray-500 mb-5">軽自動車税（種別割・年額）と自賠責保険料（12ヶ月）。いずれも公的な固定額です。</p>
                <div class="overflow-x-auto scrollbar-hide">
                    <table class="w-full text-sm border-collapse min-w-[520px]">
                        <thead>
                            <tr class="text-gray-400 text-[11px] border-b border-gray-200">
                                <th class="font-bold px-3 py-2 text-left">区分</th>
                                <th class="font-bold px-3 py-2 text-right">軽自動車税/年</th>
                                <th class="font-bold px-3 py-2 text-right">自賠責12ヶ月</th>
                                <th class="font-bold px-3 py-2 text-center">ファミバイ特約</th>
                                <th class="font-bold px-3 py-2 text-center">車検</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($brackets as $b)
                            <tr class="border-b border-gray-50">
                                <td class="px-3 py-2.5 font-black text-gray-800">{{ $b['label'] }}</td>
                                <td class="px-3 py-2.5 text-right font-bold text-gray-700">{{ number_format($b['tax']) }}円</td>
                                <td class="px-3 py-2.5 text-right font-bold text-gray-700">@if($b['jibaiseki_12']){{ number_format($b['jibaiseki_12']) }}円@else-@endif</td>
                                <td class="px-3 py-2.5 text-center">@if($b['family_tokuyaku'])<span class="text-emerald-600 font-black">○</span>@else<span class="text-gray-300">×</span>@endif</td>
                                <td class="px-3 py-2.5 text-center">@if($b['shaken'])<span class="text-amber-600 font-bold text-[11px]">あり</span>@else<span class="text-gray-300">なし</span>@endif</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-[11px] text-amber-600 font-bold mt-3 leading-relaxed"><i data-lucide="alert-triangle" class="w-3 h-3 inline"></i> {{ $revisionNote }}</p>
                <p class="text-[10px] text-gray-400 font-bold mt-2 leading-relaxed">
                    最終確認: {{ $lastVerified }}
                    ・出典: <a href="{{ $sources['tax']['url'] }}" target="_blank" rel="nofollow noopener" class="underline hover:text-gray-600">{{ $sources['tax']['name'] }}</a>、<a href="{{ $sources['jibaiseki']['url'] }}" target="_blank" rel="nofollow noopener" class="underline hover:text-gray-600">{{ $sources['jibaiseki']['name'] }}</a>
                </p>
            </section>

            {{-- ファミリーバイク特約（新基準原付ハブへ内部リンク） --}}
            <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h2 class="text-xl font-black text-gray-800 mb-3 flex items-center gap-3"><span class="w-1.5 h-6 bg-blue-500 rounded-full"></span>ファミリーバイク特約（125cc以下）</h2>
                <p class="text-[13px] text-gray-600 leading-relaxed mb-3">
                    125cc以下（原付一種・原付二種、<strong>2025年の新基準原付を含む</strong>）は、家族の自動車保険に付ける「ファミリーバイク特約」の対象です。等級に影響せず、複数台でも定額なのが特徴で、単独の任意保険より割安になる場合があります。
                </p>
                <a href="{{ route('shinkijun_gentsuki') }}" class="inline-flex items-center gap-1 text-[13px] font-black text-blue-600 hover:text-blue-700 transition-colors">
                    新基準原付とは？対象モデル・税金・維持費 <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </section>

            {{-- 任意保険の選び方（一般論のみ・商品推奨はしない） --}}
            <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h2 class="text-xl font-black text-gray-800 mb-4 flex items-center gap-3"><span class="w-1.5 h-6 bg-blue-500 rounded-full"></span>任意保険の選び方の要点</h2>
                <ul class="space-y-3 text-[13px] text-gray-600 leading-relaxed">
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i><span><strong>対人・対物は無制限</strong>が基本。事故の賠償は高額になりうるため、上限を設けない考え方が一般的です。</span></li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i><span><strong>人身傷害・搭乗者傷害</strong>で自分のケガに備えるか、補償額を検討します。</span></li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i><span><strong>ロードサービス</strong>の有無・範囲は、ツーリング中心なら重視されます。</span></li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i><span>保険料は<strong>年齢条件・等級・排気量</strong>で大きく変わります。同じ補償でも会社差があるため、複数社の見積もり比較が有効です。</span></li>
                </ul>
            </section>

            {{-- 一括見積もりCTA（★affiliate.url 設定時のみ表示・PR表記付き。未設定なら偽ボタンを出さない） --}}
            @if($ctaUrl !== '')
            <section class="bg-gradient-to-br from-blue-600 to-indigo-600 rounded-3xl shadow-lg p-6 sm:p-8 mb-8 text-center">
                <p class="text-white/70 text-[10px] font-black tracking-widest uppercase mb-2">PR</p>
                <h2 class="text-xl sm:text-2xl font-black text-white mb-2">{{ $affiliate['headline'] }}</h2>
                <p class="text-white/80 text-[13px] font-bold mb-5 max-w-xl mx-auto">{{ $affiliate['sub'] }}</p>
                <a href="{{ $ctaUrl }}" target="_blank" rel="nofollow sponsored noopener"
                   class="inline-flex items-center gap-2 bg-white text-blue-700 font-black px-8 py-3.5 rounded-xl shadow hover:bg-blue-50 transition-colors">
                    無料で一括見積もり <i data-lucide="external-link" class="w-4 h-4"></i>
                </a>
                @if(!empty($affiliate['provider']))
                <p class="text-white/50 text-[10px] font-bold mt-3">提供: {{ $affiliate['provider'] }}・PR</p>
                @endif
            </section>
            @endif

            {{-- 内部リンク（排気量別ページへ） --}}
            <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h2 class="text-base font-black text-gray-800 mb-4">排気量別で維持費・在庫を見る</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($ccLinks as $cc)
                    <a href="{{ route('bikes.category_cc', $cc['slug']) }}" class="text-xs font-bold text-gray-600 bg-gray-50 border border-gray-200 rounded-full px-3 py-1.5 hover:border-blue-300 hover:text-blue-600 transition-colors">{{ $cc['label'] }}のバイク</a>
                    @endforeach
                    <a href="{{ route('shinkijun_gentsuki') }}" class="text-xs font-bold text-gray-600 bg-gray-50 border border-gray-200 rounded-full px-3 py-1.5 hover:border-blue-300 hover:text-blue-600 transition-colors">新基準原付</a>
                    <a href="{{ route('theft') }}" class="text-xs font-bold text-gray-600 bg-gray-50 border border-gray-200 rounded-full px-3 py-1.5 hover:border-red-300 hover:text-red-600 transition-colors">バイク盗難データ・対策</a>
                </div>
            </section>

            {{-- FAQ --}}
            <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h2 class="text-xl font-black text-gray-800 mb-5 flex items-center gap-3"><span class="w-1.5 h-6 bg-gray-400 rounded-full"></span>よくある質問</h2>
                <div class="space-y-4">
                    @foreach($faqs as $f)
                    <div class="border-b border-gray-50 pb-4 last:border-b-0 last:pb-0">
                        <p class="text-sm font-black text-gray-800 mb-1.5">Q. {{ $f['q'] }}</p>
                        <p class="text-[13px] text-gray-600 leading-relaxed">{{ $f['a'] }}</p>
                    </div>
                    @endforeach
                </div>
            </section>

            {{-- 免責（保険募集をしない情報提供サイトである明示） --}}
            <p class="text-[10px] text-gray-400 leading-relaxed mt-6">
                本ページは保険商品の情報提供を目的としており、特定商品の推奨・保険募集を行うものではありません。固定額（軽自動車税・自賠責保険料）は上記出典から{{ $lastVerified }}時点で確認した値です。任意保険の保険料は条件により異なるため、実額は各社の見積もりでご確認ください。
            </p>
        </div>
    </div>

    <x-slot:scripts>
        <script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
    </x-slot:scripts>
</x-layout>
