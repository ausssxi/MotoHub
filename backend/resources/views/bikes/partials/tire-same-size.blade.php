{{-- 「同じタイヤサイズの車種」ブロック（面②・タイヤサイズセクション直後・render時計算＝キャッシュbump不要）。
     TireSize::normalize で表記ゆれを吸収して前後一致（3件未満は前輪のみ一致にフォールバック）。
     自車種のサイズが正規化不能なら $same=null で非表示。リンクは canonical な bikes.model_detail（/bikes/{mfr}/{slug}）を route() で組み立て（301回避・URLハードコード禁止）。 --}}
@php
    $same = \App\Support\TireSize::sameSizeModels($model);
@endphp
@if($same !== null && ! empty($same['items']))
<div class="bg-white rounded-3xl shadow-sm p-5 sm:p-6 border border-gray-100">
    <h2 class="text-lg font-black text-gray-900 flex items-center gap-2 mb-1">
        <i data-lucide="circle-dashed" class="w-5 h-5 text-slate-500"></i>
        同じタイヤサイズの車種{{ $same['mode'] === 'front' ? '（前輪サイズが同じ）' : '' }}
    </h2>
    <p class="text-[11px] font-bold text-slate-500 mb-4">
        {{ $same['mode'] === 'both' ? '前後とも同じ純正装着サイズの車種' : '前輪の純正装着サイズが同じ車種' }}
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        @foreach($same['items'] as $item)
        <a href="{{ route('bikes.model_detail', ['mfrSlug' => $item['mfr_slug'], 'modelSlug' => $item['model_slug']]) }}"
           class="block rounded-xl border border-gray-100 p-3 hover:shadow-md transition-shadow">
            @if($item['manufacturer'] !== '')<p class="text-[10px] font-bold text-gray-400">{{ $item['manufacturer'] }}</p>@endif
            <p class="text-sm font-black text-gray-900">{{ $item['name'] }}</p>
        </a>
        @endforeach
    </div>

    {{-- そのサイズの一覧ページへの導線（ページ化条件=5件以上のときだけ）。$same!==null なら前サイズは正規化可能。 --}}
    @php
        $selfFront = \App\Support\TireSize::normalize($model->tire_size_front);
    @endphp
    @if($selfFront !== null && \App\Support\TireSize::isPageable($selfFront))
    <div class="border-t border-gray-100 pt-4 mt-4">
        <a href="{{ route('bikes.tire_size.show', ['sizeSlug' => \App\Support\TireSize::sizeSlug($selfFront)]) }}"
           class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:underline">
            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>{{ $selfFront }}を装着する車種をすべて見る
        </a>
    </div>
    @endif
</div>
@endif
