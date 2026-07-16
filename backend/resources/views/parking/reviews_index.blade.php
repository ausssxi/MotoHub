<x-layout>
    <x-slot:title>みんなの口コミ（新着）| バイク駐車場の評判・停めやすさ | MotoHub</x-slot:title>
    <x-slot:metaDescription>全国のバイク駐車場に寄せられた利用者の口コミを新着順に掲載。停めやすさ・見つけやすさ・周辺の雰囲気など、実際に停めた人の声を集めました。あなたの体験も投稿できます。</x-slot:metaDescription>

    {{-- 2頁目以降は薄い重複回避のため noindex（1頁目のみ index） --}}
    @if($reviews->currentPage() > 1)
    <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">
        <div class="bg-white border-b border-gray-200 pt-8 pb-8 px-4">
            <div class="max-w-3xl mx-auto">
                <nav class="flex text-xs font-bold text-gray-400 mb-4" aria-label="Breadcrumb">
                    <a href="{{ route('bikes.index') }}" class="hover:text-green-600">ホーム</a>
                    <span class="mx-1.5">/</span>
                    <a href="{{ route('parking.area.index') }}" class="hover:text-green-600">バイク駐車場</a>
                    <span class="mx-1.5">/</span>
                    <span class="text-gray-600">みんなの口コミ</span>
                </nav>
                <h1 class="text-xl md:text-2xl font-black text-gray-800 flex items-center gap-2">
                    <i data-lucide="messages-square" class="w-6 h-6 text-green-600"></i>
                    みんなの口コミ（新着）
                </h1>
                <p class="text-sm text-gray-500 mt-2 leading-relaxed">
                    全国のバイク駐車場に寄せられた利用者の口コミです。気になる駐車場があれば駐車場名から詳細をご覧ください。
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
                @forelse($reviews as $review)
                {{-- カード全体をクリックで駐車場ページへ（リンク/ボタン/フォーム上のクリックは除外＝通報等は独立動作） --}}
                <li class="bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-3.5 cursor-pointer hover:border-green-200 transition-colors"
                    x-data="{ report: false }"
                    @click="if(!$event.target.closest('a,button,input,label,form')) window.location='{{ route('parking.show', $review->bikeParking) }}'">
                    <div class="flex gap-3">
                        {{-- 駐車場サムネイル。画像はプレースホルダーの上に重ね、欠損時は onerror で隠す --}}
                        <div class="relative w-20 h-20 rounded-xl overflow-hidden bg-gray-100 shrink-0 flex items-center justify-center">
                            <i data-lucide="square-parking" class="w-6 h-6 text-gray-300"></i>
                            @if($review->bikeParking->display_image_url)
                            <img src="{{ $review->bikeParking->display_image_url }}" alt="{{ $review->bikeParking->name }}"
                                 loading="lazy" decoding="async"
                                 class="absolute inset-0 w-full h-full object-cover"
                                 onerror="this.style.display='none'">
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            {{-- 駐車場名（回遊リンク）＋所在地（都道府県はエリアページへ） --}}
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <a href="{{ route('parking.show', $review->bikeParking) }}" class="text-sm font-black text-green-700 hover:underline flex items-center gap-1 min-w-0">
                                    <span class="truncate">{{ $review->bikeParking->name }}</span>
                                </a>
                                @if($review->bikeParking->prefecture)
                                <a href="{{ route('parking.area.prefecture', $review->bikeParking->prefecture) }}" class="text-[10px] font-bold text-gray-400 hover:text-green-600 shrink-0">
                                    {{ $review->bikeParking->prefecture }}{{ $review->bikeParking->city }}
                                </a>
                                @endif
                            </div>

                            {{-- 星評価 --}}
                            <div class="flex items-center gap-0.5 mb-1">
                                @for($i = 1; $i <= 5; $i++)
                                <i data-lucide="star" class="w-3 h-3 {{ $i <= $review->rating ? 'text-yellow-400 fill-yellow-400' : 'text-gray-200' }}"></i>
                                @endfor
                            </div>

                            {{-- 本文 --}}
                            <p class="text-sm text-gray-700 leading-relaxed bg-gray-50 rounded-xl px-3 py-2 whitespace-pre-line break-words">「{{ $review->body }}」</p>

                            {{-- 投稿者・日時・通報 --}}
                            <div class="flex items-center gap-1.5 mt-2 text-[11px] text-gray-400">
                                <x-user-avatar :user="$review->user" :name="$review->display_name" :size="6" />
                                <span class="font-bold text-gray-500">{{ $review->display_name }}さん</span>
                                @if($review->user_id)
                                <span class="inline-flex items-center gap-0.5 text-[9px] font-black text-green-700 bg-green-50 px-1 py-0.5 rounded">
                                    <i data-lucide="badge-check" class="w-2.5 h-2.5"></i>ログインユーザー
                                </span>
                                @endif
                                <span>・{{ $review->created_at->diffForHumans() }}</span>
                                <button type="button" @click="report = !report"
                                    class="ml-auto inline-flex items-center gap-0.5 font-bold text-gray-300 hover:text-red-500 transition-colors"
                                    aria-label="この投稿を報告する">
                                    <i data-lucide="flag" class="w-2.5 h-2.5"></i>報告
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- その先への明示アンカー（アンカーテキストに駐車場名＝内部リンク価値） --}}
                    <a href="{{ route('parking.show', $review->bikeParking) }}" class="mt-2.5 inline-flex items-center gap-1 text-xs font-black text-green-700 hover:underline">
                        {{ $review->bikeParking->name }}の詳細を見る
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>

                    {{-- 報告フォーム（polymorphic reports・parking_review・存在クラスで塗る） --}}
                    <form x-show="report" x-cloak method="POST" action="{{ route('reports.store') }}" class="mt-2 pt-2 border-t border-gray-100 space-y-1.5">
                        @csrf
                        <input type="hidden" name="type" value="parking_review">
                        <input type="hidden" name="id" value="{{ $review->id }}">
                        <p class="text-[10px] font-bold text-gray-500">報告の理由（任意）</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach(\App\Models\Report::REASONS as $key => $label)
                            <label class="inline-flex items-center gap-1 cursor-pointer text-[10px] text-gray-600 bg-gray-50 border border-gray-200 rounded-full px-2 py-0.5">
                                <input type="radio" name="reason" value="{{ $key }}" class="accent-red-500 w-2.5 h-2.5">{{ $label }}
                            </label>
                            @endforeach
                        </div>
                        {{-- ハニーポット（人間には非表示・ボット除け） --}}
                        <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden" style="display:none">
                        <button type="submit" class="text-[10px] font-black text-white bg-red-500 hover:bg-red-600 rounded-lg px-3 py-1 transition">報告する</button>
                    </form>
                </li>
                @empty
                <li class="bg-white rounded-2xl border border-gray-100 px-4 py-10 text-center">
                    <p class="text-sm text-gray-400 font-bold">まだ口コミがありません。</p>
                    <p class="text-xs text-gray-400 mt-1">駐車場の詳細ページから、星をタップして最初の口コミを投稿できます。</p>
                </li>
                @endforelse
            </ul>

            <div class="mt-6">{{ $reviews->links() }}</div>
        </div>
    </div>
</x-layout>
