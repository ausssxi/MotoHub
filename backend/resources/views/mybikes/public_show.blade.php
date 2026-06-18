<x-layout>
    {{-- 公開表示は本人設定の公開ハンドルのみ。user->name(本名) は絶対に出さない。未設定は「名無しライダー」 --}}
    <x-slot:title>{{ $myBike->user->review_display_name ?? '名無しライダー' }}の{{ $myBike->bikeModel->name ?? $myBike->name }} | 愛車ガレージ | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $myBike->user->review_display_name ?? '名無しライダー' }}の愛車「{{ $myBike->display_name }}」。走行{{ number_format($myBike->current_odometer) }}km・累計維持費¥{{ number_format($share['total_cost']) }}{{ $share['avg_efficiency'] !== null ? '・平均燃費'.$share['avg_efficiency'].'km/L' : '' }}。燃費記録・整備ログを公開中。</x-slot:metaDescription>
    {{-- 動的OGP画像（バイク名＋走行距離＋維持費＋平均燃費＋カバー写真を合成したカード） --}}
    <x-slot:ogImage>{{ route('garage.public.ogp', $myBike->id) }}</x-slot:ogImage>
    {{-- 暫定 noindex（本格版＝公開opt-in＋ハンドル＋中身充実 が固まるまで半端な公開面を index させない）。
         ※noindex でも X/Facebook 等のカード取得は og: タグを読むため影響しない。 --}}
    <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">

            {{-- パンくず --}}
            <nav class="mb-6 text-xs font-bold text-gray-400 flex items-center gap-1 flex-wrap">
                <a href="{{ route('bikes.index') }}" class="hover:text-gray-600 transition-colors">トップ</a>
                <i data-lucide="chevron-right" class="w-3 h-3"></i>
                <a href="{{ route('garage.public.index') }}" class="hover:text-gray-600 transition-colors">みんなのガレージ</a>
                <i data-lucide="chevron-right" class="w-3 h-3"></i>
                <span class="text-gray-600">{{ $myBike->display_name }}</span>
            </nav>

            {{-- ヘッダーカード --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden mb-6">
                <div class="relative h-48 sm:h-56 bg-gray-900 overflow-hidden">
                    @if($myBike->display_image)
                        <img src="{{ $myBike->display_image }}" alt="{{ $myBike->display_name }}"
                             class="w-full h-full object-cover opacity-70" loading="lazy" decoding="async">
                    @else
                        <div class="flex items-center justify-center h-full text-white/30">
                            <i data-lucide="bike" class="w-16 h-16"></i>
                        </div>
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
                    <div class="absolute bottom-0 left-0 p-6 text-white w-full">
                        <div class="text-[10px] font-bold opacity-80 mb-1">{{ $myBike->bikeModel->manufacturer->name ?? '' }}</div>
                        <h1 class="text-2xl sm:text-3xl font-black leading-tight">{{ $myBike->display_name }}</h1>
                    </div>
                </div>

                <div class="p-6">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center">
                            <i data-lucide="user" class="w-5 h-5 text-gray-400"></i>
                        </div>
                        <div>
                            <p class="text-sm font-black text-gray-900">{{ $myBike->user->review_display_name ?? '名無しライダー' }}</p>
                            <p class="text-[10px] text-gray-400 font-bold">オーナー</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4 bg-gray-50 rounded-xl p-4">
                        <div class="text-center">
                            <p class="text-[10px] font-bold text-gray-400 mb-1">年式</p>
                            <p class="text-sm font-black text-gray-800">{{ $myBike->model_year ?? '-' }}</p>
                        </div>
                        <div class="text-center border-x border-gray-200">
                            <p class="text-[10px] font-bold text-gray-400 mb-1">走行距離</p>
                            <p class="text-sm font-black text-gray-800">{{ number_format($myBike->current_odometer) }} km</p>
                        </div>
                        <div class="text-center">
                            <p class="text-[10px] font-bold text-gray-400 mb-1">最新燃費</p>
                            @if($myBike->latest_efficiency)
                                <p class="text-sm font-black text-blue-600">{{ number_format($myBike->latest_efficiency, 1) }} km/L</p>
                            @else
                                <p class="text-sm font-bold text-gray-300">-</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- 外部シェア（X / リンクコピー） --}}
            @php
                $shareUrl = route('garage.public.show', $myBike->id);
                $shareText = $share['handle'].'の'.$share['bike_name'].'｜走行'.number_format($share['odometer']).'km・累計維持費¥'.number_format($share['total_cost']).($share['avg_efficiency'] !== null ? '・平均燃費'.$share['avg_efficiency'].'km/L' : '').' #MotoHub 愛車ガレージ';
                $xIntent = 'https://twitter.com/intent/tweet?text='.rawurlencode($shareText).'&url='.rawurlencode($shareUrl);
            @endphp
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-5 mb-6" x-data="{ copied: false, copy() { navigator.clipboard?.writeText(@js($shareUrl)).then(() => { this.copied = true; setTimeout(() => this.copied = false, 1800); }); } }">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-xs font-black text-gray-500 mr-1">このガレージをシェア</span>
                    <a href="{{ $xIntent }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-xs font-bold text-white bg-gray-900 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors">
                        <i data-lucide="twitter" class="w-3.5 h-3.5"></i> Xでシェア
                    </a>
                    <button type="button" @click="copy()" class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg transition-colors">
                        <i data-lucide="link" class="w-3.5 h-3.5"></i> <span x-text="copied ? 'コピーしました' : 'リンクをコピー'">リンクをコピー</span>
                    </button>
                </div>
            </div>

            {{-- 給油記録 --}}
            @if($myBike->fuelLogs->isNotEmpty())
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
                    <span class="bg-blue-100 text-blue-600 p-2 rounded-lg">
                        <i data-lucide="fuel" class="w-4 h-4"></i>
                    </span>
                    給油記録
                    <span class="text-xs text-gray-400 font-bold">({{ $myBike->fuelLogs->count() }}件)</span>
                </h2>

                <div class="space-y-3">
                    @foreach($myBike->fuelLogs as $log)
                    <div class="bg-gray-50 rounded-xl p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-[10px] font-bold text-gray-400 mb-0.5">{{ $log->filled_at->format('Y/m/d') }}</div>
                                <div class="text-sm font-black text-gray-800">{{ number_format($log->odometer) }} km</div>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-black text-blue-600">
                                    {{ $log->efficiency ? $log->efficiency . ' km/L' : '-' }}
                                </div>
                                <div class="text-[10px] font-bold text-gray-400">
                                    {{ $log->quantity }}L
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- 整備記録 --}}
            @if($myBike->maintenanceLogs->isNotEmpty())
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
                    <span class="bg-orange-100 text-orange-600 p-2 rounded-lg">
                        <i data-lucide="wrench" class="w-4 h-4"></i>
                    </span>
                    整備記録
                    <span class="text-xs text-gray-400 font-bold">({{ $myBike->maintenanceLogs->count() }}件)</span>
                </h2>

                <div class="space-y-3">
                    @foreach($myBike->maintenanceLogs as $log)
                    <div class="bg-gray-50 rounded-xl p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="text-[10px] font-bold text-gray-400 mb-0.5">{{ $log->maintained_at->format('Y/m/d') }}</div>
                                <div class="text-sm font-black text-gray-900">{{ $log->title }}</div>
                            </div>
                            @if($log->cost)
                            <div class="text-sm font-black text-orange-600">{{ number_format($log->cost) }}円</div>
                            @endif
                        </div>
                        @if($log->note)
                        <div class="text-xs text-gray-500 bg-white p-2 rounded-lg mt-2">{{ $log->note }}</div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- 車種詳細リンク --}}
            @if($myBike->bikeModel && $myBike->bikeModel->seo_url)
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-6">
                <a href="{{ $myBike->bikeModel->seo_url }}" class="flex items-center justify-between group">
                    <div class="flex items-center gap-3">
                        <span class="bg-gray-100 text-gray-600 p-2 rounded-lg">
                            <i data-lucide="file-text" class="w-4 h-4"></i>
                        </span>
                        <div>
                            <p class="text-sm font-black text-gray-800 group-hover:text-blue-600 transition-colors">{{ $myBike->bikeModel->name }} の車種情報</p>
                            <p class="text-[10px] text-gray-400 font-bold">中古車相場・スペック・レビューを見る</p>
                        </div>
                    </div>
                    <i data-lucide="chevron-right" class="w-5 h-5 text-gray-300 group-hover:text-blue-400 group-hover:translate-x-1 transition-all"></i>
                </a>
            </div>
            @endif

            {{-- CTA --}}
            <div class="bg-gradient-to-r from-pink-50 to-rose-50 rounded-3xl p-6 border border-pink-100 text-center">
                <i data-lucide="heart" class="w-8 h-8 text-pink-400 mx-auto mb-2"></i>
                <h3 class="text-lg font-black text-gray-900 mb-2">あなたも愛車を登録しませんか？</h3>
                <p class="text-xs text-gray-500 mb-4">燃費記録・整備ログを管理して、バイクライフをもっと楽しく</p>
                <a href="{{ route('mybikes.index') }}" class="inline-block bg-pink-600 text-white font-bold text-sm px-6 py-3 rounded-xl hover:bg-pink-700 transition-colors">
                    愛車を登録する
                </a>
            </div>

        </div>
    </div>

    <script>
        if (typeof lucide !== 'undefined') lucide.createIcons();
    </script>
</x-layout>
