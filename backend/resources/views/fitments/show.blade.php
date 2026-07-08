@php
    $taskLabel = $taskConfig['label'] ?? $task;
    $modelName = $bikeModel->name;
    $single = $fitments->count() === 1;
    $first = $fitments->first();
    $isOil = $task === 'oil';
    // oil は「品番」でなく粘度/JASO/量を扱うため、見出し・meta を task で出し分ける。
    $pageNoun = $isOil ? 'エンジンオイル（粘度・量）' : "{$taskLabel}型番"; // title 用
    $crumbNoun = $isOil ? 'エンジンオイル' : "{$taskLabel}型番";           // パンくず用
    $h1 = $isOil
        ? "{$modelName}のエンジンオイル（粘度・量）【型式別】"
        : "{$modelName}の{$taskLabel}型番と交換方法【型式別】";
    // meta description（120字以内）: 推奨（粘度/品番）を列挙
    $recos = $fitments->pluck('recommended_part_no')->unique()->take(6)->implode('・');
    $metaDesc = $isOil
        ? mb_substr("{$modelName}のエンジンオイルの粘度・JASO規格・交換量を型式別にまとめました。推奨粘度: {$recos}。純正銘柄・交換手順も掲載。", 0, 120)
        : mb_substr("{$modelName}の{$taskLabel}型番・適合一覧【型式別】。推奨品番: {$recos}。新車搭載品番・互換品番・交換手順もまとめています。", 0, 120);
    $breadcrumb = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'ホーム', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $modelName, 'item' => url($bikeModel->seo_url)],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $crumbNoun],
        ],
    ];

    // ── task別の静的文言（事実はDB由来・ここはUIコピーのみ）──
    $compatColLabel = $isOil ? '純正銘柄' : '互換品番';
    // 価格比較の keyword/label ビルダー（task差はここに集約）
    $primaryKeywordFor = fn ($rec) => $isOil ? "バイク エンジンオイル {$rec}" : $rec;
    $primaryLabelFor = function ($rec) use ($task, $isOil) {
        if ($task === 'plug') {
            return '標準プラグの価格を比較';
        }
        if ($isOil) {
            return "{$rec}のオイルを比較";
        }

        return '価格を比較';
    };
    // 上位/純正: 互換先頭から (keyword,label)。oil は純正銘柄フルネームで検索、他は品番で検索。
    $secondaryFor = function ($top) use ($isOil) {
        $brand = trim($top['brand'] ?? '');
        $partNo = trim($top['part_no'] ?? '');
        if ($isOil) {
            $name = ($brand !== '' && $partNo !== '' && $brand !== $partNo) ? "{$brand} {$partNo}" : ($brand !== '' ? $brand : $partNo);
            return $name === '' ? null : ['keyword' => $name, 'label' => "{$name}を比較"];
        }
        if ($partNo === '') {
            return null;
        }

        return ['keyword' => $partNo, 'label' => ($brand !== '' ? "{$brand}の価格を比較" : 'この品番の価格を比較')];
    };
    // spec 表示: 既知キーのみ。label=null は値だけ、label有りは「ラベル: 値+suffix」。未知キーは非表示。
    $specRender = [
        'voltage' => ['label' => null, 'suffix' => ''],
        'capacity' => ['label' => null, 'suffix' => ''],
        'type' => ['label' => null, 'suffix' => ''],
        'heat' => ['label' => '熱価', 'suffix' => '番'],
        'plugs' => ['label' => '必要本数', 'suffix' => '本'],
        'jaso' => ['label' => 'JASO規格', 'suffix' => ''],
        'capacity_change' => ['label' => '交換時', 'suffix' => ''],
        'capacity_filter' => ['label' => 'フィルター交換時', 'suffix' => ''],
        'capacity_strainer' => ['label' => 'ストレーナー清掃時', 'suffix' => ''],
        'interval' => ['label' => '交換目安', 'suffix' => ''],
    ];
    $stepMarks = ['①', '②', '③', '④', '⑤', '⑥'];
    $stepsByTask = [
        'battery' => [
            'エンジンを止めキーを抜く',
            'バッテリーカバーを外す',
            '<b>マイナス端子 → プラス端子の順に外す</b>',
            '新しいバッテリーを載せ、<b>プラス端子 → マイナス端子の順に接続</b>',
            'カバーを戻し、始動確認',
        ],
        'plug' => [
            'エンジンを止め、<b>完全に冷えてから</b>作業する（火傷・ネジかじり防止）',
            'プラグキャップ（コード）をまっすぐ引き抜く',
            'プラグレンチで反時計回りに外す',
            '新しいプラグを<b>手で回せるところまで仮締め</b>してから、レンチで軽く締める（締めすぎ注意）',
            'キャップを戻して始動確認',
        ],
        'oil' => [
            'エンジンを2〜3分暖機してから止める（オイルが抜けやすくなる）',
            'ドレンボルトを外して古いオイルを抜く（火傷注意・ドレンワッシャーは毎回交換）',
            'ドレンボルトを規定トルクで締める（締めすぎはネジ山破損の原因）',
            '規定量の新しいオイルを入れる（入れすぎ注意・少なめから調整）',
            'エンジンをかけて数分アイドリング→停止→数分後にレベルゲージ/点検窓で油面確認',
        ],
    ];
    $steps = $stepsByTask[$task] ?? $stepsByTask['battery'];
    $noticeByTask = [
        'battery' => 'VRLA（MF）バッテリーには即用式と液入充電済タイプがあります。バッテリーが斜めに搭載される車種では液入充電済タイプを選んでください。外した古いバッテリーは自治体のルールに従うか、バイク店・購入店での引き取りを利用してください。',
        'plug' => '品番の数字（熱価）は指定から変えないのが基本です。取り付け時は必ず手で仮締めしてから工具を使ってください（斜めに入るとネジ山を傷めます）。2気筒車はプラグ2本を同時交換するのが基本です。',
        'oil' => 'JASO規格（MA/MB）を必ず合わせてください。カブ系・MT車（湿式クラッチ）はMA、スクーター（CVT）はMB指定です。スクーター用のMBオイルをMA指定車に入れるとクラッチが滑ることがあります。粘度（10W-30等）も指定から変えないのが基本です。オイル量は少なすぎ・多すぎともに不調の原因になるため、必ずレベルゲージ/点検窓で確認してください。抜いた廃油は自治体のルールに従って処分してください。',
    ];
    $notice = $noticeByTask[$task] ?? $noticeByTask['battery'];
    $faqsByTask = [
        'battery' => [
            ['q' => '充電すれば復活する？', 'a' => '一時的に復活することはありますが、劣化が進んだバッテリーは再発しやすいです。始動不良を繰り返すなら交換が確実です。'],
            ['q' => '純正品番と互換品番の違いは？', 'a' => '規格が互換のため使用できます。メーカー・保証・価格が異なります。'],
            ['q' => '寿命の目安は？', 'a' => '使い方によりますが一般に2〜5年程度です。短距離走行が中心では短くなりやすいです。'],
        ],
        'plug' => [
            ['q' => '交換時期の目安は？', 'a' => '一般に標準プラグは3,000〜5,000km程度が点検・交換の目安です。イリジウム系・MotoDXは長寿命です。'],
            ['q' => '標準とイリジウム（MotoDX）の違いは？', 'a' => '着火性・始動性・寿命に優れるぶん価格は高めです。適合品番は表の互換欄を参照してください。'],
            ['q' => '熱価とは？', 'a' => '品番中の数字です。指定から変えると不調や損傷の原因になるため、基本は変えません。'],
        ],
        'oil' => [
            ['q' => '交換時期は？', 'a' => '一般に3,000km（PCX等一部6,000km）または半年〜1年が目安です。短距離走行が中心の場合は早めに交換してください。'],
            ['q' => '粘度の異なるオイルを混ぜていい？', 'a' => '応急的には可能ですが、次回は指定の単一粘度に戻すのが基本です。JASO規格（MA/MB）が違うものの混用は避けてください。'],
            ['q' => 'カブに車用オイルは使える？', 'a' => '使えますがJASO MA非対応品はクラッチ滑りのリスクがあります。二輪指定のMAオイルが安全です。'],
        ],
    ];
    $faqs = $faqsByTask[$task] ?? $faqsByTask['battery'];
