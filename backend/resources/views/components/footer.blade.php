{{-- アドセンス審査を意識した、信頼性の高いフッターコンポーネント --}}
<footer class="bg-white border-t border-gray-100 pt-16 pb-12">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex flex-col items-center text-center">
            
            <!-- サイトロゴ & 紹介 -->
            <div class="mb-10 max-w-2xl">
                <div class="flex items-center justify-center gap-2 mb-4">
                    <i data-lucide="bike" class="w-6 h-6 text-black"></i>
                    <span class="text-xl font-black tracking-tighter">MotoHub</span>
                </div>
                <p class="text-xs text-gray-400 leading-relaxed font-medium">
                    MotoHub（モトハブ）は、日本中のバイク販売店から中古車・新車情報を集約し、<br class="hidden sm:block">
                    あなたに最適な一台を最速で見つけるためのバイク一括検索プラットフォームです。
                </p>
            </div>

            <!-- SEO内部リンク: 排気量から探す -->
            <div class="mb-10 w-full max-w-4xl">
                <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">排気量から探す</h3>
                <div class="flex flex-wrap justify-center gap-2">
                    @foreach([
                        '50' => '50cc以下',
                        '125' => '51〜125cc',
                        '250' => '126〜250cc',
                        '400' => '251〜400cc',
                        '750' => '401〜750cc',
                        'over750' => '751cc以上',
                    ] as $slug => $label)
                        <a href="{{ route('bikes.category_cc', ['slug' => $slug]) }}"
                           class="px-3 py-1.5 rounded-lg bg-gray-50 border border-gray-100 text-xs font-bold text-gray-600 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-600 transition-colors">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- SEO内部リンク: タイプから探す -->
            <div class="mb-10 w-full max-w-4xl">
                <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">タイプから探す</h3>
                <div class="flex flex-wrap justify-center gap-2">
                    @foreach([
                        'naked' => 'ネイキッド',
                        'scooter' => 'スクーター',
                        'american' => 'アメリカン',
                        'sport' => 'スポーツ/レプリカ',
                        'offroad' => 'オフロード',
                        'tourer' => 'ツアラー',
                        'classic' => 'クラシック',
                        'adventure' => 'アドベンチャー',
                        'street-fighter' => 'ストリートファイター',
                        'mini' => 'ミニバイク',
                    ] as $slug => $label)
                        <a href="{{ route('bikes.category_type', ['slug' => $slug]) }}"
                           class="px-3 py-1.5 rounded-lg bg-gray-50 border border-gray-100 text-xs font-bold text-gray-600 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-600 transition-colors">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- SEO内部リンク: 人気の車種 -->
            @if(isset($footerPopularBikes) && $footerPopularBikes->isNotEmpty())
            <div class="mb-10 w-full max-w-4xl">
                <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">人気の車種</h3>
                <div class="flex flex-wrap justify-center gap-2">
                    @foreach($footerPopularBikes as $bike)
                        <a href="{{ $bike->seo_url }}"
                           class="px-3 py-1.5 rounded-lg bg-gray-50 border border-gray-100 text-xs font-bold text-gray-600 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-600 transition-colors">
                            {{ $bike->manufacturer?->name }} {{ $bike->name }}
                        </a>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- SEO内部リンク: カテゴリから探す -->
            @if(isset($footerCategories) && $footerCategories->isNotEmpty())
            <div class="mb-10 w-full max-w-4xl">
                <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">カテゴリから探す</h3>
                <div class="flex flex-wrap justify-center gap-2">
                    @foreach($footerCategories as $category)
                        <a href="{{ route('bikes.search', ['category_id' => $category->id]) }}"
                           class="px-3 py-1.5 rounded-lg bg-gray-50 border border-gray-100 text-xs font-bold text-gray-600 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-600 transition-colors">
                            {{ $category->name }}
                        </a>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- リンクメニュー -->
            <nav class="mb-10">
                <ul class="flex flex-wrap justify-center gap-x-8 gap-y-4 text-xs font-bold text-gray-500 uppercase tracking-widest">
                    <li>
                        <a href="{{ route('pages.about') }}" class="footer-link">運営者情報</a>
                    </li>
                    <li>
                        <a href="{{ route('pages.contact') }}" class="footer-link">お問い合わせ</a>
                    </li>

                    {{-- スマートフォン表示時のみ、ここで強制改行を入れる --}}
                    <div class="basis-full h-0 sm:hidden"></div>

                    <li>
                        <a href="{{ route('pages.privacy-policy') }}" class="footer-link">プライバシーポリシー</a>
                    </li>
                    <li>
                        <a href="{{ route('pages.terms') }}" class="footer-link">利用規約・免責事項</a>
                    </li>

                    <div class="basis-full h-0 sm:hidden"></div>
                    
                    <li>
                        <a href="{{ route('hoken') }}" class="footer-link">バイク保険・維持費</a>
                    </li>
                    <li>
                        <a href="{{ route('theft') }}" class="footer-link">バイクの盗難データ（全国）</a>
                    </li>
                    <li>
                        <a href="{{ route('pages.widget') }}" class="footer-link">相場ウィジェット</a>
                    </li>
                    <li>
                        <a href="{{ route('data') }}" class="footer-link">データ提供</a>
                    </li>
                    <li>
                        <a href="{{ route('riders.map') }}" class="footer-link">ライダーズマップ</a>
                    </li>
                    <li>
                        <a href="{{ route('parking.index') }}" class="footer-link">バイク駐車場マップ</a>
                    </li>
                    <li>
                        <a href="{{ route('parking.area.index') }}" class="footer-link">エリアから駐車場を探す</a>
                    </li>
                    <li>
                        <a href="{{ route('parking.station.index') }}" class="footer-link">駅から駐車場を探す</a>
                    </li>
                    <li>
                        <a href="{{ route('parking.reviews') }}" class="footer-link">駐車場の口コミ（新着）</a>
                    </li>
                    <li>
                        <a href="{{ route('shops.area.index') }}" class="footer-link">エリアからバイクショップを探す</a>
                    </li>
                    <li>
                        <a href="{{ route('shops.reviews') }}" class="footer-link">みんなの口コミ（新着）</a>
                    </li>
                    <li>
                        <a href="{{ route('bikes.bargains') }}" class="footer-link">お買い得バイク</a>
                    </li>
                    <li>
                        <a href="{{ route('bikes.overseas') }}" class="footer-link">輸入バイク中古車</a>
                    </li>
                    <li>
                        <a href="{{ route('bikes.model_compare_hub') }}" class="footer-link">バイク車種を比較する</a>
                    </li>
                    <li>
                        <a href="{{ route('parts.index') }}" class="footer-link">バイクパーツ検索</a>
                    </li>
                    <li>
                        <a href="{{ route('trouble.index') }}" class="footer-link">バイクトラブル診断</a>
                    </li>
                    <li>
                        <a href="{{ route('ranking.index') }}" class="footer-link">売れ筋ランキング</a>
                    </li>
                    <li>
                        <a href="{{ route('garage.public.index') }}" class="footer-link">愛車ガレージ</a>
                    </li>
                </ul>
            </nav>

            <!-- メディア掲載実績 -->
            @php
                $mediaFeatures = [
                    [
                        'date' => '2026.06.28',
                        'name' => 'PRESSNOW プレスナウ',
                        'url'  => 'https://pressnow.jp/2026/06/28/motohub-4/',
                    ],
                    [
                        'date' => '2026.06.27',
                        'name' => 'PRESSNOW プレスナウ',
                        'url'  => 'https://pressnow.jp/2026/06/27/motohub-3/',
                    ],
                    [
                        'date' => '2026.05.20',
                        'name' => 'PRESSNOW プレスナウ',
                        'url'  => 'https://pressnow.jp/2026/05/20/motohub/',
                    ],
                    [
                        'date' => '2026.05.19',
                        'name' => 'valuepress',
                        'url'  => 'https://www.value-press.com/pressrelease/374582',
                    ],
                ];
            @endphp
            <div class="mb-10 w-full max-w-4xl">
                <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">メディア掲載実績</h3>
                <div class="flex flex-col items-center gap-2">
                    @foreach($mediaFeatures as $media)
                        <div class="text-[11px]">
                            <span class="text-gray-500">{{ $media['date'] }}</span>
                            <span class="text-gray-300 mx-1">&mdash;</span>
                            <a href="{{ $media['url'] }}"
                               target="_blank"
                               rel="noopener"
                               class="text-gray-400 hover:text-blue-500 transition-colors">
                                {{ $media['name'] }} に掲載されました
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- データ出典 -->
            <div class="pt-6 border-t border-gray-200/50 w-full">
                <p class="text-[10px] text-gray-400 leading-relaxed">駅データ: 国土数値情報（鉄道データ）国土交通省</p>
            </div>

            <!-- コピーライト -->
            <div class="pt-4 w-full">
                <p class="text-[10px] text-gray-300 font-bold tracking-widest uppercase">
                    &copy; {{ date('Y') }} MotoHub Project - All Rights Reserved.
                </p>
            </div>
        </div>
    </div>
</footer>