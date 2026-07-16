{{--
    公開ガレージの社交コメント（会員限定）。props: $myBike, $comments
    過疎に見せない: 0件でも大きな「まだありません」を出さず、CTA＋軽い入力欄だけ。ガレージ本体が主役。
--}}
<div id="garage-comments" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-6 scroll-mt-20">
    <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
        <span class="bg-rose-100 text-rose-600 p-2 rounded-lg"><i data-lucide="message-circle" class="w-4 h-4"></i></span>
        コメント
        @if($comments->isNotEmpty())
        <span class="text-xs text-gray-400 font-bold">({{ $comments->count() }}件)</span>
        @endif
    </h2>

    @if(session('garage_comment_success'))
    <div class="mb-4 text-sm font-bold text-green-700 bg-green-50 border border-green-200 rounded-xl px-4 py-3">コメントしました。ありがとうございます！</div>
    @endif
    @if(session('report_success'))
    <div class="mb-4 text-xs font-bold text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">報告を受け付けました。確認します。ご協力ありがとうございます。</div>
    @endif

    {{-- コメント一覧 --}}
    @if($comments->isNotEmpty())
    <div class="space-y-3 mb-5">
        @foreach($comments as $c)
        <div class="bg-gray-50 rounded-xl p-4" x-data="{ report: false }">
            <div class="flex items-center gap-1.5 text-[11px] text-gray-400 mb-1">
                <x-user-avatar :user="$c->user" :name="$c->display_name" :size="6" />
                <span class="font-bold text-gray-600">{{ $c->display_name }}さん</span>
                <span>・{{ $c->created_at->diffForHumans() }}</span>
                <button type="button" @click="report = !report" class="ml-auto inline-flex items-center gap-0.5 font-bold text-gray-300 hover:text-red-500 transition-colors" aria-label="このコメントを報告する">
                    <i data-lucide="flag" class="w-2.5 h-2.5"></i>報告
                </button>
            </div>
            <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-line break-words">{{ $c->body }}</p>
            @include('bikes.partials._qa_report', ['type' => 'garage_comment', 'id' => $c->id])
        </div>
        @endforeach
    </div>
    @endif

    {{-- 投稿フォーム（会員限定・軽い一言前提） --}}
    @auth
        @error('body')<p class="text-xs font-bold text-red-500 mb-2">{{ $message }}</p>@enderror
        <form method="POST" action="{{ route('garage.comment', $myBike->id) }}" class="flex flex-col sm:flex-row gap-2">
            @csrf
            <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden" style="display:none">
            <input type="text" name="body" maxlength="500" value="{{ old('body') }}" required
                   placeholder="「かっこいいですね！」など、ひとことどうぞ"
                   class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-rose-400 focus:outline-none">
            <button type="submit" class="shrink-0 bg-rose-500 hover:bg-rose-600 text-white font-black text-sm px-6 py-2 rounded-lg transition">コメント</button>
        </form>
    @else
        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-sm font-bold text-rose-600 hover:text-rose-700">
            <i data-lucide="log-in" class="w-4 h-4"></i>ログインしてこのガレージにコメントする
        </a>
    @endauth
</div>
