{{--
    店舗の対応サービス（service_tags）をチップ表示する共通コンポーネント。
    整備系（認証工場/修理・点検整備/車検受付）は強調色、その他は中立色。

    使い方: <x-shop-service-tags :tags="$shop->service_tags" />
--}}
@props(['tags' => []])

@php
    $tags = is_array($tags) ? array_values(array_filter(array_map('trim', $tags))) : [];
    $maintenance = ['認証工場', '修理・点検整備', '車検受付'];
@endphp

@if(count($tags) > 0)
<div class="flex flex-wrap gap-1.5">
    @foreach($tags as $tag)
        @php $isMaint = in_array($tag, $maintenance, true); @endphp
        <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full border
            {{ $isMaint ? 'bg-green-50 text-green-700 border-green-100' : 'bg-gray-50 text-gray-600 border-gray-100' }}">
            @if($isMaint)<i data-lucide="wrench" class="w-2.5 h-2.5"></i>@endif
            {{ $tag }}
        </span>
    @endforeach
</div>
@endif
