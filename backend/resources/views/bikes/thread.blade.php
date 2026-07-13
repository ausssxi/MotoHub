<x-layout>
    <x-slot:title>{{ $thread->title }}｜{{ $model->name }} のクチコミ・相談 | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $model->manufacturer?->name }} {{ $model->name }}のクチコミ・相談「{{ \Illuminate\Support\Str::limit($thread->title, 80) }}」。MotoHubの回答とオーナーの声をチェック。あなたの疑問も投稿できます。</x-slot:metaDescription>
    <x-slot:canonical>{{ route('bikes.thread', ['mfrSlug' => $model->manufacturer->slug ?? $model->manufacturer_id, 'modelSlug' => $model->slug ?? $model->id, 'id' => $thread->id]) }}</x-slot:canonical>

    <x-slot:navigation><x-navigation :showSearch="true" /></x-slot:navigation>

    @php
        $threadUrl = route('bikes.thread', ['mfrSlug' => $model->manufacturer->slug ?? $model->manufacturer_id, 'modelSlug' => $model->slug ?? $model->id, 'id' => $thread->id]);
        $breadcrumb = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'トップ', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => '車種一覧', 'item' => route('bikes.models')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $model->name, 'item' => url($model->seo_url)],
                ['@type' => 'ListItem', 'position' => 4, 'name' => \Illuminate\Support\Str::limit($thread->title, 60)],
            ],
        ];
        // FAQPage: 質問スレ＋生成済みのMotoHub公式回答があれば出力（買い手の検索語を拾う）
        $official = $replies->firstWhere('is_official', true);
        $faqAnswer = ($thread->type === 'question' && $official && $official->answer_generated_at) ? $official->body : null;
    @endphp
    <script type="application/ld+json">{!! json_encode($breadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @if($faqAnswer)
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [[
            '@type' => 'Question',
            'name' => $thread->title,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faqAnswer],
        ]],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- パンくず --}}
            <nav class="flex flex-wrap text-xs font-bold text-gray-400 mb-6 gap-x-1.5" aria-label="Breadcrumb">
                <a href="{{ route('bikes.index') }}" class="hover:text-blue-600">ホーム</a><span>/</span>
                <a href="{{ $model->seo_url }}" class="hover:text-blue-600">{{ $model->name }}</a><span>/</span>
                <span class="text-gray-600">クチコミ・相談</span>
            </nav>

            @if(session('ugc_success') === 'reply')
            <div class="mb-4 text-sm font-bold text-green-700 bg-green-50 border border-green-200 rounded-xl px-4 py-3">投稿しました。ありがとうございます！</div>
            @endif
            @if(session('report_success'))
            <div class="mb-4 text-xs font-bold text-gray-600 bg-white border border-gray-200 rounded-lg px-3 py-2">報告を受け付けました。確認します。ご協力ありがとうございます。</div>
            @endif

            {{-- スレッド本体（OP） --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6" x-data="{ report: false }">
                <div class="flex items-center gap-2 mb-2 text-[11px] font-bold text-gray-400">
                    <span class="inline-flex items-center gap-1 text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full"><i data-lucide="help-circle" class="w-3 h-3"></i>{{ $thread->type === 'question' ? '質問' : '相談' }}</span>
                    <a href="{{ $model->seo_url }}" class="text-gray-500 hover:text-blue-600">{{ $model->manufacturer?->name }} {{ $model->name }}</a>
                </div>
                <h1 class="text-lg sm:text-xl font-black text-gray-900 leading-snug">{{ $thread->title }}</h1>
                @if($thread->body)
                <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line break-words mt-3">{{ $thread->body }}</p>
                @endif
                <div class="flex items-center gap-1.5 mt-3 text-[11px] text-gray-400">
                    <span class="font-bold text-gray-500">{{ $thread->display_name }}さん</span>
                    <span>・{{ $thread->created_at->diffForHumans() }}</span>
                    <button type="button" @click="report = !report" class="ml-auto inline-flex items-center gap-0.5 font-bold text-gray-300 hover:text-red-500 transition-colors" aria-label="このスレッドを報告する">
                        <i data-lucide="flag" class="w-2.5 h-2.5"></i>報告
                    </button>
                </div>
                @include('bikes.partials._qa_report', ['type' => 'discussion_thread', 'id' => $thread->id])
            </div>

            {{-- 返信一覧（MotoHub必答が先頭） --}}
            <h2 id="replies" class="text-base font-black text-gray-800 mb-3 scroll-mt-20">返信（{{ $replies->count() }}件）</h2>
            <ul class="space-y-3 mb-6">
                @foreach($replies as $reply)
                <li class="bg-white rounded-2xl border {{ $reply->is_official ? 'border-blue-200' : 'border-gray-100' }} shadow-sm p-4" x-data="{ report: false, helped: false, count: {{ $reply->helpful_count }} }"
                    x-init="helped = !!localStorage.getItem('thread_helpful_{{ $reply->id }}')">
                    @if($reply->is_official)
                    <div class="flex items-center gap-1.5 mb-2">
                        <span class="inline-flex items-center gap-1 text-[11px] font-black text-white bg-blue-600 px-2 py-0.5 rounded-full"><i data-lucide="badge-check" class="w-3 h-3"></i>MotoHub</span>
                        <span class="text-[10px] font-bold text-gray-400">AIによる回答（データに基づく参考情報）</span>
                    </div>
                    @endif
                    @if($reply->isPending())
                    <p class="text-sm text-gray-400 font-bold">{{ $reply->body }}</p>
                    @else
                    <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line break-words">{{ $reply->body }}</p>
                    @endif
                    <div class="flex items-center gap-2 mt-2 text-[11px] text-gray-400">
                        @unless($reply->is_official)
                        <span class="font-bold text-gray-500">{{ $reply->display_name }}さん</span>
                        <span>・{{ $reply->created_at->diffForHumans() }}</span>
                        @endunless
                        <button type="button"
                                @click="if(!helped){ helped=true; localStorage.setItem('thread_helpful_{{ $reply->id }}','1'); fetch('{{ route('bikes.thread.reply.vote', $reply->id) }}',{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}}).then(r=>r.json()).then(d=>count=d.helpful_count).catch(()=>{}); }"
                                class="inline-flex items-center gap-1 font-bold transition-colors" :class="helped ? 'text-blue-600' : 'text-gray-400 hover:text-blue-600'">
                            <i data-lucide="thumbs-up" class="w-3 h-3"></i>参考になった<span x-show="count>0" x-text="count"></span>
                        </button>
                        @unless($reply->is_official)
                        <button type="button" @click="report = !report" class="ml-auto inline-flex items-center gap-0.5 font-bold text-gray-300 hover:text-red-500 transition-colors" aria-label="この返信を報告する">
                            <i data-lucide="flag" class="w-2.5 h-2.5"></i>報告
                        </button>
                        @endunless
                    </div>
                    @unless($reply->is_official)
                    @include('bikes.partials._qa_report', ['type' => 'discussion_reply', 'id' => $reply->id])
                    @endunless
                </li>
                @endforeach
            </ul>

            {{-- 返信フォーム（開放フラグで制御） --}}
            @if(config('ugc.replies_open', true))
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h3 class="text-sm font-black text-gray-900 mb-3">このスレッドに返信する</h3>
                @error('body')<p class="text-xs font-bold text-red-500 mb-2">{{ $message }}</p>@enderror
                <form method="POST" action="{{ route('bikes.thread.reply', $thread->id) }}" class="space-y-3">
                    @csrf
                    <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden" style="display:none">
                    <textarea name="body" rows="3" maxlength="2000" required placeholder="返信を書く（実際に乗っている方の声が特に役立ちます）"
                              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">{{ old('body') }}</textarea>
                    <div class="flex gap-2">
                        @guest
                        <input type="text" name="nickname" maxlength="50" value="{{ old('nickname') }}" placeholder="お名前（任意）" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm">
                        @endguest
                        <button type="submit" class="shrink-0 bg-blue-600 hover:bg-blue-700 text-white font-black text-sm px-6 py-2 rounded-lg transition ml-auto">返信する</button>
                    </div>
                </form>
            </div>
            @endif
        </div>
    </div>
</x-layout>
