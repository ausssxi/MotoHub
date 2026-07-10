{{-- (a) 初回オンボーディングの中身（モバイル帯／デスクトップ地図内オーバーレイの両ラッパで共用）。
     親要素の x-data({ show }) スコープ内で include すること。close は show=false + localStorage 記憶。 --}}
<button type="button"
        @click="localStorage.setItem('riders_map_onboarding_dismissed_at', Date.now().toString()); show = false"
        class="absolute top-2 right-2 text-gray-300 hover:text-gray-500 p-1" aria-label="閉じる">
    <i data-lucide="x" class="w-4 h-4"></i>
</button>
<p class="text-sm font-black text-gray-800 pr-6">このマップでできること</p>
<p class="text-xs text-gray-600 leading-relaxed mt-1.5">
    行きたいエリアを開くと、バイク駐車場・給油ポイント・道の駅・ツーリング記事が地図に重なります。気になる場所をタップして、そのまま走るルートも引けます。
</p>
<div class="flex flex-wrap gap-x-3 gap-y-1 mt-2 text-[11px] font-bold text-gray-500">
    <span>🅿️ 駐車場を探す</span>
    <span>⛽ 給油ポイント</span>
    <span>🔥 ツーリング記事</span>
</div>
<div class="flex flex-wrap items-center gap-x-3 gap-y-2 mt-3">
    {{-- 主CTA: 既存「AIルート提案」アクションを流用（#btn-ai-route を発火・新規実装なし） --}}
    <a href="#" @click.prevent="document.getElementById('btn-ai-route').click()"
       class="bg-violet-600 hover:bg-violet-700 text-white text-xs font-black px-4 py-2.5 rounded-xl transition inline-flex items-center gap-1">
        AIにルートを提案してもらう →
    </a>
    {{-- 従CTA: /touring（記事一覧）へ --}}
    <a href="{{ route('touring.index') }}" class="text-xs font-bold text-gray-500 hover:text-blue-600 underline underline-offset-2">
        ツーリング記事から探す
    </a>
</div>
