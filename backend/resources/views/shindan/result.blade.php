@php
    // シェア文言の構築
    $topBike = $bikes->first();
    $shareText = "私のライダータイプは【{$riderType['name']}】でした！\n";
    if ($topBike) {
        $shareText .= "おすすめの相棒は「{$topBike->name}」！\n";
    }
    $shareText .= "#MotoHubバイク診断 #バイク乗りと繋がりたい";
    
    $shareUrl = url()->current() . '?' . http_build_query(request()->all());
    $twitterUrl = "https://twitter.com/intent/tweet?text=" . urlencode($shareText) . "&url=" . urlencode($shareUrl);
    
    // ライダータイプ別のOGP画像パス（画像アセットがある前提）
    $ogImagePath = asset("images/shindan/ogp/{$riderType['key']}.png");
@endphp

<x-layout>
    <x-slot:title>{{ $riderType['emoji'] }} {{ $riderType['name'] }} - 診断結果 | MotoHub</x-slot:title>

    {{-- ★OGP画像の出し分け --}}
    <x-slot:ogImage>{{ $ogImagePath }}</x-slot:ogImage>
    
    <x-slot:metaDescription>
        バイク相性診断の結果：あなたのタイプは「{{ $riderType['name'] }}」！{{ $riderType['desc'] }} MotoHubで相性ピッタリのバイクを探そう。
    </x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="false" />
    </x-slot:navigation>

    <div class="min-h-screen bg-gray-950 text-white py-12 px-4 sm:py-20">
        <div class="max-w-xl mx-auto">

            {{-- 1. ライダータイプカード --}}
            <div class="rounded-[2.5rem] overflow-hidden shadow-2xl mb-10 border border-white/10">
                <div class="bg-gradient-to-br {{ $riderType['bg'] }} p-8 sm:p-12 text-center relative overflow-hidden">
                    {{-- 装飾 --}}
                    <div class="absolute inset-0 opacity-10" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; viewBox=&quot;0 0 40 40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 40L40 0H20L0 20M40 40V20L20 40&quot; fill=&quot;%23fff&quot; fill-opacity=&quot;.15&quot;/%3E%3C/svg%3E');"></div>
                    
                    <div class="relative z-10">
                        <div class="text-[10px] font-black text-white/50 uppercase tracking-[0.3em] mb-6">Diagnosis Result</div>
                        <div class="text-7xl mb-6 drop-shadow-lg">{{ $riderType['emoji'] }}</div>
                        <h1 class="text-3xl sm:text-4xl font-black text-white mb-6 tracking-tighter">{{ $riderType['name'] }}</h1>
                        <p class="text-sm sm:text-base text-white/90 font-bold leading-relaxed max-w-sm mx-auto drop-shadow-sm">
                            {{ $riderType['desc'] }}
                        </p>
                    </div>
                </div>
                
                {{-- シェアボタンをカードの直下に配置 --}}
                <div class="bg-white/5 p-6 border-t border-white/10 text-center">
                    <p class="text-[10px] font-black text-white/40 uppercase tracking-widest mb-4">Results Share</p>
                    <a href="{{ $twitterUrl }}" target="_blank" class="inline-flex items-center gap-2 bg-white text-black px-8 py-4 rounded-2xl font-black text-sm hover:bg-gray-200 transition-all active:scale-95 shadow-xl">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        結果をXでシェアする
                    </a>
                </div>
            </div>

            {{-- 2. 提案されたバイク（3台） --}}
            <div class="space-y-6 mb-12">
                <h3 class="text-xs font-black text-gray-500 uppercase tracking-widest flex items-center gap-2 px-2">
                    <i data-lucide="sparkles" class="w-4 h-4 text-yellow-400 fill-current"></i>
                    AI Suggested Bikes
                </h3>

                @forelse($bikes as $index => $b)
                    <div class="group relative bg-gray-900 rounded-3xl border border-white/5 overflow-hidden hover:border-white/20 transition-all shadow-lg">
                        <a href="{{ route('bikes.search', ['bike_model_id' => $b->id]) }}" class="absolute inset-0 z-10"></a>
                        
                        <div class="flex items-stretch h-32 sm:h-40">
                            {{-- 画像エリア --}}
                            <div class="w-32 sm:w-48 shrink-0 relative overflow-hidden bg-gray-800">
                                @if($b->image_url)
                                    <img src="{{ $b->image_url }}" alt="{{ $b->name }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-700">
                                        <i data-lucide="bike" class="w-10 h-10"></i>
                                    </div>
                                @endif
                                
                                {{-- 順位バッジ --}}
                                <div class="absolute top-3 left-3 w-8 h-8 rounded-full flex items-center justify-center font-black text-sm shadow-lg border border-white/20
                                    {{ $index === 0 ? 'bg-yellow-500 text-yellow-950' : 'bg-gray-700 text-white' }}">
                                    {{ $index + 1 }}
                                </div>
                            </div>

                            {{-- テキストエリア --}}
                            <div class="flex-1 p-4 sm:p-6 flex flex-col justify-center">
                                <div class="text-[9px] sm:text-[10px] font-black text-blue-400 uppercase tracking-widest mb-1">{{ $b->manufacturer?->name }}</div>
                                <h4 class="text-sm sm:text-lg font-black text-white leading-tight mb-3 group-hover:text-blue-400 transition-colors">{{ $b->name }}</h4>
                                
                                <div class="flex items-center justify-between mt-auto">
                                    <span class="text-[10px] font-black text-white/40 uppercase">{{ $b->categoryData?->name ?? 'MT' }}</span>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-black text-blue-400 group-hover:translate-x-1 transition-transform">
                                        在庫を検索する <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-center text-gray-500 py-10">相性の良いバイクが見つかりませんでした。</p>
                @endforelse
            </div>

            {{-- 3. アクションエリア --}}
            <div class="space-y-4 pt-10 border-t border-white/10">
                <a href="{{ route('shindan.index') }}" class="block w-full bg-white/5 hover:bg-white/10 border border-white/10 text-white font-black text-center py-6 rounded-2xl transition active:scale-[0.98] text-lg">
                    もう一度診断する 🔄
                </a>
                <a href="{{ route('bikes.index') }}" class="block w-full text-center text-gray-500 hover:text-white text-xs font-bold transition">
                    MotoHubトップへ戻る
                </a>
            </div>

        </div>
    </div>
</x-layout>