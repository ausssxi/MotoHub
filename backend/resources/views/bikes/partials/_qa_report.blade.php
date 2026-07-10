{{-- Q&A の通報フォーム（polymorphic reports・親の x-data({report}) スコープ内で include）。
     props: type（model_question / model_answer）・id --}}
<form x-show="report" x-cloak method="POST" action="{{ route('reports.store') }}" class="mt-2 pt-2 border-t border-gray-100 space-y-1.5">
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">
    <input type="hidden" name="id" value="{{ $id }}">
    <p class="text-[10px] font-bold text-gray-500">報告の理由（任意）</p>
    <div class="flex flex-wrap gap-1.5">
        @foreach(\App\Models\Report::REASONS as $key => $label)
        <label class="inline-flex items-center gap-1 cursor-pointer text-[10px] text-gray-600 bg-gray-50 border border-gray-200 rounded-full px-2 py-0.5">
            <input type="radio" name="reason" value="{{ $key }}" class="accent-red-500 w-2.5 h-2.5">{{ $label }}
        </label>
        @endforeach
    </div>
    <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden" style="display:none">
    <button type="submit" class="text-[10px] font-black text-white bg-red-500 hover:bg-red-600 rounded-lg px-3 py-1 transition">報告する</button>
</form>
