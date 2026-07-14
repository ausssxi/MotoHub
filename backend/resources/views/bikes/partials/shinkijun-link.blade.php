{{--
    新基準原付ハブ（/shinkijun-gentsuki）への相互リンク・バナー。
    対象Liteモデル → 「このモデルは新基準原付です」／ベース110cc・旧50cc → 「新基準原付として乗れるモデルは？」。
    判定は config('shinkijun.*') の正確名リストで行い、キャッシュ済みデータには触れない（bump不要）。
    $model を受け取る。
--}}
@php
    $skTargets = config('shinkijun.target_models', []);
    $skBaseNames = array_merge(config('shinkijun.base_models', []), config('shinkijun.old50_models', []));
    $skIsTarget = in_array($model->name, $skTargets, true);
    $skIsBase = ! $skIsTarget && in_array($model->name, $skBaseNames, true);
@endphp
@if($skIsTarget || $skIsBase)
    <a href="{{ route('shinkijun_gentsuki') }}"
       class="group flex items-center gap-4 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-2xl p-4 sm:p-5 hover:border-blue-400 hover:shadow-md transition-all">
        <span class="shrink-0 w-11 h-11 rounded-xl bg-blue-600 text-white flex items-center justify-center">
            <i data-lucide="badge-check" class="w-6 h-6"></i>
        </span>
        <span class="flex-1 min-w-0">
            <span class="block text-sm sm:text-base font-black text-gray-900">
                @if($skIsTarget)このモデルは新基準原付です@else新基準原付として乗れるモデルは？@endif
            </span>
            <span class="block text-xs text-gray-500 mt-0.5">新基準原付の対象モデル一覧・新古車の相場・原付二種との違いを見る</span>
        </span>
        <i data-lucide="chevron-right" class="shrink-0 w-5 h-5 text-blue-400 group-hover:translate-x-0.5 transition-transform"></i>
    </a>
@endif
