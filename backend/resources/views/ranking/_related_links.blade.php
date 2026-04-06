{{-- 関連リンク --}}
<div class="mt-8 bg-gray-50 rounded-2xl p-6">
    <h3 class="text-sm font-black text-gray-500 mb-3">関連リンク</h3>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
        <a href="{{ route('bikes.index') }}" class="flex items-center gap-2 p-3 bg-white rounded-xl hover:shadow-sm transition text-sm font-bold text-gray-700 hover:text-blue-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            中古バイクを探す
        </a>
        <a href="{{ route('bikes.trends') }}" class="flex items-center gap-2 p-3 bg-white rounded-xl hover:shadow-sm transition text-sm font-bold text-gray-700 hover:text-blue-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            相場・トレンド
        </a>
        <a href="{{ route('news.index') }}" class="flex items-center gap-2 p-3 bg-white rounded-xl hover:shadow-sm transition text-sm font-bold text-gray-700 hover:text-blue-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
            バイクニュース
        </a>
    </div>
</div>
