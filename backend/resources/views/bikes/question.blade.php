<x-layout>
    <x-slot:title>{{ $question->title }}｜{{ $model->name }} の質問・相談 | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $model->manufacturer?->name }} {{ $model->name }}についての質問「{{ \Illuminate\Support\Str::limit($question->title, 80) }}」。オーナー・検討者の回答をチェック。あなたの疑問も投稿できます。</x-slot:metaDescription>
    <x-slot:canonical>{{ route('bikes.model_question', ['mfrSlug' => $model->manufacturer->slug ?? $model->manufacturer_id, 'modelSlug' => $model->slug ?? $model->id, 'id' => $question->id]) }}</x-slot:canonical>

    <x-slot:navigation><x-navigation :showSearch="true" /></x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- パンくず --}}
            <nav class="flex flex-wrap text-xs font-bold text-gray-400 mb-6 gap-x-1.5" aria-label="Breadcrumb">
                <a href="{{ route('bikes.index') }}" class="hover:text-blue-600">ホーム</a><span>/</span>
                <a href="{{ $model->seo_url }}" class="hover:text-blue-600">{{ $model->name }}</a><span>/</span>
                <span class="text-gray-600">質問・相談</span>
            </nav>

            @if(session('qa_success') === 'answer')
            <div class="mb-4 text-sm font-bold text-green-700 bg-green-50 border border-green-200 rounded-xl px-4 py-3">回答を投稿しました。ありがとうございます！</div>
            @endif
            @if(session('report_success'))
            <div class="mb-4 text-xs font-bold text-gray-600 bg-white border border-gray-200 rounded-lg px-3 py-2">報告を受け付けました。確認します。ご協力ありがとうございます。</div>
            @endif

            {{-- 質問本体 --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6" x-data="{ report: false }">
                <div class="flex items-center gap-2 mb-2 text-[11px] font-bold text-gray-400">
                    <span class="inline-flex items-center gap-1 text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full"><i data-lucide="help-circle" class="w-3 h-3"></i>質問</span>
                    <a href="{{ $model->seo_url }}" class="text-gray-500 hover:text-blue-600">{{ $model->manufacturer?->name }} {{ $model->name }}</a>
                </div>
                <h1 class="text-lg sm:text-xl font-black text-gray-900 leading-snug">{{ $question->title }}</h1>
                @if($question->body)
                <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line break-words mt-3">{{ $question->body }}</p>
                @endif
                <div class="flex items-center gap-1.5 mt-3 text-[11px] text-gray-400">
                    <x-user-avatar :user="$question->user" :name="$question->display_name" :size="6" />
                    <span class="font-bold text-gray-500">{{ $question->display_name }}さん</span>
                    <span>・{{ $question->created_at->diffForHumans() }}</span>
                    <button type="button" @click="report = !report" class="ml-auto inline-flex items-center gap-0.5 font-bold text-gray-300 hover:text-red-500 transition-colors" aria-label="この質問を報告する">
                        <i data-lucide="flag" class="w-2.5 h-2.5"></i>報告
                    </button>
                </div>
                @include('bikes.partials._qa_report', ['type' => 'model_question', 'id' => $question->id])
            </div>

            {{-- 回答一覧 --}}
            <h2 id="answers" class="text-base font-black text-gray-800 mb-3 scroll-mt-20">回答（{{ $answers->count() }}件）</h2>
            <ul class="space-y-3 mb-6">
                @forelse($answers as $answer)
                <li class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4" x-data="{ report: false, helped: false, count: {{ $answer->helpful_count }} }"
                    x-init="helped = !!localStorage.getItem('qa_helpful_{{ $answer->id }}')">
                    <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line break-words">{{ $answer->body }}</p>
                    <div class="flex items-center gap-2 mt-2 text-[11px] text-gray-400">
                        <x-user-avatar :user="$answer->user" :name="$answer->display_name" :size="6" />
                        <span class="font-bold text-gray-500">{{ $answer->display_name }}さん</span>
                        <span>・{{ $answer->created_at->diffForHumans() }}</span>
                        {{-- 参考になった（回答者へのモチベーション・投稿の代替ではない） --}}
                        <button type="button"
                                @click="if(!helped){ helped=true; localStorage.setItem('qa_helpful_{{ $answer->id }}','1'); fetch('{{ route('bikes.model_answer.helpful', $answer->id) }}',{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}}).then(r=>r.json()).then(d=>count=d.helpful_count).catch(()=>{}); }"
                                class="inline-flex items-center gap-1 font-bold transition-colors" :class="helped ? 'text-blue-600' : 'text-gray-400 hover:text-blue-600'">
                            <i data-lucide="thumbs-up" class="w-3 h-3"></i>参考になった<span x-show="count>0" x-text="count"></span>
                        </button>
                        <button type="button" @click="report = !report" class="ml-auto inline-flex items-center gap-0.5 font-bold text-gray-300 hover:text-red-500 transition-colors" aria-label="この回答を報告する">
                            <i data-lucide="flag" class="w-2.5 h-2.5"></i>報告
                        </button>
                    </div>
                    @include('bikes.partials._qa_report', ['type' => 'model_answer', 'id' => $answer->id])
                </li>
                @empty
                <li class="bg-white rounded-2xl border border-gray-100 px-4 py-8 text-center">
                    <p class="text-sm text-gray-400 font-bold">まだ回答がありません。</p>
                    <p class="text-xs text-gray-400 mt-1">この{{ $model->name }}にお乗りの方、ぜひ答えてあげてください。</p>
                </li>
                @endforelse
            </ul>

            {{-- 投稿は統合スレッド「クチコミ・相談」へ一本化（この質問詳細は閲覧/SEO用として残置） --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 text-center">
                <p class="text-sm text-gray-600 font-bold mb-3">この{{ $model->name }}への質問・回答は「クチコミ・相談」に移動しました。</p>
                <a href="{{ $model->seo_url }}#threads" class="inline-flex items-center gap-1 text-xs font-bold bg-blue-600 text-white px-5 py-2 rounded-full hover:bg-blue-700 transition-colors">
                    <i data-lucide="messages-square" class="w-3.5 h-3.5"></i>クチコミ・相談を見る／投稿する
                </a>
            </div>
        </div>
    </div>
</x-layout>
