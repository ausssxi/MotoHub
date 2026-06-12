<x-layout>
    <x-slot:title>車種比較一覧 | スペック・中古相場を徹底比較 | MotoHub</x-slot:title>
    <x-slot:metaDescription>人気バイクのスペック・中古相場を1対1で徹底比較。排気量クラス・カテゴリ別に比較ページをまとめました。気になる2台を並べてチェックしましょう。</x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">

        {{-- ヘッダー --}}
        <div class="bg-white border-b border-gray-200 pt-8 pb-10 px-4">
            <div class="max-w-5xl mx-auto">
                {{-- パンくずリスト --}}
                <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><a href="{{ route('bikes.models') }}" class="hover:text-gray-600 transition-colors">車種一覧</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><span class="text-gray-800">車種比較一覧</span></li>
                    </ol>
                </nav>

                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-3 tracking-tight">
                    車種比較一覧<span class="text-lg text-gray-400 ml-2">スペック・中古相場</span>
                </h1>

                <p class="text-sm text-gray-500 leading-relaxed max-w-3xl">
                    人気バイクを1対1でスペック・中古価格相場を比較できるページの一覧です。
                    排気量クラス・カテゴリ別にまとめています。気になる2台を選んで詳しく比べてみましょう。
                </p>
            </div>
        </div>

        {{-- 比較ペア一覧（cc帯×カテゴリ別） --}}
        <div class="max-w-5xl mx-auto px-4 py-8">
            @forelse($groups as $group)
            <section class="mb-10">
                <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2 pb-2 border-b-2 border-gray-100">
                    <i data-lucide="git-compare" class="w-5 h-5 text-indigo-500"></i>
                    {{ $group['cc_label'] }}・{{ $group['category'] }}
                    <span class="text-xs font-bold text-gray-400 ml-1">{{ count($group['pairs']) }}件</span>
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($group['pairs'] as $pair)
                    <a href="{{ $pair['url'] }}" class="flex items-center justify-between bg-white hover:bg-indigo-50 rounded-xl p-4 border border-gray-100 hover:border-indigo-200 shadow-sm transition-colors group">
                        <span class="font-bold text-sm text-gray-700 group-hover:text-indigo-600 transition-colors">{{ $pair['label'] }}</span>
                        <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400 group-hover:text-indigo-500 shrink-0"></i>
                    </a>
                    @endforeach
                </div>
            </section>
            @empty
            <div class="bg-white rounded-2xl p-12 text-center border border-gray-100">
                <p class="text-sm font-bold text-gray-400">比較ページは準備中です。</p>
            </div>
            @endforelse
        </div>
    </div>
</x-layout>
