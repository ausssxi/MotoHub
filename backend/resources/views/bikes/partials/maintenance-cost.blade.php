{{-- 車種ページ「維持費（税金・保険）」ブロック（面②）。静的差込＝$model->displacement + config のみ。
     ★キャッシュbump不要（buildModelDetailData の配列に依存しない）。
     ★正直さ: 公的固定額（軽自動車税・自賠責）だけを出典付きで出す。任意保険の額は出さずハブへ誘導。 --}}
@php
    $ins = \App\Support\InsuranceClassifier::bracketForModel($model);
@endphp
@if($ins)
@php
    $insSrcTax = config('insurance.sources.tax');
    $insSrcJib = config('insurance.sources.jibaiseki');
    $insVerified = config('insurance.last_verified');
    $insJibTable = $ins['jibaiseki_table'] ?? null;
@endphp
<div class="bg-white rounded-3xl shadow-sm p-5 sm:p-6 border border-gray-100">
    <h2 class="text-lg font-black text-gray-900 flex items-center gap-2 mb-1">
        <i data-lucide="receipt-japanese-yen" class="w-5 h-5 text-emerald-600"></i>
        {{ $model->name }}の維持費（税金・保険）
    </h2>
    <p class="text-[11px] font-bold text-gray-400 mb-4">
        区分: {{ $ins['label'] }}
        @if(!empty($ins['shaken']))・車検あり@else・車検なし@endif
    </p>

    {{-- 軽自動車税（種別割・年額） --}}
    <div class="mb-5">
        <p class="text-sm font-black text-gray-800 mb-2">軽自動車税（種別割・年額）</p>
        <div class="flex items-baseline gap-2 bg-emerald-50 border border-emerald-100 rounded-xl px-4 py-3">
            <span class="text-2xl font-black text-emerald-700">{{ number_format($ins['tax']) }}</span>
            <span class="text-sm font-bold text-emerald-600">円 / 年</span>
        </div>
        @if($ins['key'] === 'shinkijun' && !empty($ins['note']))
            <p class="text-[11px] text-gray-500 font-bold mt-2 leading-relaxed"><i data-lucide="info" class="w-3 h-3 inline text-amber-500"></i> {{ $ins['note'] }}</p>
        @endif
    </div>

    {{-- 自賠責保険料（強制保険・契約期間別） --}}
    @if($insJibTable)
    <div class="mb-5">
        <p class="text-sm font-black text-gray-800 mb-2">自賠責保険料（強制保険・{{ $insJibTable['label'] }}）</p>
        <div class="overflow-x-auto scrollbar-hide">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="text-gray-400 text-[11px]">
                        @foreach($insJibTable['terms'] as $months => $amount)
                        <th class="font-bold px-2 py-1 text-center border-b border-gray-100">{{ $months }}ヶ月</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @foreach($insJibTable['terms'] as $months => $amount)
                        <td class="px-2 py-2 text-center font-black text-gray-800">{{ number_format($amount) }}円</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
        @if(!empty($ins['shaken']))
            <p class="text-[11px] text-gray-400 font-bold mt-1.5">※250cc超は車検があり、車検期間に応じた月数（25ヶ月・37ヶ月等）もあります。</p>
        @endif
        <p class="text-[11px] text-amber-600 font-bold mt-2 leading-relaxed"><i data-lucide="alert-triangle" class="w-3 h-3 inline"></i> {{ config('insurance.jibaiseki_revision_note') }}</p>
    </div>
    @endif

    {{-- 任意保険（★金額は出さない・創作しない。選び方と一括見積もりはハブへ内部リンク） --}}
    <div class="bg-blue-50/60 border border-blue-100 rounded-xl px-4 py-3">
        <p class="text-sm font-black text-gray-800 mb-1">任意保険（別途）</p>
        <p class="text-[12px] text-gray-600 leading-relaxed mb-2">
            任意保険の保険料は<strong>年齢・等級・補償内容で大きく変わる</strong>ため、一律の目安額はありません。
            @if(!empty($ins['family_tokuyaku']))
            125cc以下は自動車保険の<strong>ファミリーバイク特約</strong>で安く備えられる場合があります。
            @endif
        </p>
        <a href="{{ route('hoken') }}" class="inline-flex items-center gap-1 text-[13px] font-black text-blue-600 hover:text-blue-700 transition-colors">
            バイク保険の選び方・一括見積もりガイド <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
        </a>
    </div>

    {{-- 出典・最終確認（金額を出すブロックの生命線） --}}
    <p class="text-[10px] text-gray-400 font-bold mt-4 leading-relaxed">
        最終確認: {{ $insVerified }}
        ・出典: <a href="{{ $insSrcTax['url'] }}" target="_blank" rel="nofollow noopener" class="underline hover:text-gray-600">{{ $insSrcTax['name'] }}</a>、<a href="{{ $insSrcJib['url'] }}" target="_blank" rel="nofollow noopener" class="underline hover:text-gray-600">{{ $insSrcJib['name'] }}</a>
    </p>
</div>
@endif
