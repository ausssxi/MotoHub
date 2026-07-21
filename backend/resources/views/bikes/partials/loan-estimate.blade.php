{{-- ローン「月々の目安」サーバー描画ブロック（純情報・JS不要・完全静的）。
     価格ごとにユニークなテキスト＝薄コンテンツ対策。隣のJS計算機(#loan-simulator)とは役割分担。
     $loanEstimate は App\Support\LoanEstimate::forListing() の結果（価格欠損/min_price未満なら null）。 --}}
@if(!empty($loanEstimate))
<div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mt-8">
    <div class="flex items-center gap-2 mb-3">
        <span class="bg-green-100 text-green-700 p-2 rounded-lg shrink-0"><i data-lucide="receipt-japanese-yen" class="w-5 h-5"></i></span>
        <h3 class="text-lg font-black text-gray-900">このバイクをローンで購入した場合の目安</h3>
    </div>

    <p class="text-sm text-gray-600 leading-relaxed mb-4">
        価格 <span class="font-bold text-gray-900">¥{{ number_format($loanEstimate['price']) }}</span> を、頭金0円・ボーナス払いなし・実質年率<span class="font-bold text-gray-900">{{ $loanEstimate['apr'] }}%（例）</span>で試算した、月々のお支払いの<span class="font-bold">目安</span>です。
    </p>

    <div class="overflow-hidden rounded-2xl border border-gray-100">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-500">
                    <th class="text-left font-bold px-4 py-2.5">支払回数</th>
                    <th class="text-right font-bold px-4 py-2.5">月々（目安）</th>
                    <th class="text-right font-bold px-4 py-2.5">支払総額（目安）</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($loanEstimate['rows'] as $row)
                <tr>
                    <td class="px-4 py-3 font-bold text-gray-700">{{ $row['term'] }}回<span class="text-gray-400 font-normal text-xs ml-1">({{ intdiv($row['term'], 12) }}年)</span></td>
                    <td class="px-4 py-3 text-right font-black text-green-600">¥{{ number_format($row['monthly']) }}</td>
                    <td class="px-4 py-3 text-right font-bold text-gray-600">¥{{ number_format($row['total']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4 space-y-1 text-[11px] text-gray-400 leading-relaxed">
        <p>※金利は一般的なバイクローン（ディーラー・信販系）を想定した例です。実際の金利・審査結果・販売店の条件により支払額は異なります。</p>
        <p>※MotoHubはローンの契約・媒介を行いません。（{{ $loanEstimate['source_note'] }}／最終確認: {{ $loanEstimate['checked_at'] }}）</p>
    </div>
</div>
@endif
