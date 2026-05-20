{{-- CSVダウンロードボタン --}}
<div class="flex justify-end mb-4">
    <a href="{{ $downloadUrl }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-bold rounded-lg transition"
       onclick="if(typeof gtag==='function')gtag('event','download',{event_category:'csv',event_label:this.href})">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        CSVダウンロード
    </a>
</div>
