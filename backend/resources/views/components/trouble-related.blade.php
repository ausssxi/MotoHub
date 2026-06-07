@props(['post'])

@php
    $repairSlugs = diagnosis_repair_slugs();

    // 修理記事以外では何も描画しない（show.blade.php に無条件で差して安全）
    if (! in_array($post->slug, $repairSlugs, true)) {
        return;
    }

    // 現在の記事を除いた、他の修理記事（公開済み・実在するもののみ）を最大6本
    $otherSlugs = array_values(array_diff($repairSlugs, [$post->slug]));

    $siblings = empty($otherSlugs)
        ? collect()
        : \App\Models\BlogPost::published()
            ->whereIn('slug', $otherSlugs)
            ->orderByDesc('published_at')
            ->limit(6)
            ->get(['slug', 'title']);

    // 公開済みの兄弟記事が1本も無ければ描画しない
    if ($siblings->isEmpty()) {
        return;
    }
@endphp

<section class="my-8 rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-sm">
    <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
        <span class="text-lg">🛠️</span>関連：ほかの症状の対処記事
    </h2>
    <ul class="divide-y divide-gray-100">
        @foreach($siblings as $sib)
            <li>
                <a href="{{ route('blog.show', $sib->slug) }}"
                   class="group flex items-center justify-between gap-3 py-3 hover:text-blue-700 transition">
                    <span class="font-bold text-sm text-gray-800 group-hover:text-blue-700 line-clamp-2 leading-snug">{{ $sib->title }}</span>
                    <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-500 flex-shrink-0 transition" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                </a>
            </li>
        @endforeach
    </ul>
</section>
