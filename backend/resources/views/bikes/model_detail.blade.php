<x-layout>
    <x-slot:title>{{ $model->name }} の買取相場・最新価格情報 | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $model->name }}（{{ $model->manufacturer->name }}）の買取相場、リセールバリュー、中古車販売価格の推移を徹底分析。現在販売中の在庫車両も一括検索できます。</x-slot:metaDescription>

    <x-slot:scripts>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        {{-- チャート描画用JS --}}
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                // null合体演算子を使って、変数が空の場合でも安全に空配列を渡すように修正
                const stats = @json($stats ?? []);
                
                if (!stats || !stats.distribution || stats.distribution.length === 0) return;

                const chartCanvas = document.getElementById('priceChart');
                if (!chartCanvas) return;

                const ctx = chartCanvas.getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: stats.distribution.map(d => d.label),
                        datasets: [{
                            label: '台数',
                            data: stats.distribution.map(d => d.count),
                            backgroundColor: 'rgba(59, 130, 246, 0.5)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { 
                                beginAtZero: true, 
                                grid: { color: '#f3f4f6' },
                                ticks: { stepSize: 1 }
                            },
                            x: { 
                                grid: { display: false }, 
                                ticks: { font: { size: 10 } } 
                            }
                        }
                    }
                });
            });
        </script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    {{-- ヘッダーエリア --}}
    <div class="bg-gray-900 text-white pt-10 pb-16 relative overflow-hidden">
        <div class="absolute inset-0 z-0 opacity-30">
            @if($model->image_url)
                <img src="{{ $model->image_url }}" class="w-full h-full object-cover blur-sm" alt="">
            @else
                <img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=2070" class="w-full h-full object-cover blur-sm" alt="">
            @endif
        </div>
        <div class="max-w-7xl mx-auto px-4 relative z-10">
            <div class="inline-block bg-blue-600 text-white text-[10px] font-bold px-2 py-1 rounded mb-2">
                {{ $model->manufacturer->name }}
            </div>
            <h1 class="text-3xl sm:text-5xl font-black tracking-tight mb-2">
                {{ $model->name }}
            </h1>
            <p class="text-gray-300 font-bold text-sm">
                Market Price & Resale Value Analysis
            </p>
        </div>
    </div>

    <div class="bg-gray-50 min-h-screen -mt-8 pb-16">
        <div class="max-w-7xl mx-auto px-4">
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                
                {{-- メインコンテンツ --}}
                <div class="lg:col-span-8 space-y-8">
                    
                    {{-- 1. 買取相場・リセール情報（収益化ポイント） --}}
                    <div class="bg-white rounded-3xl shadow-lg p-6 sm:p-8 border border-gray-100">
                        <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                            <span class="bg-yellow-100 text-yellow-600 p-2 rounded-lg"><i data-lucide="coins" class="w-5 h-5"></i></span>
                            買取相場・リセールバリュー
                        </h2>

                        @if(!empty($resale) && isset($resale['resale_min']) && $resale['data_count'] > 0)
                            <div class="bg-gradient-to-r from-yellow-50 to-orange-50 rounded-2xl p-6 mb-6 border border-yellow-100">
                                <p class="text-xs font-bold text-gray-500 mb-2 text-center">このバイクの想定買取価格</p>
                                <div class="text-center mb-4">
                                    <span class="text-4xl sm:text-5xl font-black text-yellow-600 tracking-tighter">
                                        {{ $resale['resale_min'] }}<span class="text-lg text-gray-600 mx-1">~</span>{{ $resale['resale_max'] }}
                                    </span>
                                    <span class="text-sm font-bold text-gray-500">万円</span>
                                </div>
                                <p class="text-[10px] text-gray-400 text-center">
                                    ※市場流通価格（平均{{ $resale['market_avg'] }}万円）から独自アルゴリズムで算出。<br>実際の買取額は車両状態や時期により変動します。
                                </p>
                            </div>

                            {{-- アフィリエイト導線 --}}
                            <div class="space-y-3">
                                <a href="https://px.a8.net/..." target="_blank" rel="nofollow" class="block w-full bg-yellow-500 hover:bg-yellow-400 text-white font-black text-center py-4 rounded-xl shadow-lg shadow-yellow-500/30 transition-all transform hover:-translate-y-1 flex items-center justify-center gap-2">
                                    <span>あなたの {{ $model->name }} を無料査定する</span>
                                    <i data-lucide="arrow-right-circle" class="w-5 h-5"></i>
                                </a>
                                <p class="text-[10px] text-gray-400 text-center font-bold">
                                    Supported by バイク王 / KATIX
                                </p>
                            </div>
                        @else
                            <div class="text-center py-8 bg-gray-50 rounded-2xl border border-gray-100">
                                <i data-lucide="bar-chart-2" class="w-8 h-8 text-gray-300 mx-auto mb-2"></i>
                                <p class="text-sm text-gray-500 font-bold">
                                    データ不足のため、現在買取相場を算出できません。
                                </p>
                            </div>
                        @endif
                    </div>

                    {{-- 2. 市場価格分析（チャート） --}}
                    <div class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100">
                        <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                            <span class="bg-blue-100 text-blue-600 p-2 rounded-lg"><i data-lucide="bar-chart-2" class="w-5 h-5"></i></span>
                            中古車価格の分布
                        </h2>
                        
                        @if(!empty($stats) && isset($stats['avg']) && $stats['count'] > 0)
                            <div class="grid grid-cols-3 gap-4 mb-8 text-center">
                                <div class="bg-gray-50 rounded-xl p-3">
                                    <div class="text-[10px] font-bold text-gray-400">平均価格</div>
                                    <div class="text-xl font-black text-gray-800">{{ $stats['avg'] }}<span class="text-xs">万円</span></div>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-3">
                                    <div class="text-[10px] font-bold text-gray-400">最安値</div>
                                    <div class="text-xl font-black text-blue-600">{{ $stats['min'] }}<span class="text-xs">万円</span></div>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-3">
                                    <div class="text-[10px] font-bold text-gray-400">最高値</div>
                                    <div class="text-xl font-black text-red-500">{{ $stats['max'] }}<span class="text-xs">万円</span></div>
                                </div>
                            </div>
                            
                            <div class="relative h-64 w-full">
                                <canvas id="priceChart"></canvas>
                            </div>
                        @else
                            <div class="text-center py-8 bg-gray-50 rounded-2xl border border-gray-100">
                                <p class="text-sm text-gray-500 font-bold">データがありません。</p>
                            </div>
                        @endif
                    </div>

                </div>

                {{-- サイドバー（在庫リスト） --}}
                <div class="lg:col-span-4 space-y-6">
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sticky top-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-black text-gray-900">販売中の車両</h3>
                            <a href="{{ route('bikes.search', ['bike_model_id' => $model->id]) }}" class="text-xs font-bold text-blue-600 hover:underline">すべて見る</a>
                        </div>

                        <div class="space-y-4">
                            @forelse($listings as $bike)
                                <a href="{{ route('bikes.show', $bike['id']) }}" class="flex gap-3 group">
                                    <div class="w-20 h-20 shrink-0 rounded-lg overflow-hidden bg-gray-100 relative">
                                        @if(!empty($bike['images'][0]))
                                            <img src="{{ $bike['images'][0] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" alt="">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-gray-300"><i data-lucide="bike"></i></div>
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0 py-1">
                                        <h4 class="text-xs font-black text-gray-800 line-clamp-2 group-hover:text-blue-600 transition-colors mb-1">
                                            {{ $bike['name'] }}
                                        </h4>
                                        <div class="text-red-500 font-black text-sm">
                                            {{ $bike['total_price'] }}<span class="text-[10px]">万円</span>
                                        </div>
                                        <div class="text-[10px] text-gray-400 mt-0.5 truncate">
                                            {{ $bike['prefecture'] }}
                                        </div>
                                    </div>
                                </a>
                            @empty
                                <p class="text-xs text-gray-400 font-bold text-center py-4">現在、在庫はありません。</p>
                            @endforelse
                        </div>
                        
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <a href="{{ route('bikes.search', ['bike_model_id' => $model->id]) }}" class="block w-full bg-gray-900 hover:bg-gray-800 text-white font-bold text-xs text-center py-3 rounded-xl transition-colors">
                                在庫を検索する
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-layout>