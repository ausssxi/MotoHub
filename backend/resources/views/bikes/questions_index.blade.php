<x-layout>
    <x-slot:title>{{ $model->name }} の質問・相談 一覧 | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $model->manufacturer?->name }} {{ $model->name }}についての質問・相談の一覧。オーナー・検討者のやり取りをチェック。あなたの疑問も投稿できます。</x-slot:metaDescription>
    @if($questions->currentPage() > 1)
    <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif

    <x-slot:navigation><x-navigation :showSearch="true" /></x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <nav class="flex flex-wrap text-xs font-bold text-gray-400 mb-6 gap-x-1.5" aria-label="Breadcrumb">
                <a href="{{ route('bikes.index') }}" class="hover:text-blue-600">ホーム</a><span>/</span>
                <a href="{{ $model->seo_url }}" class="hover:text-blue-600">{{ $model->name }}</a><span>/</span>
                <span class="text-gray-600">質問・相談</span>
            </nav>

            <h1 class="text-lg sm:text-xl font-black text-gray-900 mb-1">{{ $model->name }} の質問・相談</h1>
            <p class="text-xs text-gray-500 mb-5">全{{ $questions->total() }}件。気になる質問を開いて、回答を確認できます。</p>

            <ul class="space-y-2">
                @forelse($questions as $q)
                <a href="{{ route('bikes.model_question', ['mfrSlug' => $model->manufacturer->slug ?? $model->manufacturer_id, 'modelSlug' => $model->slug ?? $model->id, 'id' => $q->id]) }}"
                   class="flex items-center justify-between gap-3 bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-3 hover:border-blue-200 transition-colors">
                    <span class="text-sm font-bold text-gray-800 truncate">{{ $q->title }}</span>
                    <span class="shrink-0 text-xs font-black {{ $q->approved_answers_count > 0 ? 'text-gray-500' : 'text-orange-600 bg-orange-50 px-2 py-0.5 rounded-full' }}">
                        {{ $q->approved_answers_count > 0 ? '回答'.$q->approved_answers_count.'件' : '回答募集中' }}
                    </span>
                </a>
                @empty
                <li class="bg-white rounded-2xl border border-gray-100 px-4 py-10 text-center">
                    <p class="text-sm text-gray-400 font-bold">この車種への質問はまだありません。</p>
                </li>
                @endforelse
            </ul>

            <div class="mt-6">{{ $questions->links() }}</div>

            <div class="mt-6 text-center">
                <a href="{{ $model->seo_url }}#questions" class="inline-flex items-center gap-1.5 text-sm font-bold text-blue-600 hover:underline">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>{{ $model->name }} のページへ戻って質問する
                </a>
            </div>
        </div>
    </div>
</x-layout>
