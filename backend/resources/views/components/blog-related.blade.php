@props(['post'])

@php
    // トピッククラスター定義（config/blog_clusters.php＝コードが正本）から、
    // この記事が属するクラスタの pillar・兄弟記事を集約して関連記事として表示する。
    $clusters = config('blog_clusters.clusters', []);
    $slug = $post->slug;

    $pillarSlugs = []; // この記事が属するクラスタの親記事（自分が親なら除外）
    $memberSlugs = []; // 同クラスタの兄弟記事
    foreach ($clusters as $cluster) {
        $all = array_merge([$cluster['pillar']], $cluster['members'] ?? []);
        if (! in_array($slug, $all, true)) {
            continue; // この記事はこのクラスタに属さない
        }
        if (($cluster['pillar'] ?? null) !== $slug) {
            $pillarSlugs[] = $cluster['pillar'];
        }
        foreach (($cluster['members'] ?? []) as $m) {
            if ($m !== $slug) {
                $memberSlugs[] = $m;
            }
        }
    }

    $pillarSlugs = array_values(array_unique($pillarSlugs));
    $memberSlugs = array_values(array_diff(array_unique($memberSlugs), $pillarSlugs));
    // 親記事を先頭に（ハブへ評価を集約）、続いて兄弟記事。自分自身は除外。
    $orderedSlugs = array_values(array_diff(array_merge($pillarSlugs, $memberSlugs), [$slug]));

    if (empty($orderedSlugs)) {
        return; // クラスタ未所属（ハウツー/お知らせ等）では何も描画しない
    }

    $found = \App\Models\BlogPost::published()
        ->whereIn('slug', $orderedSlugs)
        ->get(['slug', 'title'])
        ->keyBy('slug');

    // config の並び順を維持しつつ、公開済み・実在のものだけを最大8本
    $related = collect($orderedSlugs)
        ->map(fn ($s) => $found->get($s))
        ->filter()
        ->take(8)
        ->values();

    if ($related->isEmpty()) {
        return;
    }

    $pillarSet = array_flip($pillarSlugs);
@endphp

<section class="my-8 rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-sm">
    <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
        <span class="text-lg">🔗</span>関連記事
    </h2>
    <ul class="divide-y divide-gray-100">
        @foreach($related as $rel)
            <li>
                <a href="{{ route('blog.show', $rel->slug) }}"
                   class="group flex items-center justify-between gap-3 py-3 hover:text-blue-700 transition">
                    <span class="flex items-center gap-2 min-w-0">
                        @if(isset($pillarSet[$rel->slug]))
                            <span class="flex-shrink-0 text-[10px] font-black text-blue-700 bg-blue-50 border border-blue-100 rounded px-1.5 py-0.5">まとめ</span>
                        @endif
                        <span class="font-bold text-sm text-gray-800 group-hover:text-blue-700 line-clamp-2 leading-snug">{{ $rel->title }}</span>
                    </span>
                    <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-500 flex-shrink-0 transition" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                </a>
            </li>
        @endforeach
    </ul>
</section>
