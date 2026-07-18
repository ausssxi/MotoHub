<x-layout>
    @php
        $tSum = $summary['target']; $bSum = $summary['base']; $oSum = $summary['old50'];
        $updated = now()->format('Y年n月j日');
        $targetNames = $targets->pluck('name')->implode('・'); // 実在庫のある対象のみ（偽らない）
    @endphp
    <x-slot:title>新基準原付とは？対象モデル一覧・中古相場・原付二種との違い【{{ now()->year }}】｜MotoHub</x-slot:title>
    <x-slot:metaDescription>新基準原付@if($targetNames !== '')（{{ $targetNames }}）@endifの対象モデルと、@if($tSum['low_man'])新古車の実在庫{{ number_format($tSum['total_count']) }}台・相場{{ $tSum['low_man'] }}〜{{ $tSum['high_man'] }}万円、@endif原付二種との違い・税金・選び方を、MotoHubの実データで解説。</x-slot:metaDescription>
    <x-slot:canonical>{{ route('shinkijun_gentsuki') }}</x-slot:canonical>

    <x-slot:navigation><x-navigation :showSearch="true" /></x-slot:navigation>

    @php
        $breadcrumb = [
            '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'トップ', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => '新基準原付', 'item' => route('shinkijun_gentsuki')],
            ],
        ];
        $itemList = [
            '@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => '新基準原付の対象モデル',
            'itemListElement' => $targets->values()->map(fn ($m, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1, 'name' => $m['maker'].' '.$m['name'], 'url' => url($m['seo_url']),
            ])->all(),
        ];
        // FAQ（買い手の迷い語）＝画面と JSON-LD で共用
        $faqs = [
            ['q' => '新基準原付と原付二種の違いは？', 'a' => '新基準原付は車体は110ccでも最高出力を4.0kW以下に制御し、法律上は原付一種（50cc相当）として扱われます。原付免許（または普通自動車免許）で運転でき、法定最高速度30km/h・二段階右折・二人乗り不可・白ナンバーです。一方の原付二種（51〜125cc）は小型限定二輪免許が必要で、法定速度60km/h・二人乗り可・ピンクナンバーです。'],
            ['q' => '新基準原付の中古はある？', 'a' => ($tSum['total_count'] > 0 ? 'はい。2025年登場の新しい区分ですが、MotoHubには新古車・超低走行の在庫が現在'.number_format($tSum['total_count']).'台掲載されています'.($tSum['low_man'] ? '（相場'.$tSum['low_man'].'〜'.$tSum['high_man'].'万円）' : '').'。新車の納車を待たず、いま手に入れる選択肢になります。' : '2025年登場のため中古はまだ少数です。')],
            ['q' => '新基準原付の税金・維持費は？', 'a' => '原付一種扱いのため、軽自動車税は年2,000円、ナンバーは白（原付一種）、自賠責も原付一種区分です。維持費は50cc原付と同じ水準です。'],
            ['q' => '新基準原付・中古の原付二種・50ccのどれを選ぶ？', 'a' => '免許・速度・費用で選びます。原付免許しか無く車体は新しめが良いなら新基準原付、二段階右折や二人乗り・幹線道路の流れを重視し小型二輪免許があるなら中古の原付二種、とにかく安く割り切るなら旧50ccの中古が候補です。MotoHubの実在庫の相場で比較できます。'],
            ['q' => 'なぜ50ccバイクは無くなるの？', 'a' => '令和2年（2020年）排出ガス規制で、従来の50ccエンジンの継続生産が難しくなったためです。各メーカーは110cc等を出力制御した「新基準原付」へ移行しています。'],
        ];
        $faqSchema = [
            '@context' => 'https://schema.org', '@type' => 'FAQPage',
            'mainEntity' => collect($faqs)->map(fn ($f) => [
                '@type' => 'Question', 'name' => $f['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
            ])->all(),
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($breadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @if($targets->isNotEmpty())
    <script type="application/ld+json">{!! json_encode($itemList, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
    <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    <div class="bg-gray-50 min-h-screen py-10 sm:py-16">
        <div class="max-w-5xl mx-auto px-4">

            {{-- ヒーロー --}}
            <div class="text-center mb-10">
                <h1 class="text-3xl sm:text-4xl font-black text-black mb-3 tracking-tighter">新基準原付とは？</h1>
                <p class="text-sm text-gray-500 font-bold max-w-2xl mx-auto">対象モデル・中古相場・原付二種との違いを、MotoHubの実在庫データで。</p>
                <p class="text-[11px] text-gray-400 font-bold mt-2">最終更新 {{ $updated }}</p>
            </div>

            {{-- 対象モデル（新基準原付本体） --}}
            @if($targets->isNotEmpty())
            <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-10 mb-8">
                <h2 class="text-xl font-black text-gray-800 mb-2 flex items-center gap-3">
                    <span class="w-1.5 h-6 bg-blue-500 rounded-full"></span>新基準原付の対象モデル
                </h2>
                <p class="text-xs text-gray-500 mb-6">2025年登場。MotoHubでは新古車・超低走行の在庫を掲載中（各車種ページで詳細）。</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    @foreach($targets as $m)
                        <a href="{{ $m['seo_url'] }}" class="group block bg-gray-50 rounded-2xl p-4 border border-gray-100 hover:border-blue-300 hover:shadow-md transition-all">
                            <h3 class="text-sm font-black text-gray-800 group-hover:text-blue-600 line-clamp-2 mb-1">{{ $m['name'] }}</h3>
                            <div class="text-[11px] text-gray-400 font-bold">在庫{{ number_format($m['count']) }}台</div>
                            @if($m['avg_man'])<div class="text-blue-600 font-black text-base mt-0.5">{{ $m['avg_man'] }}<span class="text-xs">万円</span></div>@endif
                            @if($m['avg_mileage'] !== null)<div class="text-[10px] text-gray-400">平均{{ number_format($m['avg_mileage']) }}km（新古車水準）</div>@endif
                        </a>
                    @endforeach
                </div>
            </section>
            @endif

            {{-- 独自データの核：4層 相場比較 --}}
            <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-10 mb-8">
                <h2 class="text-xl font-black text-gray-800 mb-2 flex items-center gap-3">
                    <span class="w-1.5 h-6 bg-green-500 rounded-full"></span>いくらで買える？ 実在庫の相場比較
                </h2>
                <p class="text-xs text-gray-500 mb-6">「新車を待つ／新基準原付の新古車を今買う／中古の原付二種／旧50cc中古」の相場を、MotoHubの実在庫で比較。</p>
                <div class="space-y-3">
                    @php
                        $rows = [
                            ['label' => '新基準原付（Lite）新古車・超低走行', 'sum' => $tSum, 'note' => '原付免許でOK・車体は新しい', 'color' => 'blue', 'link' => null],
                            ['label' => '旧仕様の110cc（原付二種）中古', 'sum' => $bSum, 'note' => '小型二輪免許・二人乗り可・60km/h', 'color' => 'green', 'link' => route('bikes.category_cc', ['slug' => '125'])],
                            ['label' => '旧50cc（原付一種）中古', 'sum' => $oSum, 'note' => 'タマは減少傾向・割り切り向け', 'color' => 'amber', 'link' => route('bikes.category_cc', ['slug' => '50'])],
                        ];
                    @endphp
                    @foreach($rows as $r)
                        @if($r['sum']['total_count'] > 0)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 rounded-2xl border border-gray-100 p-4">
                            <div>
                                <div class="text-sm font-black text-gray-900"><span class="inline-block w-2 h-2 rounded-full bg-{{ $r['color'] }}-500 mr-1.5"></span>{{ $r['label'] }}</div>
                                <div class="text-[11px] text-gray-400 font-bold mt-0.5">{{ $r['note'] }}・実在庫{{ number_format($r['sum']['total_count']) }}台</div>
                                @if($r['link'])
                                <a href="{{ $r['link'] }}" class="inline-flex items-center gap-0.5 text-[11px] font-bold text-blue-600 hover:text-blue-700 mt-1">この在庫一覧を見る<i data-lucide="chevron-right" class="w-3 h-3"></i></a>
                                @endif
                            </div>
                            <div class="shrink-0 text-right">
                                @if($r['sum']['low_man'])
                                <div class="text-lg font-black text-gray-900">{{ $r['sum']['low_man'] }}〜{{ $r['sum']['high_man'] }}<span class="text-xs font-bold text-gray-400 ml-0.5">万円</span></div>
                                <div class="text-[10px] text-gray-400">車種別の平均相場レンジ</div>
                                @endif
                            </div>
                        </div>
                        @endif
                    @endforeach
                </div>
                <p class="text-[11px] text-gray-400 mt-4">※ 相場は各車種の掲載平均から算出（MotoHub実在庫・{{ $updated }}時点）。新車メーカー希望小売価格は各モデルの公式情報をご確認ください。</p>
            </section>

            {{-- 要点解説（本体テキスト） --}}
            <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-10 mb-8 prose prose-sm max-w-none">
                <h2 class="text-xl font-black text-gray-800 !mb-4 flex items-center gap-3">
                    <span class="w-1.5 h-6 bg-purple-500 rounded-full"></span>新基準原付の基礎知識
                </h2>
                <h3 class="text-base font-black text-gray-900">新基準原付とは</h3>
                <p class="text-sm text-gray-600 leading-relaxed">令和2年（2020年）の排出ガス規制で従来の50ccエンジンの継続生産が難しくなり、各メーカーは総排気量50cc超〜125cc以下の車体を<strong>最高出力4.0kW以下に制御</strong>して、法律上は<strong>原付一種（50cc相当）</strong>として扱う「新基準原付」へ移行しています。2025年から対象モデルが登場しました。</p>
                <h3 class="text-base font-black text-gray-900">原付二種との違い（ここが迷いどころ）</h3>
                <ul class="text-sm text-gray-600 leading-relaxed">
                    <li><strong>免許</strong>：新基準原付＝原付免許（普通自動車免許でも可）／原付二種＝小型限定二輪免許以上</li>
                    <li><strong>法定最高速度</strong>：新基準原付＝30km/h／原付二種＝60km/h</li>
                    <li><strong>二段階右折・二人乗り</strong>：新基準原付＝二段階右折あり・二人乗り不可／原付二種＝二段階右折なし・二人乗り可</li>
                    <li><strong>ナンバー・税金</strong>：新基準原付＝白ナンバー・軽自動車税 年2,000円／原付二種＝ピンクナンバー・年2,400円</li>
                </ul>
                <p class="text-sm text-gray-600 leading-relaxed">車体は同じ110ccでも、<strong>扱いは50cc原付とまったく同じ</strong>という点が最大のポイントです。</p>
                <h3 class="text-base font-black text-gray-900">どれを選ぶ？</h3>
                <p class="text-sm text-gray-600 leading-relaxed">原付免許しか無く新しめの車体が良いなら<strong>新基準原付</strong>（新車待ちなら上の新古車在庫が近道）。二段階右折や二人乗り・幹線道路の流れを重視し小型二輪免許があるなら<strong>中古の原付二種</strong>。とにかく安く割り切るなら<strong>旧50ccの中古</strong>（ただしタマは減少傾向）。上の相場比較で、実在庫の価格差をそのまま判断材料にできます。</p>
            </section>

            {{-- FAQ（視認・FAQPageと同内容） --}}
            <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-10 mb-8" x-data="{ open: 0 }">
                <h2 class="text-xl font-black text-gray-800 mb-6 flex items-center gap-3">
                    <span class="w-1.5 h-6 bg-orange-500 rounded-full"></span>よくある質問
                </h2>
                <div class="space-y-2">
                    @foreach($faqs as $i => $f)
                    <div class="rounded-2xl border border-gray-100 overflow-hidden">
                        <button type="button" @click="open === {{ $i }} ? open = null : open = {{ $i }}" class="w-full flex items-center justify-between gap-3 p-4 text-left">
                            <span class="text-sm font-black text-gray-900">{{ $f['q'] }}</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 shrink-0 transition-transform" :class="open === {{ $i }} && 'rotate-180'"></i>
                        </button>
                        <div x-show="open === {{ $i }}" x-collapse class="px-4 pb-4 -mt-1">
                            <p class="text-sm text-gray-600 leading-relaxed">{{ $f['a'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </section>

            {{-- ハブ&スポーク：比較対象の車種ページへ --}}
            @if($bases->isNotEmpty() || $old50->isNotEmpty())
            <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-10 mb-8">
                <h2 class="text-xl font-black text-gray-800 mb-6 flex items-center gap-3">
                    <span class="w-1.5 h-6 bg-gray-400 rounded-full"></span>比較対象モデルの相場・在庫を見る
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                    @foreach($bases->concat($old50) as $m)
                        <a href="{{ $m['seo_url'] }}" class="group flex items-center justify-between px-4 py-3 rounded-xl bg-gray-50 border border-gray-100 hover:border-blue-200 hover:bg-blue-50 transition-colors">
                            <span class="text-xs font-black text-gray-700 group-hover:text-blue-600 truncate">{{ $m['name'] }}</span>
                            <span class="shrink-0 text-[10px] font-bold text-gray-400">{{ number_format($m['count']) }}台</span>
                        </a>
                    @endforeach
                </div>
            </section>
            @endif

            {{-- 保険ハブへの相互リンク（ファミバイ特約の文脈・双方向） --}}
            <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h2 class="text-base font-black text-gray-800 mb-2">保険・維持費について</h2>
                <p class="text-[13px] text-gray-600 leading-relaxed mb-3">新基準原付は原付一種扱い（軽自動車税 年2,000円）で、<strong>ファミリーバイク特約</strong>の対象です。税金・自賠責・任意保険の考え方はこちら。</p>
                <a href="{{ route('hoken') }}" class="inline-flex items-center gap-1 text-[13px] font-black text-blue-600 hover:text-blue-700 transition-colors">
                    バイクの保険と維持費ガイド <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </section>

        </div>
    </div>
</x-layout>
