@php
    // ★ "slot" は Blade 予約変数（$slot=スロット内容）と衝突するため props にせず属性から読む。
    //   公開APIは <x-ads.unit slot="blog_mid" /> のまま。
    $adSlotKey = (string) $attributes->get('slot', '');
    $adsClient = (string) config('adsense.client', '');
    $adsSlotId = (string) config('adsense.slots.'.$adSlotKey, '');
    // enabled が false / client 未設定 / スロットID空 のときは何も出さない（空の <ins> を置かない）。
    $adsShow = (bool) config('adsense.enabled') && $adsClient !== '' && $adsSlotId !== '';
@endphp

@if($adsShow)
{{-- CLS対策の min-height と、未フィル時の折りたたみは layout head の .ad-unit CSS 側で担保。 --}}
<div class="ad-unit my-8">
    {{-- 広告とコンテンツを見分けられるようにするラベル（AdSenseポリシー要件）。 --}}
    <p class="text-[10px] text-gray-400 mb-1 text-center tracking-widest">広告</p>
    <ins class="adsbygoogle"
         style="display:block"
         data-ad-client="{{ $adsClient }}"
         data-ad-slot="{{ $adsSlotId }}"
         data-ad-format="auto"
         data-full-width-responsive="true"></ins>
    <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
</div>
@endif
