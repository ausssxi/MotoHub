{{--
    駐車場名で探す GET フォーム（駐車場エリア系ページ上部に設置）。
    /shops の x-shop-name-search と同型。グローバルヘッダーには置かない。

    props:
      q  入力欄の初期値（検索結果ページで現在値を保持）
--}}
@props(['q' => ''])

<form method="GET" action="{{ route('parking.name-search') }}"
      class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5 mb-4">
    <label class="block text-sm font-black text-gray-900 mb-2 flex items-center gap-2">
        <i data-lucide="search" class="w-4 h-4 text-green-600"></i>
        駐車場名で探す
    </label>
    <div class="flex gap-2">
        <input type="text" name="q" value="{{ $q }}" maxlength="60"
               placeholder="例: 町田森野第一駐車場、〇〇パーキング"
               class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:outline-none">
        <button type="submit"
                class="shrink-0 bg-green-600 hover:bg-green-700 text-white font-black text-sm px-5 py-2.5 rounded-lg transition active:scale-[0.99]">
            検索
        </button>
    </div>
</form>
