<x-layout>
    <x-slot:title>みんなの愛車ガレージ - オーナーのバイクライフ | MotoHub</x-slot:title>
    <x-slot:metaDescription>MotoHubユーザーの愛車コレクション。燃費記録・整備ログ・カスタム写真を公開中。</x-slot:metaDescription>
    {{-- 暫定 noindex（本格版が固まるまで半端な公開面を index させない） --}}
    <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">
        {{-- ヘッダー --}}
        <div class="bg-gradient-to-r from-pink-600 to-rose-500 text-white py-12 sm:py-16">
            <div class="max-w-6xl mx-auto px-4 text-center">
                <p class="text-[10px] font-bold uppercase tracking-widest text-white/60 mb-2">Owner's Garage</p>
                <h1 class="text-2xl sm:text-3xl font-black mb-3">みんなの愛車ガレージ</h1>
                <p class="text-xs sm:text-sm text-white/80 font-medium">オーナーたちの愛車と、そのバイクライフをのぞいてみよう</p>
            </div>
        </div>

        <div class="max-w-6xl mx-auto px-4 py-10">
            @if($bikes->isEmpty())
                <div class="bg-white rounded-3xl p-12 text-center border border-gray-100 shadow-sm">
                    <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i data-lucide="bike" class="w-10 h-10 text-gray-300"></i>
                    </div>
                    <h2 class="text-lg font-black text-gray-800 mb-2">まだ愛車が登録されていません</h2>
                    <p class="text-gray-400 text-xs font-bold mb-8">最初のオーナーになりませんか？</p>
                    <a href="{{ route('mybikes.index') }}" class="inline-block bg-pink-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-pink-700 transition-colors shadow-md">
                        愛車を登録する
                    </a>
                </div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                    @foreach($bikes as $bike)
                        {{-- カード→公開ガレージ。オーナー名は別リンク→プロフィール（aの入れ子回避） --}}
                        <div class="group relative bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg hover:border-pink-200 transition-all duration-300">
                            <a href="{{ route('garage.public.show', $bike->id) }}" class="block">
                                <div class="aspect-[4/3] bg-gray-100 overflow-hidden relative">
                                    @if($bike->display_image)
                                        <img src="{{ $bike->display_image }}" alt="{{ $bike->display_name }}"
                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                             loading="lazy" decoding="async">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                                            <i data-lucide="bike" class="w-8 h-8"></i>
                                        </div>
                                    @endif
                                </div>
                                <div class="px-4 pt-4 pb-1">
                                    <p class="text-[10px] font-bold text-gray-400 mb-0.5">{{ $bike->bikeModel->manufacturer->name ?? '' }}</p>
                                    <h2 class="text-sm font-black text-gray-800 group-hover:text-pink-600 transition-colors line-clamp-1 mb-1">{{ $bike->display_name }}</h2>
                                </div>
                            </a>
                            <div class="px-4 pb-4 flex items-center justify-between">
                                @if($bike->user->public_token)
                                    <a href="{{ route('riders.profile', $bike->user->public_token) }}" class="text-[10px] font-bold text-gray-400 hover:text-pink-600 transition-colors">
                                        <i data-lucide="user" class="w-3 h-3 inline"></i>
                                        {{ $bike->user->review_display_name ?? '名無しライダー' }}
                                    </a>
                                @else
                                    <p class="text-[10px] font-bold text-gray-400">
                                        <i data-lucide="user" class="w-3 h-3 inline"></i>
                                        {{ $bike->user->review_display_name ?? '名無しライダー' }}
                                    </p>
                                @endif
                                @if($bike->model_year)
                                    <span class="text-[10px] text-gray-400">{{ $bike->model_year }}年式</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $bikes->links() }}
                </div>
            @endif

            {{-- CTA --}}
            <div class="mt-12 bg-gradient-to-r from-pink-50 to-rose-50 rounded-3xl p-8 border border-pink-100 text-center">
                <i data-lucide="heart" class="w-8 h-8 text-pink-400 mx-auto mb-3"></i>
                <h3 class="text-lg font-black text-gray-900 mb-2">あなたの愛車も登録しませんか？</h3>
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
