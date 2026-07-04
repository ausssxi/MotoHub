{{--
    店名でショップを探すGETフォーム（ショップ系ページ上部に設置）。
    グローバルヘッダーには置かない（車両検索との混同回避）。

    props:
      pref   都道府県名（渡すと hidden で県内検索・ラベルにも表示）
      type   dealer / repair_only（渡すと hidden で店種固定）
      q      入力欄の初期値（検索結果ページで現在値を保持）
      accent blue（既定・販売店系）/ green（整備・修理系）
--}}
@props(['pref' => '', 'type' => '', 'q' => '', 'accent' => 'blue'])

@php
    $ring = $accent === 'green' ? 'focus:ring-green-500' : 'focus:ring-blue-500';
    $btn = $accent === 'green' ? 'bg-green-600 hover:bg-green-700' : 'bg-blue-600 hover:bg-blue-700';
@endphp

<form method="GET" action="{{ route('shops.search') }}"
      class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5 mb-6">
    <label class="block text-sm font-black text-gray-900 mb-2 flex items-center gap-2">
        <i data-lucide="search" class="w-4 h-4 {{ $accent === 'green' ? 'text-green-600' : 'text-blue-600' }}"></i>
        店名で探す@if($pref)<span class="text-xs font-bold text-gray-400">（{{ $pref }}内）</span>@endif
    </label>
    <div class="flex gap-2">
        <input type="text" name="q" value="{{ $q }}" maxlength="60"
               placeholder="例: YSP、モトパドック、レッドバロン"
               class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 {{ $ring }} focus:outline-none">
        @if($pref !== '')<input type="hidden" name="pref" value="{{ $pref }}">@endif
        @if($type !== '')<input type="hidden" name="type" value="{{ $type }}">@endif
        <button type="submit"
                class="shrink-0 {{ $btn }} text-white font-black text-sm px-5 py-2.5 rounded-lg transition active:scale-[0.99]">
            検索
        </button>
    </div>
</form>
