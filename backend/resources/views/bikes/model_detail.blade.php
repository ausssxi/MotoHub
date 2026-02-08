<x-layout>
    <x-slot:title>{{ $model->name }} の買取相場・最新価格情報 | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $model->name }}（{{ $model->manufacturer->name }}）の買取相場、リセールバリュー、中古車販売価格の推移を徹底分析。現在販売中の在庫車両も一括検索できます。</x-slot:metaDescription>

    <x-slot:scripts>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        
        {{-- ★修正: データをグローバル変数として定義し、外部JSを読み込む --}}
        <script>
            window.bikeModelStats = {!! json_encode($stats ?? []) !!};
        </script>
        <script src="{{ asset('js/bikes/model_detail.js') }}"></script>
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
                                <a href="https://px.a8.net/svt/ejp?a8mat=4AX6CG+5PEKHE+1BFI+61RIA" target="_blank" rel="nofollow" class="block w-full bg-yellow-500 hover:bg-yellow-400 text-white font-black text-center py-4 rounded-xl shadow-lg shadow-yellow-500/30 transition-all transform hover:-translate-y-1 flex items-center justify-center gap-2">
                                <span>あなたの {{ $model->name }} を無料査定する</span>
                                    <i data-lucide="arrow-right-circle" class="w-5 h-5"></i>
                                </a>
                                <img src="https://www.a8.net/svt/ejp?a8mat=4AX6CG+5PEKHE+1BFI+61RIA" width="100%" height="auto" alt="あなたの {{ $model->name }} を無料査定する">
                                <p class="text-[10px] text-gray-400 text-center font-bold">
                                    提携: バイク買取専門店バイクワン
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

                    {{-- ★追加: 3. ユーザーレビュー --}}
                    <div class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100" id="reviews">
                        <div class="flex items-center justify-between mb-8">
                            <h2 class="text-xl font-black text-gray-900 flex items-center gap-2">
                                <span class="bg-green-100 text-green-600 p-2 rounded-lg"><i data-lucide="message-circle" class="w-5 h-5"></i></span>
                                ユーザーレビュー
                                <span class="text-sm text-gray-500 font-bold ml-2">({{ $model->reviews->count() }}件)</span>
                            </h2>
                            <a href="#review-form" class="text-xs font-bold bg-black text-white px-4 py-2 rounded-full hover:bg-gray-800 transition-colors inline-flex items-center">
                                <i data-lucide="pen-tool" class="w-3 h-3 mr-1"></i>投稿する
                            </a>
                        </div>

                        {{-- レビュー一覧 --}}
                        <div class="space-y-6 mb-12">
                            @forelse($model->reviews as $review)
                                <div class="border-b border-gray-100 pb-6 last:border-0">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                            <div class="flex text-yellow-400">
                                                @for($i=1; $i<=5; $i++)
                                                    <i data-lucide="star" class="w-4 h-4 {{ $i <= $review->rating ? 'fill-current' : 'text-gray-200' }}"></i>
                                                @endfor
                                            </div>
                                            <span class="text-sm font-bold text-gray-900">{{ $review->title }}</span>
                                        </div>
                                        <span class="text-[10px] text-gray-400">{{ $review->created_at->format('Y/m/d') }}</span>
                                    </div>
                                    <p class="text-sm text-gray-600 leading-relaxed mb-2 whitespace-pre-wrap">{{ $review->body }}</p>
                                    <p class="text-xs text-gray-400 font-bold">
                                        by {{ $review->nickname }}
                                    </p>
                                </div>
                            @empty
                                <div class="text-center py-8 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                                    <p class="text-sm text-gray-500 font-bold mb-2">まだレビューがありません。</p>
                                    <p class="text-xs text-gray-400">最初のレビューを投稿してみませんか？</p>
                                </div>
                            @endforelse
                        </div>

                        {{-- 投稿フォーム --}}
                        <div id="review-form" class="bg-gray-50 rounded-2xl p-6 border border-gray-100">
                            <h3 class="text-base font-black text-gray-900 mb-4">レビューを投稿する</h3>
                            
                            @if(session('success'))
                                <div class="mb-4 p-4 bg-green-100 text-green-700 text-sm font-bold rounded-xl border border-green-200">
                                    {{ session('success') }}
                                </div>
                            @endif

                            {{-- エラー表示 --}}
                            @if ($errors->any())
                                <div class="mb-4 p-4 bg-red-50 text-red-600 text-sm font-bold rounded-xl border border-red-100">
                                    <ul class="list-disc list-inside">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form action="{{ route('bikes.model_detail.review', $model->id) }}" method="POST">
                                @csrf
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 mb-1">ニックネーム</label>
                                        <input type="text" name="nickname" value="{{ old('nickname') }}" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="名無しライダー">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 mb-1">評価</label>
                                        <select name="rating" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                            <option value="5" @selected(old('rating') == 5)>★★★★★ (最高)</option>
                                            <option value="4" @selected(old('rating') == 4)>★★★★☆ (良い)</option>
                                            <option value="3" @selected(old('rating') == 3)>★★★☆☆ (普通)</option>
                                            <option value="2" @selected(old('rating') == 2)>★★☆☆☆ (いまいち)</option>
                                            <option value="1" @selected(old('rating') == 1)>★☆☆☆☆ (悪い)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-xs font-bold text-gray-500 mb-1">タイトル</label>
                                    <input type="text" name="title" value="{{ old('title') }}" required class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="例: 燃費が良くて乗りやすい！">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-xs font-bold text-gray-500 mb-1">レビュー内容</label>
                                    <textarea name="body" required rows="4" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="良い点、気になった点などを自由に書いてください。">{{ old('body') }}</textarea>
                                </div>
                                <button type="submit" class="w-full bg-black hover:bg-gray-800 text-white font-bold py-3 rounded-xl transition-all shadow-lg transform active:scale-95">
                                    投稿する
                                </button>
                            </form>
                        </div>
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
                                            <img src="{{ $bike['images'][0] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
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
                                        <div class="text-[10px] text-gray-400 mt-0.5">
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