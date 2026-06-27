{{-- 排気量別 流通台数ランキング（販売=成約とは別指標＝掲載中の在庫数）。
     集計はデータAPI(/api/v1/rankings/listings)と同じ ClassRankingService＝数字は一致。 --}}
@if(!empty($classRanking))
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6" x-data="{ tab: '250' }">
    <h2 class="text-base font-black text-gray-900 mb-2 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        排気量別 流通台数ランキング
    </h2>

    {{-- 必須: 「流通台数（掲載中の在庫）」である旨の明記（販売台数との混同防止） --}}
    <p class="text-xs text-gray-500 leading-relaxed mb-4 bg-blue-50/50 border border-blue-100 rounded-xl p-3">
        このランキングは、現在MotoHubに掲載中の中古バイク在庫の台数（流通量）に基づきます。「売れた台数」ではなく「今、中古市場に出ている台数」のランキングです。
    </p>

    {{-- クラスタブ（クライアント側切替・全パネルはDOMに残しSEOインデックス対象） --}}
    <div class="flex gap-1.5 mb-4 overflow-x-auto scrollbar-hide">
        @foreach($classRanking as $class => $data)
        <button type="button" @click="tab = '{{ $class }}'"
            :class="tab === '{{ $class }}' ? 'bg-blue-600 text-white border-blue-600' : 'bg-gray-50 text-gray-600 border-gray-200 hover:border-blue-300'"
            class="shrink-0 text-xs font-bold px-4 py-2 rounded-xl border transition-colors">{{ $data['label'] }}</button>
        @endforeach
    </div>

    @foreach($classRanking as $class => $data)
    <div x-show="tab === '{{ $class }}'" x-cloak>
        <h3 class="text-sm font-black text-gray-900 mb-1">{{ $data['heading'] }} 中古バイクの流通台数ランキング</h3>
        @if(!empty($data['rows']))
        {{-- データ解説文（インデックス対策・top1を動的出力） --}}
        <p class="text-[11px] text-gray-500 mb-3">{{ $data['heading'] }}クラスでは、現在「{{ $data['rows'][0]['model'] }}」が最も多く中古市場に流通しています（掲載中{{ number_format($data['rows'][0]['count']) }}台）。</p>

        @php $maxCount = collect($data['rows'])->max('count') ?: 1; @endphp
        <div class="space-y-1">
            @foreach($data['rows'] as $i => $row)
            @php
                $rank = $i + 1;
                $medal = match($rank) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => null };
                $modelLink = ! empty($row['bike_model_id']) ? route('ranking.model_stats', $row['bike_model_id']) : ($row['seo_url'] ?? '#');
            @endphp
            <div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-gray-50 transition-colors group">
                {{-- 順位（上位3位はメダル・既存TOP30と同じ） --}}
                <div class="w-8 text-center flex-shrink-0">
                    @if($medal)
                    <span class="text-lg">{{ $medal }}</span>
                    @else
                    <span class="text-sm font-black {{ $rank <= 10 ? 'text-gray-700' : 'text-gray-400' }}">{{ $rank }}</span>
                    @endif
                </div>

                {{-- 車両画像（TOP20=_model_ranking と同じソース(image_url)・サイズ・プレースホルダ挙動） --}}
                <a href="{{ $modelLink }}" class="w-12 h-12 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0 block">
                    @if(!empty($row['image_url']))
                    <img src="{{ $row['image_url'] }}" alt="" class="w-full h-full object-cover" loading="lazy" onerror="this.style.display='none'">
                    @endif
                </a>

                {{-- 車種名・メーカー（車種ページへ内部リンク・既存と同じ張り方） --}}
                <div class="flex-1 min-w-0">
                    <a href="{{ $modelLink }}" class="text-sm font-bold text-gray-900 truncate block group-hover:text-blue-600 transition-colors">{{ $row['model'] }}</a>
                    <p class="text-[11px] text-gray-400 font-bold">{{ $row['maker'] }}</p>
                </div>

                {{-- 流通台数（掲載中の在庫） --}}
                <div class="text-right flex-shrink-0">
                    <span class="text-sm font-black {{ $rank <= 3 ? 'text-yellow-600' : 'text-gray-700' }}">{{ number_format($row['count']) }}</span>
                    <span class="text-[10px] text-gray-400">台</span>
                </div>

                {{-- バー --}}
                <div class="w-16 sm:w-24 bg-gray-100 rounded-full h-2 flex-shrink-0 hidden sm:block">
                    <div class="h-full rounded-full {{ $rank <= 3 ? 'bg-yellow-400' : 'bg-gray-300' }}"
                         style="width: {{ max(($row['count'] / $maxCount) * 100, 3) }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-xs text-gray-400 py-4">現在このクラスのデータがありません。</p>
        @endif
    </div>
    @endforeach

    {{-- 控えめなデータ注記。API/データ提供の本格ページは別タスクのため、ここではリンクではなく注記のみ --}}
    <p class="text-[10px] text-gray-400 mt-4">※流通台数は現在MotoHubに掲載中の中古バイク在庫に基づき、定期的に更新されます（販売台数ランキングとは別の指標です）。</p>
</div>
@endif
