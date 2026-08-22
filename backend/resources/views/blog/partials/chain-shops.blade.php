{{--
    ブログ記事内 [chain-shops slug="..."] ショートコードで描画するチェーン店舗の都道府県別リンク一覧。
    $groups: array<array{label:string, count:int, shops:array<array{id:int, name:string}>}>（北海道→沖縄順・「その他」は最後）
    $total:  int（全店舗数）
    記事本文は {!! $html !!} で生HTML出力されるため、店舗名・都道府県名は必ず e() でエスケープする。
    リンク先は route('shops.show', ['id' => (int)...]) を使い、URLはハードコードしない。内部リンクなので nofollow は付けない。
--}}
<aside class="not-prose my-8 rounded-2xl border border-gray-100 bg-gray-50 p-5 sm:p-6">
    <p class="mb-4 flex items-center gap-2 text-base font-black text-gray-900">
        <i data-lucide="store" class="h-5 w-5 text-blue-600"></i>
        全{{ number_format($total) }}店舗
    </p>
    <div class="space-y-5">
        @foreach($groups as $group)
        <div>
            <h3 class="mb-2 text-sm font-black text-gray-700">{!! e($group['label']) !!}（{{ number_format($group['count']) }}店舗）</h3>
            {{-- ul/li は .blog-content ul li{list-style:disc} で行頭記号が付くため使わない。div＋直下 a を flex 折り返し＋gap のみで配置（区切り文字なし）。 --}}
            <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                @foreach($group['shops'] as $shop)
                <a href="{{ route('shops.show', ['id' => (int) $shop['id']]) }}" class="text-sm text-blue-600 hover:underline">{!! e($shop['name']) !!}</a>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
</aside>
