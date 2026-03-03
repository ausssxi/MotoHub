<x-layout>
    <x-slot:title>{{ $model->name }} 中古車・買取相場 | {{ $model->manufacturer->name }} | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $model->name }}（{{ $model->manufacturer->name }}）の中古車情報{{ $activeCount > 0 ? '【現在' . $activeCount . '台販売中】' : '' }}。買取相場・リセールバリュー・価格推移・オーナーレビューを徹底分析。</x-slot:metaDescription>

    <x-slot:styles>
        <x-jsonld.model-product :model="$model" :stats="$stats" :reviewStats="$reviewStats ?? null" />
    </x-slot:styles>
    
    <x-slot:scripts>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://www.google.com/recaptcha/api.js?render={{ env('RECAPTCHA_SITE_KEY') }}"></script>
        <script>
            window.bikeModelStats = @json($stats ?? []);
            window.bikeModelHistory = @json($history ?? []);
        </script>
        <script src="{{ asset('js/bikes/model_detail.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('review-form-element');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const submitBtn = form.querySelector('button[type="submit"]');
                        const originalText = submitBtn.innerHTML;
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = 'スパムチェック中...';
                        grecaptcha.ready(function() {
                            grecaptcha.execute('{{ env('RECAPTCHA_SITE_KEY') }}', {action: 'submit_review'}).then(function(token) {
                                document.getElementById('recaptcha-token').value = token;
                                form.submit();
                            }).catch(function() {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                                alert('スパムチェックに失敗しました。時間をおいて再試行してください。');
                            });
                        });
                    });
                }
            });
        </script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    {{-- ★追加: パンくずリスト --}}
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <ol class="flex items-center gap-1.5 text-xs text-gray-400 font-bold overflow-x-auto whitespace-nowrap">
                <li><a href="{{ route('bikes.index') }}" class="hover:text-blue-600 transition-colors">トップ</a></li>
                <li><i data-lucide="chevron-right" class="w-3 h-3 inline"></i></li>
                <li><a href="{{ route('bikes.models') }}" class="hover:text-blue-600 transition-colors">車種一覧</a></li>
                <li><i data-lucide="chevron-right" class="w-3 h-3 inline"></i></li>
                <li><a href="{{ route('bikes.search', ['manufacturer_id' => $model->manufacturer_id]) }}" class="hover:text-blue-600 transition-colors">{{ $model->manufacturer->name }}</a></li>
                <li><i data-lucide="chevron-right" class="w-3 h-3 inline"></i></li>
                <li class="text-gray-700">{{ $model->name }}</li>
            </ol>
        </div>
    </nav>

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
                中古車価格・買取相場・オーナーレビュー
            </p>

            {{-- ★追加: ヘッダーに要約スタッツ --}}
            <div class="flex flex-wrap gap-4 mt-6">
                @if(!empty($stats) && isset($stats['avg']) && $stats['count'] > 0)
                <div class="bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2">
                    <span class="text-[10px] text-gray-400 block">中古平均価格</span>
                    <span class="text-xl font-black">{{ $stats['avg'] }}<span class="text-xs">万円</span></span>
                </div>
                @endif
                @if($activeCount > 0)
                <div class="bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2">
                    <span class="text-[10px] text-gray-400 block">販売中</span>
                    <span class="text-xl font-black">{{ number_format($activeCount) }}<span class="text-xs">台</span></span>
                </div>
                @endif
                @if(isset($reviewStats) && $reviewStats->count > 0)
                <div class="bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2">
                    <span class="text-[10px] text-gray-400 block">オーナー評価</span>
                    <span class="text-xl font-black flex items-center gap-1">
                        <i data-lucide="star" class="w-4 h-4 fill-current text-yellow-400"></i>
                        {{ $reviewStats->avg_rating }}
                        <span class="text-xs text-gray-400">({{ $reviewStats->count }}件)</span>
                    </span>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="bg-gray-50 min-h-screen -mt-8 pb-16">
        <div class="max-w-7xl mx-auto px-4">
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 pt-8">
                
                {{-- メインコンテンツ --}}
                <div class="lg:col-span-8 space-y-8">

                    {{-- ★追加: 車種紹介テキスト（SEOの要） --}}
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <h2 class="text-xl font-black text-gray-900 mb-4 flex items-center gap-2">
                            <span class="bg-indigo-100 text-indigo-600 p-2 rounded-lg"><i data-lucide="info" class="w-5 h-5"></i></span>
                            {{ $model->name }}とは
                        </h2>
                        <div class="text-sm text-gray-600 leading-relaxed space-y-3">
                            <p>
                                {{ $model->name }}は{{ $model->manufacturer->name }}が
                                @if($model->displacement){{ $model->displacement }}ccクラスで@endif
                                展開する
                                @if($model->categoryData){{ $model->categoryData->name }}タイプの@endif
                                バイクです。
                                @if($model->weight)車両重量は{{ $model->weight }}kgで、@endif
                                @if($model->seat_height)シート高{{ $model->seat_height }}mmと@endif
                                @if($model->displacement && $model->displacement <= 125)
                                    原付二種クラスならではの扱いやすさが魅力です。
                                @elseif($model->displacement && $model->displacement <= 250)
                                    車検不要の250ccクラスとして維持費の安さが魅力です。
                                @elseif($model->displacement && $model->displacement <= 400)
                                    普通二輪免許で乗れる400ccクラスのモデルです。
                                @elseif($model->displacement && $model->displacement > 400)
                                    大型バイクならではのパワフルな走りが魅力です。
                                @endif
                            </p>

                            @if(!empty($stats) && isset($stats['avg']) && $stats['count'] > 0)
                            <p>
                                中古車市場では現在{{ $stats['count'] }}台が流通しており、
                                平均価格は約{{ $stats['avg'] }}万円（{{ $stats['min'] }}万円〜{{ $stats['max'] }}万円）となっています。
                                @if(!empty($resale) && isset($resale['resale_min']) && $resale['data_count'] > 0)
                                    想定買取価格は{{ $resale['resale_min'] }}〜{{ $resale['resale_max'] }}万円です。
                                @endif
                            </p>
                            @endif

                            @if(isset($reviewStats) && $reviewStats->count > 0)
                            <p>
                                MotoHubに寄せられたオーナーレビューの平均評価は★{{ $reviewStats->avg_rating }}（{{ $reviewStats->count }}件）です。
                            </p>
                            @endif
                        </div>
                    </div>

                    {{-- カタログスペック情報 --}}
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <div class="flex items-center gap-2 mb-6">
                            <div class="p-2 bg-gray-800 rounded-lg text-white">
                                <i data-lucide="book-open" class="w-5 h-5"></i>
                            </div>
                            <h3 class="text-lg font-black text-gray-900">カタログスペック</h3>
                            <span class="hidden sm:inline-block text-[10px] font-bold text-gray-400 ml-2 border border-gray-200 px-2 py-0.5 rounded bg-gray-50">{{ $model->name }}</span>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-1">
                            @php
                            $specs = [
                                '型式' => $model->model_code,
                                '全長 / 全幅 / 全高' => ($model->length && $model->width && $model->height) ? "{$model->length}mm / {$model->width}mm / {$model->height}mm" : null,
                                'シート高' => $model->seat_height ? "{$model->seat_height}mm" : null,
                                '車両重量' => $model->weight ? "{$model->weight}kg" : null,
                                'エンジン種類' => $model->engine_type,
                                '総排気量' => $model->displacement ? "{$model->displacement}cc" : null,
                                '燃費' => $model->fuel_consumption ? "{$model->fuel_consumption}km/L" : null,
                                'タンク容量' => $model->tank_capacity ? "{$model->tank_capacity}L" : null,
                                '燃料供給方式' => $model->fuel_supply,
                                '最高出力' => $model->max_power,
                                '最大トルク' => $model->max_torque,
                                'フロントタイヤ' => $model->tire_size_front,
                                'リアタイヤ' => $model->tire_size_rear,
                                '前ブレーキ' => $model->brake_type_front,
                                '後ブレーキ' => $model->brake_type_rear,
                            ];
                            @endphp

                            @foreach(array_filter($specs) as $label => $value)
                                <div class="flex justify-between items-center py-3 border-b border-gray-50 last:border-0 sm:nth-last-child(-n+2):border-0">
                                    <span class="text-xs font-bold text-gray-500 whitespace-nowrap">{{ $label }}</span>
                                    <span class="text-sm font-black text-gray-800 text-right max-w-[60%] leading-tight">{{ $value }}</span>
                                </div>
                            @endforeach
                            
                            @if(empty(array_filter($specs)))
                                <div class="col-span-1 sm:col-span-2 text-center py-4 text-xs font-bold text-gray-400">
                                    スペック情報がまだ収集されていません。
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    {{-- 1. 買取相場・リセール情報 --}}
                    <div class="bg-white rounded-3xl shadow-lg p-6 sm:p-8 border border-gray-100">
                        <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                            <span class="bg-yellow-100 text-yellow-600 p-2 rounded-lg"><i data-lucide="coins" class="w-5 h-5"></i></span>
                            {{ $model->name }} の買取相場・リセールバリュー
                        </h2>

                        @if(!empty($resale) && isset($resale['resale_min']) && $resale['data_count'] > 0)
                            <div class="bg-gradient-to-r from-yellow-50 to-orange-50 rounded-2xl p-6 mb-8 border border-yellow-100">
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

                            <div class="space-y-4">
                                <p class="text-xs font-black text-gray-800 text-center mb-2">＼ 複数の業者で比較して高く売ろう ／</p>
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="flex flex-col">
                                        <div class="text-[10px] font-bold text-center text-blue-600 bg-blue-50 py-1 rounded-t-lg border-x border-t border-blue-100">
                                            カスタム車・改造車もOK！
                                        </div>
                                        <a href="https://px.a8.net/svt/ejp?a8mat=4AX6CG+5PEKHE+1BFI+61RIA" target="_blank" rel="nofollow" class="block w-full bg-gradient-to-br from-blue-500 to-blue-600 hover:from-blue-400 hover:to-blue-500 text-white font-black text-center py-4 rounded-b-xl shadow-md transition transform hover:-translate-y-1 flex items-center justify-center gap-2">
                                            <span>バイクワンで査定</span>
                                            <i data-lucide="external-link" class="w-4 h-4"></i>
                                        </a>
                                        <img border="0" width="1" height="1" src="https://www18.a8.net/0.gif?a8mat=4AX6CG+5PEKHE+1BFI+61RIA" alt="" class="hidden">
                                    </div>
                                    <div class="flex flex-col">
                                        <div class="text-[10px] font-bold text-center text-red-600 bg-red-50 py-1 rounded-t-lg border-x border-t border-red-100">
                                            旧車・ハーレー・大型車に強い！
                                        </div>
                                        <a href="https://px.a8.net/svt/ejp?a8mat=4AX6CG+5QLFOY+1T3W+62ENM" target="_blank" rel="nofollow" class="block w-full bg-gradient-to-br from-red-500 to-red-600 hover:from-red-400 hover:to-red-500 text-white font-black text-center py-4 rounded-b-xl shadow-md transition transform hover:-translate-y-1 flex items-center justify-center gap-2">
                                            <span>バイクBOONで査定</span>
                                            <i data-lucide="external-link" class="w-4 h-4"></i>
                                        </a>
                                        <img border="0" width="1" height="1" src="https://www18.a8.net/0.gif?a8mat=4AX6CG+5QLFOY+1T3W+62ENM" alt="" class="hidden">
                                    </div>
                                </div>
                                <p class="text-[10px] text-gray-400 text-center font-bold mt-2">提携: バイクワン / バイクBOON</p>
                            </div>
                        @else
                            <div class="text-center py-8 bg-gray-50 rounded-2xl border border-gray-100">
                                <i data-lucide="bar-chart-2" class="w-8 h-8 text-gray-300 mx-auto mb-2"></i>
                                <p class="text-sm text-gray-500 font-bold">データ不足のため、現在買取相場を算出できません。</p>
                            </div>
                        @endif
                    </div>

                    {{-- 2. 市場価格分析 --}}
                    <div class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100">
                        <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                            <span class="bg-blue-100 text-blue-600 p-2 rounded-lg"><i data-lucide="bar-chart-2" class="w-5 h-5"></i></span>
                            {{ $model->name }} 中古車価格の分布
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

                    {{-- 価格推移・買い時分析 --}}
                    <div class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-4">
                            <h2 class="text-xl font-black text-gray-900 flex items-center gap-2">
                                <span class="bg-orange-100 text-orange-600 p-2 rounded-lg"><i data-lucide="trending-up" class="w-5 h-5"></i></span>
                                {{ $model->name }} 価格推移・買い時予報
                            </h2>
                            @if(!empty($history['trend']))
                                <div class="px-4 py-2 rounded-full text-xs font-bold flex items-center gap-2
                                    {{ $history['trend']['status'] === 'down' ? 'bg-red-100 text-red-600' : ($history['trend']['status'] === 'up' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600') }}">
                                    @if($history['trend']['status'] === 'down') <i data-lucide="arrow-down-right" class="w-4 h-4"></i>
                                    @elseif($history['trend']['status'] === 'up') <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
                                    @else <i data-lucide="minus" class="w-4 h-4"></i>
                                    @endif
                                    {{ $history['trend']['message'] }}
                                </div>
                            @endif
                        </div>
                        <div class="relative h-64 w-full">
                            <canvas id="historyChart"></canvas>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-4 text-right">※MotoHub独自の過去データに基づく平均価格の推移です</p>
                    </div>

                    {{-- 3. ユーザーレビュー --}}
                    <div class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100" id="reviews">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
                            <h2 class="text-xl font-black text-gray-900 flex items-center gap-2">
                                <span class="bg-green-100 text-green-600 p-2 rounded-lg shrink-0"><i data-lucide="message-circle" class="w-5 h-5"></i></span>
                                <span>
                                    {{ $model->name }} オーナーレビュー
                                    <span class="text-sm text-gray-500 font-bold ml-1 inline-block">({{ $model->reviews->count() }}件)</span>
                                </span>
                            </h2>
                            <a href="#review-form" class="text-xs font-bold bg-black text-white px-4 py-3 sm:py-2 rounded-full hover:bg-gray-800 transition-colors inline-flex items-center justify-center w-full sm:w-auto">
                                <i data-lucide="pen-tool" class="w-3 h-3 mr-1"></i>投稿する
                            </a>
                        </div>

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
                                    <p class="text-xs text-gray-400 font-bold">by {{ $review->nickname }}</p>
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

                            @if ($errors->any())
                                <div class="mb-4 p-4 bg-red-50 text-red-600 text-sm font-bold rounded-xl border border-red-100">
                                    <ul class="list-disc list-inside">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form id="review-form-element" action="{{ route('bikes.model_detail.review', $model->id) }}" method="POST">
                                @csrf
                                <input type="hidden" name="recaptcha_token" id="recaptcha-token">
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
                                <button type="submit" class="w-full bg-black hover:bg-gray-800 text-white font-bold py-3 rounded-xl transition shadow-lg transform active:scale-95">
                                    投稿する
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- ★追加: FAQ（よくある質問） --}}
                    <div class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100">
                        <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                            <span class="bg-purple-100 text-purple-600 p-2 rounded-lg"><i data-lucide="help-circle" class="w-5 h-5"></i></span>
                            {{ $model->name }} よくある質問
                        </h2>

                        <div class="space-y-4">
                            {{-- Q1: 中古相場 --}}
                            <details class="group border border-gray-100 rounded-xl overflow-hidden">
                                <summary class="flex items-center justify-between px-5 py-4 cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors font-bold text-sm text-gray-800">
                                    <span>{{ $model->name }}の中古車相場はいくらですか？</span>
                                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 group-open:rotate-180 transition-transform"></i>
                                </summary>
                                <div class="px-5 py-4 text-sm text-gray-600 leading-relaxed">
                                    @if(!empty($stats) && isset($stats['avg']) && $stats['count'] > 0)
                                        {{ $model->name }}の中古車は現在{{ $stats['count'] }}台が市場に流通しています。
                                        価格帯は{{ $stats['min'] }}万円〜{{ $stats['max'] }}万円で、平均価格は約{{ $stats['avg'] }}万円です。
                                        年式や走行距離、車両の状態によって価格は大きく変動します。
                                    @else
                                        現在、{{ $model->name }}の中古車価格データが十分にありません。在庫が入荷され次第、相場情報が更新されます。
                                    @endif
                                </div>
                            </details>

                            {{-- Q2: 買取価格 --}}
                            <details class="group border border-gray-100 rounded-xl overflow-hidden">
                                <summary class="flex items-center justify-between px-5 py-4 cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors font-bold text-sm text-gray-800">
                                    <span>{{ $model->name }}の買取価格（売却価格）はいくらですか？</span>
                                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 group-open:rotate-180 transition-transform"></i>
                                </summary>
                                <div class="px-5 py-4 text-sm text-gray-600 leading-relaxed">
                                    @if(!empty($resale) && isset($resale['resale_min']) && $resale['data_count'] > 0)
                                        MotoHubの独自算出では、{{ $model->name }}の想定買取価格は{{ $resale['resale_min'] }}〜{{ $resale['resale_max'] }}万円です。
                                        ただし実際の買取額は車両の状態、走行距離、カスタムの有無、買取業者によって大きく異なります。
                                        複数の業者に査定を依頼して比較することをおすすめします。
                                    @else
                                        現在、{{ $model->name }}の買取相場データが十分にありません。買取業者に直接査定を依頼することをおすすめします。
                                    @endif
                                </div>
                            </details>

                            {{-- Q3: 維持費 --}}
                            <details class="group border border-gray-100 rounded-xl overflow-hidden">
                                <summary class="flex items-center justify-between px-5 py-4 cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors font-bold text-sm text-gray-800">
                                    <span>{{ $model->name }}の維持費はどれくらいですか？</span>
                                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 group-open:rotate-180 transition-transform"></i>
                                </summary>
                                <div class="px-5 py-4 text-sm text-gray-600 leading-relaxed">
                                    @if($model->displacement)
                                        @if($model->displacement <= 125)
                                            {{ $model->name }}は{{ $model->displacement }}ccのため、ファミリーバイク特約が使え、車検も不要です。年間の維持費は保険・税金・消耗品を含めて約3〜5万円程度が目安です。
                                        @elseif($model->displacement <= 250)
                                            {{ $model->name }}は{{ $model->displacement }}ccのため車検が不要で、維持費を抑えやすいクラスです。年間の維持費は保険・税金・消耗品を含めて約5〜10万円程度が目安です。
                                        @elseif($model->displacement <= 400)
                                            {{ $model->name }}は{{ $model->displacement }}ccのため、2年ごとに車検が必要です。年間の維持費は車検代・保険・税金・消耗品を含めて約10〜15万円程度が目安です。
                                        @else
                                            {{ $model->name }}は{{ $model->displacement }}ccの大型バイクのため、2年ごとに車検が必要です。年間の維持費は車検代・保険・税金・消耗品を含めて約12〜20万円程度が目安です。
                                        @endif
                                        @if($model->fuel_consumption)
                                            燃費は{{ $model->fuel_consumption }}km/Lです。
                                        @endif
                                    @else
                                        {{ $model->name }}の維持費は排気量クラスによって異なります。詳しくはスペック情報をご確認ください。
                                    @endif
                                </div>
                            </details>

                            {{-- Q4: オーナー評価 --}}
                            <details class="group border border-gray-100 rounded-xl overflow-hidden">
                                <summary class="flex items-center justify-between px-5 py-4 cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors font-bold text-sm text-gray-800">
                                    <span>{{ $model->name }}のオーナー評価・口コミは？</span>
                                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 group-open:rotate-180 transition-transform"></i>
                                </summary>
                                <div class="px-5 py-4 text-sm text-gray-600 leading-relaxed">
                                    @if(isset($reviewStats) && $reviewStats->count > 0)
                                        {{ $model->name }}のオーナーレビューは{{ $reviewStats->count }}件寄せられており、平均評価は★{{ $reviewStats->avg_rating }}です。
                                        <a href="#reviews" class="text-blue-600 hover:underline font-bold">レビュー一覧はこちら</a>からご覧いただけます。
                                    @else
                                        まだ{{ $model->name }}のオーナーレビューは投稿されていません。
                                        <a href="#review-form" class="text-blue-600 hover:underline font-bold">最初のレビューを投稿</a>してみませんか？
                                    @endif
                                </div>
                            </details>
                        </div>

                        {{-- FAQ構造化データ --}}
                        <script type="application/ld+json">
                        {
                            "@@context": "https://schema.org",
                            "@@type": "FAQPage",
                            "mainEntity": [
                                {
                                    "@@type": "Question",
                                    "name": "{{ $model->name }}の中古車相場はいくらですか？",
                                    "acceptedAnswer": {
                                        "@@type": "Answer",
                                        "text": "{{ !empty($stats) && isset($stats['avg']) && $stats['count'] > 0 ? "{$model->name}の中古車は現在{$stats['count']}台が流通。価格帯は{$stats['min']}万円〜{$stats['max']}万円で、平均価格は約{$stats['avg']}万円です。" : "現在データ収集中です。" }}"
                                    }
                                },
                                {
                                    "@@type": "Question",
                                    "name": "{{ $model->name }}の買取価格はいくらですか？",
                                    "acceptedAnswer": {
                                        "@@type": "Answer",
                                        "text": "{{ !empty($resale) && isset($resale['resale_min']) && $resale['data_count'] > 0 ? "想定買取価格は{$resale['resale_min']}〜{$resale['resale_max']}万円です。" : "現在データ収集中です。" }}"
                                    }
                                }
                            ]
                        }
                        </script>
                    </div>

                    {{-- ★追加: 関連車種 --}}
                    @if(isset($relatedModels) && $relatedModels->count() > 0)
                    <div class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100">
                        <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                            <span class="bg-teal-100 text-teal-600 p-2 rounded-lg"><i data-lucide="git-branch" class="w-5 h-5"></i></span>
                            {{ $model->manufacturer->name }}の他の車種
                        </h2>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            @foreach($relatedModels as $related)
                            <a href="{{ $related->seo_url }}" class="group block bg-gray-50 rounded-xl p-4 border border-gray-100 hover:border-blue-300 hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
                                <div class="aspect-[4/3] rounded-lg bg-gray-100 overflow-hidden mb-3">
                                    @if($related->image_url)
                                        <img src="{{ $related->image_url }}" alt="{{ $related->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" decoding="async">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                                            <i data-lucide="bike" class="w-6 h-6"></i>
                                        </div>
                                    @endif
                                </div>
                                <h3 class="text-xs font-black text-gray-800 group-hover:text-blue-600 transition-colors line-clamp-2 mb-1">{{ $related->name }}</h3>
                                @if($related->listings_count > 0)
                                <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">{{ $related->listings_count }}台</span>
                                @endif
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                </div>

                {{-- サイドバー --}}
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
                                {{ $model->name }} の在庫を検索する
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-layout>