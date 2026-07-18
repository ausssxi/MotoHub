<?php

declare(strict_types=1);

namespace App\Repositories\Shop;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

final class ShopRepository
{
    /**
     * 「同一座標を N 店以上が共有する」＝県庁/市の代表点フォールバックの疑い（誤ジオコーディング）。
     * 恒久修正（住所からの再ジオコーディング）が済むまでの間、これらの座標に乗る店を地図から非表示にする。
     * ★暫定対応：本命バッチ（住所再ジオコーディング）完了後はこの閾値検出ごと撤去してよい。
     */
    private const COARSE_COORD_MIN_SHOPS = 50;

    /**
     * IDで店舗を取得 (既存メソッド)
     */
    public function find(int $id): ?Shop
    {
        return Shop::find($id);
    }

    /**
     * IDで店舗を取得（見つからない場合は404エラー）
     * 詳細ページなどで使用
     */
    public function findOrFail(int $id): Shop
    {
        return Shop::findOrFail($id);
    }

    /**
     * 指定された矩形範囲（緯度経度）に含まれる店舗を取得
     * 地図検索で使用
     */
    public function findInBounds(float $swLat, float $swLng, float $neLat, float $neLng, int $limit = 100): Collection
    {
        // user店(source='user',座標あり)も scraper店と同じくそのまま含まれる（source絞り込みなし）。
        // 座標NULLの店は whereNotNull で除外（既存挙動）。
        // shop_type / source はフェーズ2のピン色分け用にペイロードへ含める（UIは今回スコープ外）。
        $query = Shop::query()
            // service_tags はメーカー正規店バッジ判定用（クライアントへは送らず getShopsInArea で hidden 化）。
            ->select(['id', 'name', 'address', 'latitude', 'longitude', 'prefecture', 'shop_type', 'source', 'service_tags'])
            ->whereNotNull('latitude')
            ->whereBetween('latitude', [$swLat, $neLat])
            ->whereBetween('longitude', [$swLng, $neLng])
            ->withCount(['listings' => function ($q) {
                $q->where('is_sold_out', false);
            }]);

        // 【暫定】県庁/市の代表点に団子表示される誤ジオコーディング店を非表示（同一座標≥N店）。
        // NOT (lat=X AND lng=Y) を各代表点ぶん AND する（＝厳密な小数一致で除外）。
        foreach ($this->coarseCoordinates() as $c) {
            $query->where(function ($w) use ($c) {
                $w->where('latitude', '!=', $c->latitude)
                    ->orWhere('longitude', '!=', $c->longitude);
            });
        }

        return $query->limit($limit)->get();
    }

    /**
     * 誤ジオコーディングの疑いが濃い「同一座標を N 店以上が共有する」代表点座標の一覧（1時間キャッシュ）。
     *
     * @return Collection<int, Shop>
     */
    private function coarseCoordinates(): Collection
    {
        return Cache::remember('shops:coarse_coords_v1', 3600, function () {
            return Shop::query()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->groupBy('latitude', 'longitude')
                ->havingRaw('COUNT(*) >= ?', [self::COARSE_COORD_MIN_SHOPS])
                ->get(['latitude', 'longitude']);
        });
    }
}
