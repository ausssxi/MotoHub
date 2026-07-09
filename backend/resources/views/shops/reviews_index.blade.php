<x-layout>
    <x-slot:title>みんなの口コミ（新着）| バイクショップの評判・体験談 | MotoHub</x-slot:title>
    <x-slot:metaDescription>全国のバイクショップに寄せられた利用者の口コミを新着順に掲載。「他店購入車OK」「持ち込みOK」など、実際に対応してもらえた体験談を集めました。あなたの体験も投稿できます。</x-slot:metaDescription>

    {{-- 2頁目以降は薄い重複回避のため noindex（1頁目のみ index） --}}
    @if($comments->currentPage() > 1)
    <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">
        <div class="bg-white border-b border-gray-200 pt-8 pb-8 px-4">
            <div class="max-w-3xl mx-auto">
                <nav class="flex text-xs font-bold text-gray-400 mb-4" aria-label="Breadcrumb">
                    <a href="{{ route('bikes.index') }}" class="hover:text-blue-600">ホーム</a>
                    <span class="mx-1.5">/</span>
                    <span class="text-gray-600">みんなの口コミ</span>
                </nav>
                <h1 class="text-xl md:text-2xl font-black text-gray-800 flex items-center gap-2">
                    <i data-lucide="messages-square" class="w-6 h-6 text-blue-600"></i>
                    みんなの口コミ（新着）
                </h1>
                <p class="text-sm text-gray-500 mt-2 leading-relaxed">
                    全国のバイクショップに寄せられた利用者の口コミです。気になるお店があれば店名から詳細をご覧ください。
                </p>
            </div>
        </div>

        <div class="max-w-3xl mx-auto px-4 py-6">
            @if(session('report_success'))
            <div class="mb-4 text-xs font-bold text-gray-600 bg-white border border-gray-200 rounded-lg px-3 py-2">
                報告を受け付けました。確認します。ご協力ありがとうございます。
            </div>
            @endif

            <ul class="space-y-3">
                @forelse($comments as $cmt)
                <li class="bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-3.5" x-data="{ report: false }">
                    {{-- 店名（回遊リンク）＋所在地 --}}
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <a href="{{ route('shops.show', $cmt->shop) }}" class="text-sm font-black text-blue-600 hover:underline flex items-center gap-1 min-w-0">
                            <i data-lucide="store" class="w-3.5 h-3.5 shrink-0"></i>
                            <span class="truncate">{{ $cmt->shop->name }}</span>
                        </a>
                        @if($cmt->shop->prefecture)
                        <span class="text-[10px] font-bold text-gray-400 shrink-0">{{ $cmt->shop->prefecture }}{{ $cmt->shop->city }}</span>
                        @endif
                    </div>

                    {{-- コメント本文 --}}
                    <p class="text-sm text-gray-700 leading-relaxed bg-gray-50 rounded-xl px-3 py-2">「{{ $cmt->comment }}」</p>

                    {{-- 投稿者・日時・通報 --}}
                    <div class="flex items-center gap-1.5 mt-2 text-[11px] text-gray-400">
                        <span class="font-bold text-gray-500">{{ $cmt->submitter_name ?: '名無しライダー' }}さん</span>
                        @if($cmt->user_id)
                        <span class="inline-flex items-center gap-0.5 text-[9px] font-black text-blue-600 bg-blue-50 px-1 py-0.5 rounded">
                            <i data-lucide="badge-check" class="w-2.5 h-2.5"></i>ログインユーザー
                        </span>
                        @endif
                        <span>・{{ $cmt->created_at->diffForHumans() }}</span>
                        <button type="button" @click="report = !report"
                            class="ml-auto inline-flex items-center gap-0.5 font-bold text-gray-300 hover:text-rose-500 transition-colors"
                            aria-label="この投稿を報告する">
                            <i data-lucide="flag" class="w-2.5 h-2.5"></i>報告
                        </button>
                    </div>

                    {{-- 報告フォーム（フェーズ1の reports.store 連携） --}}
                    <form x-show="report" x-cloak method="POST" action="{{ route('reports.store') }}" class="mt-2 pt-2 border-t border-gray-100 space-y-1.5">
                        @csrf
                        <input type="hidden" name="type" value="shop_comment">
                        <input type="hidden" name="id" value="{{ $cmt->id }}">
                        <p class="text-[10px] font-bold text-gray-500">報告の理由（任意）</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach(\App\Models\Report::REASONS as $key => $label)
                            <label class="inline-flex items-center gap-1 cursor-pointer text-[10px] text-gray-600 bg-gray-50 border border-gray-200 rounded-full px-2 py-0.5">
                                <input type="radio" name="reason" value="{{ $key }}" class="accent-rose-500 w-2.5 h-2.5">{{ $label }}
                            </label>
                            @endforeach
                        </div>
                        {{-- ハニーポット（人間には非表示・ボット除け） --}}
                        <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden" style="display:none">
                        <button type="submit" class="text-[10px] font-black text-white bg-rose-500 hover:bg-rose-600 rounded-lg px-3 py-1 transition">報告する</button>
                    </form>
                </li>
                @empty
                <li class="bg-white rounded-2xl border border-gray-100 px-4 py-10 text-center">
                    <p class="text-sm text-gray-400 font-bold">まだ口コミがありません。</p>
                    <p class="text-xs text-gray-400 mt-1">お店の詳細ページから「このお店の情報を教える」で最初の口コミを投稿できます。</p>
                </li>
                @endforelse
            </ul>

            <div class="mt-6">{{ $comments->links() }}</div>
        </div>
    </div>
</x-layout>
