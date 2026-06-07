@props(['post'])

@php
    // 「修理記事か」はタグではなく config('diagnosis.cards') の article から導出する。
    // 診断ツールの答えカードが深掘り先として指す記事 ＝ 修理記事、という単一の真実に揃える。
    $repairSlugs = collect(config('diagnosis.cards'))
        ->pluck('article')
        ->filter()
        ->map(fn ($p) => ltrim(str_replace('/blog/', '', $p), '/'))
        ->unique()
        ->values()
        ->all();

    // 修理記事以外では何も描画しない（show.blade.php に無条件で差して安全）
    if (! in_array($post->slug, $repairSlugs, true)) {
        return;
    }
@endphp

<aside class="my-10 rounded-2xl border border-blue-200 bg-gradient-to-br from-blue-50 to-indigo-50 p-5 sm:p-6 shadow-sm">
    <div class="flex items-start gap-4">
        <div class="hidden sm:flex flex-shrink-0 w-12 h-12 rounded-xl bg-white border border-blue-200 items-center justify-center text-2xl">
            🔧
        </div>
        <div class="min-w-0">
            <h2 class="text-lg font-black text-gray-900 mb-1 flex items-center gap-2">
                <span class="sm:hidden text-xl">🔧</span>症状から原因を調べる
            </h2>
            <p class="text-sm text-gray-600 leading-relaxed">
                いくつかの質問に答えるだけ。自分で直せるか・店に出すべきかの目安がわかります。
            </p>

            <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px] font-bold text-blue-700">
                <span class="px-2.5 py-1 rounded-full bg-white/70 border border-blue-200">⚡ 約30秒</span>
                <span class="px-2.5 py-1 rounded-full bg-white/70 border border-blue-200">🆓 登録不要</span>
                <span class="px-2.5 py-1 rounded-full bg-white/70 border border-blue-200">¥0 無料</span>
            </div>

            <a href="{{ route('trouble.index') }}"
               class="mt-4 inline-flex items-center justify-center gap-2 w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-black text-sm px-5 py-3 rounded-xl transition active:scale-[0.99]">
                バイクの症状を無料診断する
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</aside>
