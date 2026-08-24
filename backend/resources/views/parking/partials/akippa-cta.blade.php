{{-- akippa（予約できる駐車場）送客CTA。自前テキスト・env制御・保険/盗難と同作法。
     リンクは App\Support\AkippaLink::ctaFor() が解決：
       ・akippa物件（management_company==='akippa株式会社'）でのみ表示（非akippaは非表示）
       ・A8MAT設定＋notes内にakippa.com URL → その駐車場へのディープリンク（アフィリ・affiliate=true）
       ・A8MAT未設定＋URLあり → その駐車場のakippaページへ素のリンク（非アフィリ・affiliate=false）
       ・URL抽出不可なら非表示（akippaトップへの送客はしない）
     ★affiliate=true のときだけ PR・広告表記／rel=sponsored／計測imgを出す。素リンク時は付けない。
     ★akippaは四輪中心のため二輪可否は断定しない（「バイク可の駐車場も探せる」程度に留める）。 --}}
@php $akippaCta = \App\Support\AkippaLink::ctaFor($parking->management_company ?? null, $parking->notes ?? null, $parking->description ?? null); @endphp
@if($akippaCta)
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
    @if($akippaCta['affiliate'])
    <p class="text-[10px] font-black tracking-widest text-gray-500 uppercase mb-1">PR・広告</p>
    @endif
    <div class="flex items-start gap-3">
        <span class="bg-green-50 text-green-600 p-2 rounded-lg shrink-0"><i data-lucide="calendar-check" class="w-5 h-5"></i></span>
        <div class="min-w-0">
            <p class="text-sm font-black text-gray-900">満車が心配なら、予約できる駐車場</p>
            <p class="text-xs text-gray-500 leading-relaxed mt-0.5">事前予約でスペースを確保。バイク可の駐車場も探せます（akippa）。</p>
            <a href="{{ $akippaCta['url'] }}" target="_blank" rel="{{ $akippaCta['affiliate'] ? 'nofollow sponsored noopener' : 'nofollow noopener' }}"
               class="mt-3 inline-flex items-center gap-1.5 bg-gray-900 hover:bg-black text-white text-xs font-bold px-4 py-2.5 rounded-lg transition-colors">
                <i data-lucide="calendar-check" class="w-4 h-4"></i>{{ $akippaCta['deeplink'] ? 'この駐車場を予約' : '予約できる駐車場を探す' }}
            </a>
        </div>
    </div>
    @php $akippaImp = $akippaCta['affiliate'] ? config('parking.affiliate.akippa.imp_url') : null; @endphp
    @if(!empty($akippaImp))
    <img src="{{ $akippaImp }}" width="1" height="1" alt="" style="border:0;position:absolute;left:-9999px;" aria-hidden="true">
    @endif
</div>
@endif
