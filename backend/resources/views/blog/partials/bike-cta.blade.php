{{--
    ブログ記事内 [bikes ...] ショートコードで描画する「関連車種の在庫CTA」ブロック。
    $bikes: array<array{name:string, url:string, count:int, minMan:float|null}>
    在庫・相場は車種ページと同じ market_stat 由来（PriceStatsService::getModelStats）。
    内部リンクなので nofollow は付けない（権威を商用ページへ流す目的）。
--}}
@if(!empty($bikes))
<aside class="not-prose my-8 rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 to-indigo-50 p-5 sm:p-6">
    <h2 class="mb-4 flex items-center gap-2 text-lg font-black text-gray-900">
        <i data-lucide="search" class="h-5 w-5 text-blue-600"></i>
        この記事で取り上げた車種の中古を探す
    </h2>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        @foreach($bikes as $bike)
        <a href="{{ $bike['url'] }}"
           class="group flex items-center justify-between rounded-xl border border-blue-100 bg-white px-4 py-3 transition-all hover:border-blue-300 hover:shadow-md">
            <span class="min-w-0">
                <span class="block truncate text-sm font-bold text-gray-900">{{ $bike['name'] }}の中古を探す</span>
                <span class="mt-0.5 block text-xs text-gray-500">
                    @if(($bike['count'] ?? 0) > 0 && !empty($bike['minMan']))
                        在庫{{ number_format($bike['count']) }}台・相場{{ $bike['minMan'] }}万円〜
                    @elseif(($bike['count'] ?? 0) > 0)
                        在庫{{ number_format($bike['count']) }}台
                    @else
                        最新の在庫・相場をチェック
                    @endif
                </span>
            </span>
            <i data-lucide="chevron-right" class="h-5 w-5 shrink-0 text-blue-400 transition-transform group-hover:translate-x-0.5"></i>
        </a>
        @endforeach
    </div>
</aside>
@endif
