{{--
    盗難保険（ZuttoRide）アフィリエイトCTA
    方針:
    - 出所は config/theft.php の affiliate 一本（盗難ページと同じ単一の真実）。
    - コントローラは触らず、ビューから config() を直接読む。
    - url 未設定の間は枠ごと描画しない（偽ボタンを置かない）。
    - 表示時は PR 表記と rel="nofollow sponsored" を必ず付ける。
    - imp_url があるときだけ 1x1 の計測画像を出す。
--}}
@php
    $aff    = config('theft.affiliate', []);
    $ctaUrl = $aff['url'] ?? '';
@endphp
@if($ctaUrl !== '')
<aside class="bg-gray-900 rounded-xl p-5 mb-4 text-center">
    <h2 class="text-white text-base font-black mb-1">{{ $aff['headline'] ?? 'バイク盗難保険を無料で見積もり' }}</h2>
    <p class="text-white/60 text-xs mb-4 leading-relaxed">{{ $aff['sub'] ?? '' }}</p>
    <a href="{{ $ctaUrl }}" target="_blank" rel="nofollow sponsored noopener"
       class="flex items-center justify-center gap-1.5 w-full px-4 py-2.5 bg-white text-gray-900 text-xs font-bold rounded-lg hover:bg-gray-100 transition">
        {{ $aff['cta_label'] ?? '無料で見積もりを見る' }}
        <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
    </a>
    @if(!empty($aff['provider']))
    <p class="text-white/50 text-[10px] font-bold mt-3">提供: {{ $aff['provider'] }}・PR</p>
    @endif
    @if(!empty($aff['imp_url']))
    <img src="{{ $aff['imp_url'] }}" width="1" height="1" alt="" style="border:0;position:absolute;left:-9999px;" aria-hidden="true">
    @endif
</aside>
@endif