@endphp
<x-layout>
    <x-slot:title>{{ $modelName }}の{{ $pageNoun }}・適合一覧【型式別】| MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $metaDesc }}</x-slot:metaDescription>

    <x-slot:styles>
        <script type="application/ld+json">
            {!! json_encode($breadcrumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    </x-slot:styles>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- 1. パンくず --}}
            <nav class="flex text-xs font-bold text-gray-400 mb-6 overflow-x-auto scrollbar-hide" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 whitespace-nowrap">
                    <li><a href="/" class="hover:text-gray-600">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ $bikeModel->seo_url }}" class="hover:text-gray-600">{{ $modelName }}</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $taskLabel }}型番</span></li>
                </ol>
            </nav>

            {{-- 2. H1 --}}
            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-4 leading-snug">
                {{ $h1 }}
            </h1>

            {{-- 3. 即答ボックス --}}
            <div class="bg-blue-50 border border-blue-200 rounded-2xl p-5 sm:p-6 mb-6">
                @if($single)
                    @if($isOil)
                    @php $ospec = (array) ($first->spec ?? []); @endphp
                    <p class="text-base font-bold text-gray-800">
                        {{ $modelName }}のオイルは
                        <span class="text-xl font-black text-blue-700">{{ $first->recommended_part_no }}</span>
                        @if(!empty($ospec['capacity_change']))・交換量 {{ $ospec['capacity_change'] }}@endif
                        @if(!empty($ospec['jaso']))<span class="text-sm text-gray-500">（{{ $ospec['jaso'] }}）</span>@endif
                    </p>
                    @else
                    <p class="text-base font-bold text-gray-800">
                        {{ $modelName }}の{{ $taskLabel }}は
                        <span class="text-xl font-black text-blue-700">{{ $first->recommended_part_no }}</span>
                        @if($first->oem_part_no)<span class="text-sm text-gray-500">（新車搭載: {{ $first->oem_part_no }}）</span>@endif
                    </p>
                    @endif
                @else
                    <p class="text-base font-bold text-gray-800">
                        {{ $modelName }}は型式によって{{ $taskLabel }}が異なります。下の表でお使いの型式をご確認ください。
                    </p>
                    <a href="#fitment-table" class="inline-flex items-center gap-1 mt-2 text-sm font-bold text-blue-600 hover:underline">
                        <i data-lucide="arrow-down" class="w-4 h-4"></i> 適合表を見る
                    </a>
                @endif
            </div>

            {{-- 4. 適合表 --}}
            <section id="fitment-table" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6">
                <h2 class="text-lg font-black text-gray-900 mb-4">{{ $modelName }} {{ $taskLabel }}適合表</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="text-left text-xs font-bold text-gray-500 border-b-2 border-gray-100">
                                <th class="py-2 pr-3 whitespace-nowrap">型式</th>
                                <th class="py-2 pr-3 whitespace-nowrap">年式</th>
                                @if($isOil)
                                <th class="py-2 pr-3 whitespace-nowrap">推奨粘度</th>
                                <th class="py-2 pr-3 whitespace-nowrap">JASO</th>
                                <th class="py-2 pr-3 whitespace-nowrap">交換量</th>
                                @else
                                <th class="py-2 pr-3 whitespace-nowrap">新車搭載</th>
                                <th class="py-2 pr-3 whitespace-nowrap">推奨品番</th>
                                @endif
                                <th class="py-2 pr-3">{{ $compatColLabel }}</th>
                                <th class="py-2 pr-3">備考</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fitments as $f)
                            <tr class="border-b border-gray-50 align-top">
                                <td class="py-3 pr-3 font-bold text-gray-800 whitespace-nowrap">{{ $f->frame_code !== '' ? $f->frame_code : '—' }}</td>
                                <td class="py-3 pr-3 text-gray-600 whitespace-nowrap">{{ $f->year_range !== '' ? $f->year_range : '—' }}</td>
                                @if($isOil)
                                <td class="py-3 pr-3 font-black text-blue-700 whitespace-nowrap">{{ $f->recommended_part_no }}</td>
                                <td class="py-3 pr-3 text-gray-600 whitespace-nowrap">{{ data_get($f->spec, 'jaso', '—') }}</td>
                                <td class="py-3 pr-3 text-gray-600 whitespace-nowrap">{{ data_get($f->spec, 'capacity_change', '—') }}</td>
                                @else
                                <td class="py-3 pr-3 text-gray-600 whitespace-nowrap">{{ $f->oem_part_no ?? '—' }}</td>
                                <td class="py-3 pr-3 font-black text-blue-700 whitespace-nowrap">{{ $f->recommended_part_no }}</td>
                                @endif
                                <td class="py-3 pr-3 text-gray-600">
                                    @if(!empty($f->compatible_part_nos))
                                        @foreach($f->compatible_part_nos as $c)
                                        @php $cb = trim($c['brand'] ?? ''); $cp = trim($c['part_no'] ?? ''); @endphp
                                        <div class="whitespace-nowrap">@if($cb !== '' && $cp !== '' && $cb !== $cp){{ $cb }}: <span class="font-bold">{{ $cp }}</span>@else<span class="font-bold">{{ $cb !== '' ? $cb : $cp }}</span>@endif</div>
                                        @endforeach
                                    @else
                                        —
                                    @endif
                                </td>
                                {{-- 備考: 公開用 note_public のみ表示（内部メモは絶対に出さない）。min-width で縦積み防止＋2行クランプ --}}
                                <td class="py-3 pr-3 text-xs text-gray-500">
                                    @if(!empty($f->note_public))
                                    <div class="min-w-[7em] max-w-[16em] line-clamp-2 break-words" title="{{ $f->note_public }}">{{ $f->note_public }}</div>
                                    @else
                                    <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="py-3 whitespace-nowrap">
                                    <div class="flex flex-col gap-1.5">
                                        {{-- 標準（推奨品番／粘度） --}}
                                        <a href="{{ route('parts.compare', ['keyword' => $primaryKeywordFor($f->recommended_part_no), 'ref' => 'fitment']) }}"
                                           class="inline-flex items-center gap-1 bg-orange-500 hover:bg-orange-600 text-white text-xs font-black px-3 py-2 rounded-lg transition">
                                            <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i> {{ $primaryLabelFor($f->recommended_part_no) }}
                                        </a>
                                        {{-- 上位/純正（互換先頭） --}}
                                        @if(!empty($f->compatible_part_nos))
                                            @php $sec = $secondaryFor($f->compatible_part_nos[0]); @endphp
                                            @if($sec)
                                            <a href="{{ route('parts.compare', ['keyword' => $sec['keyword'], 'ref' => 'fitment']) }}"
                                               class="inline-flex items-center gap-1 bg-white border border-orange-400 text-orange-600 hover:bg-orange-50 text-xs font-black px-3 py-2 rounded-lg transition">
                                                <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i> {{ $sec['label'] }}
                                            </a>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @php
                    $specRow = $fitments->firstWhere('spec', '!=', null);
                    // 既知キーのみ整形（未知キーは非表示）。oil は表に列があるキー(jaso/交換量)を規格行から除く。
                    $specLineExclude = $isOil ? ['jaso', 'capacity_change'] : [];
                    $specParts = [];
                    foreach ((array) ($specRow->spec ?? []) as $k => $v) {
                        if (! isset($specRender[$k]) || in_array($k, $specLineExclude, true) || $v === '' || $v === null) {
                            continue;
                        }
                        $r = $specRender[$k];
                        $specParts[] = $r['label'] ? "{$r['label']}: {$v}{$r['suffix']}" : (string) $v;
                    }
                @endphp
                @if(!empty($specParts))
                <p class="text-xs text-gray-400 mt-3">規格: {{ implode(' / ', $specParts) }}</p>
                @endif
            </section>

            {{-- 4.5 交換費用の目安（oil のみ・車種単位で代表1ブロック・費用が入っている行を1つ採用） --}}
            @if($isOil)
                @php
                    $costRow = $fitments->first(fn ($f) => filled($f->cost_oil_range) || filled($f->cost_shop_range) || filled($f->cost_diy_range));
                @endphp
                @if($costRow)
                <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6 mb-6">
                    <h2 class="text-base font-black text-gray-900 mb-3 flex items-center gap-2">
                        💴 交換費用の目安
                        <span class="text-xs font-bold text-gray-400">@if($costRow->cost_updated_at)（{{ $costRow->cost_updated_at }}時点・@endif一般的なバイク用品店基準）</span>
                    </h2>
                    <dl class="divide-y divide-gray-100 text-sm">
                        @if(filled($costRow->cost_oil_range))
                        <div class="flex items-center justify-between py-2 first:pt-0">
                            <dt class="text-gray-600">オイル代（部品のみ）</dt>
                            <dd class="font-black text-gray-800">{{ $costRow->cost_oil_range }}</dd>
                        </div>
                        @endif
                        @if(filled($costRow->cost_shop_range))
                        <div class="flex items-center justify-between py-2">
                            <dt class="text-gray-600">店で交換（総額）</dt>
                            <dd class="font-black text-blue-700">{{ $costRow->cost_shop_range }}</dd>
                        </div>
                        @endif
                        @if(filled($costRow->cost_diy_range))
                        <div class="flex items-center justify-between py-2 last:pb-0">
                            <dt class="text-gray-600">自分で交換</dt>
                            <dd class="font-black text-gray-800">{{ $costRow->cost_diy_range }}</dd>
                        </div>
                        @endif
                    </dl>
                    <p class="text-[11px] text-gray-400 mt-3">※ 工賃は店舗・地域で変わります。フィルター交換時は別途。</p>
                </section>
                @endif
            @endif

            {{-- 5. 在庫リンク --}}
            @if($stockCount > 0)
            <a href="{{ route('bikes.search', ['bike_model_id' => $bikeModel->id]) }}"
               class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 p-5 mb-6 hover:border-blue-300 hover:shadow-sm transition">
                <span class="text-sm font-black text-gray-800">{{ $modelName }}の中古車 {{ number_format($stockCount) }}件を見る</span>
                <i data-lucide="chevron-right" class="w-5 h-5 text-gray-300"></i>
            </a>
            @endif

            {{-- 6. 型式の調べ方 --}}
            <div class="mb-6">
                @include('fitments._frame-code-guide')
            </div>

            {{-- 7. 交換手順の要約 --}}
            <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6 mb-6">
                <h2 class="text-base font-black text-gray-900 mb-3">{{ $taskLabel }}交換の手順（要約）</h2>
                <ol class="space-y-2 text-sm text-gray-700">
                    @foreach($steps as $i => $s)
                    <li>{{ $stepMarks[$i] ?? ($i + 1).'.' }} {!! $s !!}</li>
                    @endforeach
                </ol>
                @if(!empty($taskConfig['article_url']))
                <a href="{{ $taskConfig['article_url'] }}"
                   class="inline-flex items-center gap-1 mt-4 text-sm font-bold text-blue-600 hover:underline">
                    写真つきの詳しい手順はこちら <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
                @endif
            </section>

            {{-- 8. 選び方の注意 --}}
            <section class="bg-amber-50 border border-amber-200 rounded-2xl p-5 sm:p-6 mb-6">
                <h2 class="text-base font-black text-gray-900 mb-2 flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600"></i> 選び方の注意
                </h2>
                <p class="text-sm text-gray-700 leading-relaxed">{{ $notice }}</p>
            </section>

            {{-- 9. お店に任せる --}}
            <a href="{{ route('shops.repair.index') }}"
               class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 p-5 mb-6 hover:border-green-300 hover:shadow-sm transition">
                <span class="flex items-center gap-2 text-sm font-black text-gray-800">
                    <i data-lucide="wrench" class="w-4 h-4 text-green-600"></i> 交換を頼めるお店を探す
                </span>
                <i data-lucide="chevron-right" class="w-5 h-5 text-gray-300"></i>
            </a>

            {{-- 10. 診断への逆リンク --}}
            @if(!empty($taskConfig['trouble_symptom']))
            <a href="{{ route('trouble.index') }}?symptom={{ $taskConfig['trouble_symptom'] }}"
               class="block text-center text-sm font-bold text-gray-500 hover:text-blue-600 mb-8">
                エンジンがかからない原因は{{ $taskLabel }}以外にもあります → 症状を診断する
            </a>
            @endif

            {{-- 11. FAQ（構造化データは付けない・可視のみ） --}}
            <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6 mb-6" x-data="{ open: null }">
                <h2 class="text-base font-black text-gray-900 mb-4">よくある質問</h2>
                <div class="divide-y divide-gray-100">
                    @foreach($faqs as $i => $faq)
                    <div class="py-3 first:pt-0 last:pb-0">
                        <button type="button" @click="open = open === {{ $i }} ? null : {{ $i }}" class="w-full flex items-start justify-between gap-3 text-left">
                            <span class="text-sm font-bold text-gray-800">{{ $faq['q'] }}</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 shrink-0 mt-0.5" x-bind:class="{ 'rotate-180': open === {{ $i }} }"></i>
                        </button>
                        <div x-show="open === {{ $i }}" x-transition style="display:none;">
                            <p class="text-sm text-gray-600 mt-2">{{ $faq['a'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </section>

            {{-- 12. 出典・免責 --}}
            @include('fitments._disclaimer')

        </div>
    </div>
</x-layout>
