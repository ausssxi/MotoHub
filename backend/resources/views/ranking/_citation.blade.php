{{-- データ引用セクション --}}
<div class="bg-gray-50 rounded-xl p-5 mt-6 mb-6" x-data="{ copied: false }">
    <h3 class="text-xs font-black text-gray-500 uppercase tracking-widest mb-3">データ引用について</h3>
    <p class="text-xs text-gray-600 leading-relaxed mb-3">
        本ページのデータは、出典を明記の上で自由に引用いただけます。記事・レポート・SNS等でご利用の際は、以下の形式でリンク付きの出典表記をお願いいたします。
    </p>
    <div class="bg-white border border-gray-200 rounded-lg p-3 mb-3">
        <code id="citation-text" class="text-xs text-gray-700 break-all leading-relaxed">出典：MotoHub中古バイク市場データ（{{ url()->current() }}）</code>
    </div>
    <button
        type="button"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-600 text-xs font-bold rounded-lg transition"
        @click="navigator.clipboard.writeText(document.getElementById('citation-text').textContent).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
        </svg>
        <span x-text="copied ? 'コピーしました！' : '出典テキストをコピー'"></span>
    </button>
</div>
