{{--
    取扱車種 → 公開済み適合表への内部リンク。
    カニバリ回避のためリンクのみ（品番・規格・油量・費用・型式は一切出さない）。
    $fitmentModelLinks が空ならブロックごと非表示（見出しも出さない）。
--}}
@if(!empty($fitmentModelLinks))
@php $taskLabels = ['battery' => 'バッテリー', 'plug' => 'プラグ', 'oil' => 'オイル']; @endphp
<section class="mt-8 bg-white rounded-3xl border border-gray-100 shadow-sm p-5 sm:p-6">
    <h2 class="text-base font-black text-gray-800 flex items-center gap-2">
        <i data-lucide="clipboard-list" class="w-5 h-5 text-blue-600"></i>
        取扱車種の適合パーツ早見表
    </h2>
    <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">
        バッテリー・プラグ・オイルの適合規格は各適合表ページで確認できます。
    </p>

    <ul class="mt-4 divide-y divide-gray-100">
        @foreach($fitmentModelLinks as $row)
        <li class="py-2.5 flex flex-wrap items-center gap-x-2 gap-y-1.5">
            <span class="text-sm font-bold text-gray-700 mr-1">{{ $row['manufacturer'] }} {{ $row['name'] }}</span>
            <span class="flex flex-wrap gap-1.5">
                @foreach($row['tasks'] as $task)
                <a href="{{ route('fitments.show', ['bikeModel' => $row['slug'], 'task' => $task]) }}"
                   class="inline-flex items-center rounded-full bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-black px-3 py-1 transition-colors">
                    {{ $taskLabels[$task] ?? $task }}
                </a>
                @endforeach
            </span>
        </li>
        @endforeach
    </ul>
</section>
@endif
