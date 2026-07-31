@props(['prefecture'])

{{--
    該当県に「二輪免許が取れる指定自動車教習所」ページがあればカード1枚を出す。
    $prefecture は正式名「神奈川県」/ 短縮形「神奈川」のどちらも来る想定。
    公開県一覧は LicenseSchoolController::show() と同一キー・同一形の配列。
    まず name 完全一致 → 見つからなければ name の前方一致。どちらも無ければ何も出さない。
--}}
@php
    $prefectures = \Illuminate\Support\Facades\Cache::remember('license.schools.pref_list', 3600, function () {
        return \App\Models\DrivingSchool::query()
            ->published()
            ->nirin()
            ->orderBy('prefecture_slug')
            ->get(['prefecture', 'prefecture_slug'])
            ->groupBy('prefecture_slug')
            ->map(fn ($g) => [
                'slug' => $g->first()->prefecture_slug,
                'name' => $g->first()->prefecture,
                'count' => $g->count(),
            ])
            ->values()
            ->all();
    });

    $match = collect($prefectures)->firstWhere('name', $prefecture)
        ?? collect($prefectures)->first(fn ($p) => str_starts_with($p['name'], $prefecture));
@endphp

@if($match)
    <a href="{{ route('license.schools.show', $match['slug']) }}"
       class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-blue-400 transition">
        <div class="text-sm font-black text-slate-900">{{ $match['name'] }}で二輪免許が取れる指定自動車教習所（{{ $match['count'] }}校）</div>
    </a>
@endif
