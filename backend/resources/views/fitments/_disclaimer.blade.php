{{-- 出典・免責（フッター固定partial）。出典・検証日はDB値をバインド。 --}}
@php
    $src = $fitments->firstWhere('source_1_name', '!=', null) ?? $fitments->first();
    $verifiedMax = $fitments->max('verified_at');
    $taskLabel = $taskConfig['label'] ?? $task;
    $subject = rawurlencode("【適合情報の誤り報告】{$bikeModel->name} {$taskLabel}");
@endphp
<div class="text-xs text-gray-500 leading-relaxed bg-gray-50 border border-gray-200 rounded-2xl p-5">
    <p>
        本ページの適合情報は
        @if($src && $src->source_1_name){{ $src->source_1_name }} 等の@endif公開適合表をもとに作成し、
        @if($verifiedMax){{ $verifiedMax->format('Y年n月') }}@endif時点で確認したものです。
        同一車種・型式でも年式や仕様（マイナーチェンジ・特別仕様等）により搭載{{ $taskLabel }}が異なる場合があります。
        <b class="text-gray-700">ご購入前に必ず、現在お使いの{{ $taskLabel }}の品番または車台番号（型式）をご確認ください。</b>
    </p>
    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1">
        @if($src && $src->source_1_url)
        <a href="{{ $src->source_1_url }}" target="_blank" class="text-blue-600 hover:underline">出典: {{ $src->source_1_name ?? $src->source_1_url }} →</a>
        @endif
        @if($src && $src->source_2_url)
        <a href="{{ $src->source_2_url }}" target="_blank" class="text-blue-600 hover:underline">{{ $src->source_2_name ?? $src->source_2_url }} →</a>
        @endif
        <a href="{{ route('pages.contact', ['subject' => $subject]) }}" class="text-gray-500 hover:text-gray-700 hover:underline">情報の誤りを報告</a>
    </div>
</div>
