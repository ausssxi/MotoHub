<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\StoreShopAcceptanceReportRequest;
use App\Mail\ShopAcceptanceReportSubmitted;
use App\Models\BikeModel;
use App\Models\BlogPost;
use App\Models\Listing;
use App\Models\Shop;
use App\Models\ShopAcceptanceReport;
use App\Services\Shop\ShopAcceptanceService;
use App\Services\Shop\ShopService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ShopController extends Controller
{
    public function __construct(
        private readonly ShopService $shopService,
        private readonly ShopAcceptanceService $acceptanceService,
    ) {}

    /**
     * 店舗詳細ページ
     */
    public function show(int $id): View
    {
        // サービスからデータ（shop, listings）を一括取得
        $data = $this->shopService->getShopDetailWithListings($id);

        // ユーザー投稿の受け入れ情報（承認済み集計）
        $data['acceptanceSummary'] = $this->acceptanceService->getApprovedSummary($id);

        // 販売実績データ
        $data['salesStats'] = $this->getShopSalesStats($id);

        // チェーン店判定（在庫一括管理の案内用）
        $data['chainInfo'] = null;
        $shop = $data['shop'];
        foreach (config('bike.chains', []) as $slug => $chain) {
            if (str_contains($shop->name, $chain['pattern'])) {
                $mainShop = \App\Models\Shop::where('name', 'like', "%{$chain['pattern']}%")
                    ->withCount(['listings' => fn ($q) => $q->where('is_sold_out', 0)])
                    ->orderByDesc('listings_count')
                    ->first();
                if ($mainShop && $mainShop->id !== $shop->id && $mainShop->listings_count > 0) {
                    $data['chainInfo'] = [
                        'name' => $chain['name'],
                        'slug' => $slug,
                        'main_shop_id' => $mainShop->id,
                        'stock' => $mainShop->listings_count,
                    ];
                }
                break;
            }
        }

        return view('shops.show', $data);
    }

    private function getShopSalesStats(int $shopId): ?array
    {
        return Cache::remember("shop_sales_stats_{$shopId}", 3600, function () use ($shopId) {
            $threeMonthsAgo = Carbon::now()->subMonths(3);

            $totalSold = Listing::where('shop_id', $shopId)
                ->where('is_sold_out', true)
                ->where('updated_at', '>=', $threeMonthsAgo)
                ->count();

            if ($totalSold === 0) {
                return null;
            }

            // 販売推移（過去6ヶ月）
            $monthlySales = [];
            for ($i = 5; $i >= 0; $i--) {
                $ms = Carbon::now()->subMonths($i)->startOfMonth();
                $me = Carbon::now()->subMonths($i)->endOfMonth();
                $monthlySales[] = [
                    'label' => $ms->format('n月'),
                    'count' => Listing::where('shop_id', $shopId)
                        ->where('is_sold_out', true)
                        ->whereBetween('updated_at', [$ms, $me])
                        ->count(),
                ];
            }

            // 人気車種TOP5
            $topModels = Listing::where('shop_id', $shopId)
                ->where('is_sold_out', true)
                ->where('updated_at', '>=', $threeMonthsAgo)
                ->whereNotNull('bike_model_id')
                ->select('bike_model_id', DB::raw('COUNT(*) as sold_count'))
                ->groupBy('bike_model_id')
                ->orderByDesc('sold_count')
                ->limit(5)
                ->get();

            $models = BikeModel::with('manufacturer')
                ->whereIn('id', $topModels->pluck('bike_model_id'))
                ->get()->keyBy('id');

            $topModelsList = $topModels->map(function ($item) use ($models) {
                $m = $models->get($item->bike_model_id);

                return [
                    'bike_model_id' => $item->bike_model_id,
                    'name' => $m->name ?? '不明',
                    'manufacturer' => $m->manufacturer->name ?? '',
                    'seo_url' => $m?->seo_url,
                    'sold_count' => $item->sold_count,
                ];
            });

            // 平均在庫日数
            $avgDays = Listing::where('shop_id', $shopId)
                ->where('is_sold_out', true)
                ->where('updated_at', '>=', $threeMonthsAgo)
                ->selectRaw('AVG(DATEDIFF(updated_at, created_at)) as avg_days')
                ->value('avg_days');

            return [
                'totalSold' => $totalSold,
                'monthlySales' => $monthlySales,
                'topModels' => $topModelsList,
                'avgDays' => (int) round((float) ($avgDays ?? 0)),
            ];
        });
    }

    /**
     * 訪問済みカウントをインクリメント
     */
    public function visited(Shop $shop): JsonResponse
    {
        $shop->increment('visited_count');

        return response()->json(['count' => $shop->visited_count]);
    }

    /**
     * 店舗の受け入れ情報をユーザー投稿として受け付ける（承認待ち）。
     * 匿名可。IPはハッシュ化して保存し、生IPは残さない。
     */
    public function submitAcceptanceReport(Shop $shop, StoreShopAcceptanceReportRequest $request): RedirectResponse
    {
        // === 表示名の解決（Reviewパターン踏襲・公開ハンドルのみ使用・User->name は絶対に使わない）===
        $user = $request->user();
        $userId = $user?->id;

        if ($user) {
            $handle = $user->review_display_name;
            if (empty($handle)) {
                // ログイン初回: 公開ハンドルを検証して保存（以降固定・タグ除去）
                $handle = trim(strip_tags((string) $request->input('submitter_name')));
                if ($handle === '' || mb_strlen($handle) > 30) {
                    return back()
                        ->withErrors(['submitter_name' => '公開表示名を1〜30文字で入力してください。'])
                        ->withInput();
                }
                $user->review_display_name = $handle;
                $user->save();
            }
            // 表示名は公開ハンドルのスナップショット（本名 name は入れない）
            $submitterName = $handle;
        } else {
            $submitterName = trim(strip_tags((string) $request->input('submitter_name'))) ?: '名無しライダー';
        }

        // コメント（主観・一言）は即反映（comment_approved=true）。事実系フラグは従来どおり
        // is_approved=false（承認待ち）。ただし新規ユーザー店は誤情報防止でコメントも承認へ。
        $comment = trim((string) $request->input('comment')) ?: null;
        $commentApproved = $comment !== null && ! $shop->isNewUserShop();

        $report = ShopAcceptanceReport::create([
            'shop_id' => $shop->id,
            'accepts_other_store' => $request->boolean('accepts_other_store'),
            'accepts_bring_in' => $request->boolean('accepts_bring_in'),
            'pickup_service' => $request->boolean('pickup_service'),
            'walk_in_ok' => $request->boolean('walk_in_ok'),
            'comment' => $comment,
            'submitter_name' => $submitterName,
            'is_approved' => false,
            'comment_approved' => $commentApproved,
            'user_id' => $userId,
            // 生IPは保存しない。app.key でソルトしたsha256のみ（重複/スパム検知用）。
            'submitter_ip_hash' => hash('sha256', $request->ip().'|'.config('app.key')),
        ]);

        // 管理者へ承認待ち通知（キュー）。失敗しても投稿自体は成功扱い。
        try {
            Mail::to(config('app.contact_admin_email'))->send(new ShopAcceptanceReportSubmitted($report));
        } catch (\Throwable $e) {
            report($e);
        }

        // 即反映コメントは掲載済み、そうでなければ承認待ちの文言を出し分ける。
        return redirect()->route('shops.show', $shop)
            ->with('acceptance_success', $commentApproved ? 'instant' : '1');
    }

    /**
     * ショップマップページ
     */
    public function map(): View
    {
        return view('shops.map');
    }

    /**
     * チェーン別ショップまとめページ
     */
    public function chainShow(string $chainSlug): View
    {
        $chains = config('bike.chains');
        if (! isset($chains[$chainSlug])) {
            abort(404);
        }

        $chain = $chains[$chainSlug];
        $shops = Shop::where('name', 'like', "%{$chain['pattern']}%")
            ->withCount(['listings' => fn ($q) => $q->where('is_sold_out', 0)])
            ->orderByDesc('listings_count')
            ->get();

        $totalStock = $shops->sum('listings_count');

        $mainShop = $shops->sortByDesc('listings_count')->first();
        $mainShopStock = $mainShop?->listings_count ?? 0;

        // 解説記事（公開済みのみ）。config の guide_slug が設定されたチェーンだけ表示される。
        $guideArticle = ! empty($chain['guide_slug'])
            ? BlogPost::published()->where('slug', $chain['guide_slug'])->first(['id', 'slug', 'title'])
            : null;

        $crossLinks = [
            ['label' => '中古バイク検索', 'url' => route('bikes.search'), 'icon' => 'search', 'description' => '全国の在庫を検索'],
            ['label' => '車種カタログ', 'url' => route('bikes.models'), 'icon' => 'book-open', 'description' => '車種の相場を確認'],
            ['label' => 'ショップマップ', 'url' => route('shops.map'), 'icon' => 'store', 'description' => 'バイクショップを探す'],
        ];

        return view('shops.chain', compact('chain', 'chainSlug', 'shops', 'totalStock', 'mainShop', 'mainShopStock', 'crossLinks', 'guideArticle'));
    }

    /**
     * 地図用データ取得API
     */
    public function area(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ne_lat' => 'required|numeric',
            'ne_lng' => 'required|numeric',
            'sw_lat' => 'required|numeric',
            'sw_lng' => 'required|numeric',
        ]);

        $shops = $this->shopService->getShopsInArea($validated);

        return response()->json($shops);
    }
}
