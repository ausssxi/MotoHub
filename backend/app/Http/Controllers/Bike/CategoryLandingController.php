<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use App\Http\Resources\Bike\ListingResource;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class CategoryLandingController extends Controller
{
    // kango = 人が検索する口語（原付/中型/大型 等）。title/h1/meta にレンジ表記と併記して
    // 「原付バイク 中古」「中型バイク 中古」等のクエリと語彙一致させる（穴①）。
    private const CC_MAP = [
        '50' => ['min' => 0, 'max' => 50, 'kango' => '原付', 'label' => '50cc以下', 'description' => '原付免許で乗れる手軽なバイク。通勤・通学の足として最適で、維持費も最も安いクラスです。燃費性能に優れ、駐輪場にも停めやすいコンパクトさが魅力。'],
        '125' => ['min' => 51, 'max' => 125, 'kango' => '原付二種', 'label' => '51〜125cc', 'description' => '小型二輪免許で乗れる原付二種クラス。二段階右折不要・二人乗り可能で、通勤から軽いツーリングまで幅広く活躍。ファミリーバイク特約で保険料も抑えられます。'],
        '250' => ['min' => 126, 'max' => 250, 'kango' => '軽二輪', 'label' => '126〜250cc', 'description' => '車検不要で維持費が安く、高速道路も走行可能。ツーリングから街乗りまでバランスの良いクラスで、初めての中型バイクとしても人気です。'],
        '400' => ['min' => 251, 'max' => 400, 'kango' => '中型', 'label' => '251〜400cc', 'description' => '普通二輪免許で乗れる最大排気量クラス。十分なパワーと扱いやすさを両立し、長距離ツーリングも快適。教習所で乗るバイクのクラスでもあり、親しみやすい排気量帯です。'],
        '750' => ['min' => 401, 'max' => 750, 'kango' => 'ナナハン', 'label' => '401〜750cc', 'description' => '大型二輪免許が必要なミドルクラス。余裕のあるパワーで高速巡航が楽になり、ワインディングも存分に楽しめます。大型の中では比較的軽量で取り回しやすいモデルが多いのが特徴。'],
        'over750' => ['min' => 751, 'max' => 99999, 'kango' => '大型', 'label' => '751cc以上', 'description' => '圧倒的なパワーとトルクを誇るリッタークラス。長距離ツーリングの快適性、ワインディングでのダイナミックな走り、所有欲を満たすプレミアム感が魅力のフラッグシップ排気量帯です。'],
    ];

    private const TYPE_MAP = [
        'naked' => ['ids' => [4], 'label' => 'ネイキッド', 'description' => 'カウルを持たないスタンダードなスタイル。軽快なハンドリングと整備のしやすさが特徴で、街乗りからツーリングまで万能に使えます。初心者からベテランまで幅広い層に支持されるジャンルです。'],
        'scooter' => ['ids' => [2, 3], 'label' => 'スクーター', 'description' => 'AT操作で手軽に乗れるスクーター。メットインスペースや足元フラットの利便性が魅力。通勤・通学の日常使いから、ビッグスクーターでのロングツーリングまで幅広く対応します。'],
        'american' => ['ids' => [10], 'label' => 'アメリカン/クルーザー', 'description' => 'ロー＆ロングのスタイルで、ゆったりとしたライディングポジションが特徴。低速トルクが豊かで、長距離クルージングに最適。カスタムの自由度が高いのも魅力です。'],
        'sport' => ['ids' => [8], 'label' => 'スポーツ/レプリカ', 'description' => 'サーキット由来の高性能エンジンとエアロダイナミクスを追求したスタイル。前傾姿勢のポジションでスポーティな走りを楽しめます。最新の電子制御技術も充実しています。'],
        'offroad' => ['ids' => [11], 'label' => 'オフロード/モタード', 'description' => '未舗装路やダートを走破できるサスペンションと軽量ボディが特徴。林道ツーリングやエンデューロ、モタード仕様での峠走行など、冒険心をくすぐるジャンルです。'],
        'tourer' => ['ids' => [6], 'label' => 'ツアラー', 'description' => '長距離走行に特化した快適装備が充実。大型カウル、パニアケース、快適なシートで何百キロも疲れ知らず。二人乗りでのロングツーリングにも最適なジャンルです。'],
        'classic' => ['ids' => [21], 'label' => 'クラシック/レトロ', 'description' => '懐かしいデザインを現代の技術で再現したネオクラシックスタイル。見た目の美しさと現代の信頼性を両立し、カフェレーサーやボバーなど多彩なカスタム文化があります。'],
        'adventure' => ['ids' => [14], 'label' => 'アドベンチャー', 'description' => 'オンロードの快適性とオフロードの走破性を兼ね備えたデュアルパーパスモデル。積載性が高く、日本一周などのロングツーリングから林道探索まで、あらゆる旅に対応します。'],
        'street-fighter' => ['ids' => [20], 'label' => 'ストリートファイター', 'description' => 'スーパースポーツの高性能エンジンをネイキッドボディに搭載した攻撃的なスタイル。アップライトなポジションで街中でもサーキットでも戦闘力を発揮します。'],
        'mini' => ['ids' => [1], 'label' => 'ミニバイク', 'description' => 'コンパクトな車体で気軽に楽しめるミニバイク。小さくても本格的な走りを楽しめるモデルが多く、セカンドバイクやサーキット遊びの入門としても人気です。'],
    ];

    public function byDisplacement(string $slug): View
    {
        $config = self::CC_MAP[$slug];

        $baseQuery = Listing::active()
            ->displacementBetween($config['min'], $config['max']);

        $searchUrl = route('bikes.search', ['cc_min' => $config['min'], 'cc_max' => $config['max']]);

        // 口語（レンジ）併記でクエリ語彙一致（例「原付（50cc以下）」）。h1/meta/パンくずも同じ label を使う。
        $label = $config['kango'] . '（' . $config['label'] . '）';

        return $this->buildLandingData(
            $baseQuery,
            $label . 'バイク中古車一覧',
            $label,
            $config['description'],
            'cc',
            $slug,
            $searchUrl
        );
    }

    public function byType(string $slug): View
    {
        $config = self::TYPE_MAP[$slug] ?? abort(404);

        $baseQuery = Listing::active()
            ->whereIn('listings.category_id', $config['ids']);

        $searchUrl = route('bikes.search', ['category' => $slug]);

        return $this->buildLandingData(
            $baseQuery,
            $config['label'] . 'の中古バイク一覧',
            $config['label'],
            $config['description'],
            'type',
            $slug,
            $searchUrl
        );
    }

    private function buildLandingData(Builder $baseQuery, string $title, string $label, string $description, string $mode, string $slug, string $searchUrl): View
    {
        // v2: 最新入庫を ListingResource 変換（生モデル渡しでカードが空になる不具合を修正）＋ItemList用データ＋件数拡大
        $cacheKey = "category_landing:v2:{$mode}:{$slug}";

        $data = Cache::remember($cacheKey, 3600, function () use ($baseQuery) {
            $kpi = (clone $baseQuery)
                ->whereNotNull('total_price')
                ->where('total_price', '>', 0)
                ->selectRaw('COUNT(*) as total_count, AVG(total_price) as avg_price, MIN(total_price) as min_price, MAX(total_price) as max_price')
                ->first();

            $totalCount = (int) ($kpi->total_count ?? 0);
            $avgPrice = $totalCount > 0 ? round(($kpi->avg_price ?? 0) / 10000, 1) : null;
            $minPrice = $totalCount > 0 ? round(($kpi->min_price ?? 0) / 10000, 1) : null;
            $maxPrice = $totalCount > 0 ? round(($kpi->max_price ?? 0) / 10000, 1) : null;

            // 人気車種TOP10
            $topModels = (clone $baseQuery)
                ->whereNotNull('bike_model_id')
                ->select('bike_model_id', DB::raw('COUNT(*) as cnt'), DB::raw('AVG(total_price) as avg_price'))
                ->groupBy('bike_model_id')
                ->orderByDesc('cnt')
                ->limit(10)
                ->get()
                ->map(function ($row) {
                    $model = \App\Models\BikeModel::with('manufacturer')->find($row->bike_model_id);
                    return [
                        'name' => ($model?->manufacturer?->name ? $model->manufacturer->name . ' ' : '') . ($model?->name ?? '不明'),
                        'count' => (int) $row->cnt,
                        'avg_price' => $row->avg_price > 0 ? round($row->avg_price / 10000, 1) : null,
                        'model_slug' => $model?->slug,
                        'mfr_slug' => $model?->manufacturer?->slug,
                    ];
                });

            // 最新入庫（在庫深度UP: 10→24）。ListingResource が使う関係を eager load（N+1防御）。
            $latestModels = (clone $baseQuery)
                ->with(['shop', 'site', 'bikeModel.manufacturer', 'bikeModel.categoryData', 'bikeModel.nationalPriceStat', 'tags'])
                ->orderByDesc('created_at')
                ->limit(24)
                ->get();

            // ★カード表示は ListingResource 変換（他ページと同形式）。生モデルだと maker/name/画像/価格が空になる。
            $latestListings = collect(ListingResource::collection($latestModels)->resolve());

            // ItemList(Product/Offer) 構造化データ用（価格は円・schema 準拠）。
            $itemList = $latestModels->map(fn (Listing $l) => [
                'name' => $l->title ?: ($l->bikeModel?->name ?? '中古バイク'),
                'url' => route('bikes.show', $l->id),
                'image' => (is_array($l->image_urls) && ! empty($l->image_urls)) ? $l->image_urls[0] : null,
                'price' => $l->total_price ? (int) $l->total_price : null,
            ])->values()->all();

            return [
                'kpi' => [
                    'total_count' => $totalCount,
                    'avg_price' => $avgPrice,
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice,
                ],
                'top_models' => $topModels,
                'latest_listings' => $latestListings,
                'item_list' => $itemList,
            ];
        });

        // 関連リンク
        $relatedLinks = $this->buildRelatedLinks($mode, $slug);

        return view('bikes.category-landing', [
            'pageInfo' => [
                'title' => $title,
                'label' => $label,
                'description' => $description,
            ],
            'kpi' => $data['kpi'],
            'topModels' => $data['top_models'],
            'latestListings' => $data['latest_listings'],
            'itemList' => $data['item_list'] ?? [],
            'relatedLinks' => $relatedLinks,
            'mode' => $mode,
            'slug' => $slug,
            'searchUrl' => $searchUrl,
        ]);
    }

    private function buildRelatedLinks(string $mode, string $currentSlug): array
    {
        $ccLinks = [];
        foreach (self::CC_MAP as $s => $config) {
            if ($s === $currentSlug && $mode === 'cc') continue;
            $ccLinks[] = [
                'url' => route('bikes.category_cc', ['slug' => $s]),
                'label' => $config['label'],
            ];
        }

        $typeLinks = [];
        foreach (self::TYPE_MAP as $s => $config) {
            if ($s === $currentSlug && $mode === 'type') continue;
            $typeLinks[] = [
                'url' => route('bikes.category_type', ['slug' => $s]),
                'label' => $config['label'],
            ];
        }

        return [
            'cc' => $ccLinks,
            'type' => $typeLinks,
        ];
    }
}
