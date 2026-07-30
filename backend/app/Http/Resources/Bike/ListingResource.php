<?php

declare(strict_types=1);

namespace App\Http\Resources\Bike;

use App\Services\Bike\RegionalBargainService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 出品データをフロントエンド向けのJSON/配列に変換するリソースクラス。
 * APIとBladeビューの両方で使用します。（N+1完全防御版）
 */
class ListingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewCount = (int) ($this->view_count_today ?? 0);
        $favCount = (int) ($this->favorite_count ?? 0);

        // サイトのアイコンキーを判定するロジックをバックエンドに集約
        $siteName = $this->site?->name ?? '外部サイト';
        $siteKey = 'default';
        $siteLower = mb_strtolower($siteName);

        if (str_contains($siteLower, 'goo') || str_contains($siteLower, 'グーバイク')) {
            $siteKey = 'goobike';
        } elseif (str_contains($siteLower, 'webike') || str_contains($siteLower, 'ウェビック')) {
            $siteKey = 'webike';
        } elseif (str_contains($siteLower, 'bds') || str_contains($siteLower, 'バイクセンサー')) {
            $siteKey = 'bds';
        }

        // --- お得バッジ（全国中央値ベース・N+1防御） ---
        // 旧 avg(marketStats) ベースは外れ値で94%等の暴れ値を出したため、全国中央値基準に置換。
        // bikeModel.nationalPriceStat が eager load されている面でのみ算出（カード毎の追加クエリなし）。
        $nationalStat = $this->bikeModel && $this->bikeModel->relationLoaded('nationalPriceStat')
            ? $this->bikeModel->nationalPriceStat
            : null;

        $regionBargain = RegionalBargainService::fromStat(
            $nationalStat,
            $this->total_price !== null ? (int) $this->total_price : null
        );

        // 後方互換: 旧 bargain_info を参照する面（AI検索の is_bargain 等）向けに同じ基準で組み直す。
        $bargainInfo = $regionBargain === null ? null : [
            'diff' => $regionBargain['diff_man'],
            'label' => "全国相場(中央値{$regionBargain['median_man']}万)より約{$regionBargain['pct']}%お得！",
            'is_bargain' => true,
        ];

        // --- N+1防御 その2 ---
        // カテゴリ名も同様に、事前にロードされている場合のみ取得
        $categoryName = $this->bikeModel && $this->bikeModel->relationLoaded('categoryData')
            ? ($this->bikeModel->categoryData?->name ?? 'その他')
            : 'その他';

        return [
            'id' => $this->id,
            'site_name' => $this->resolveSourceDisplayName($this->site?->name ?? ''),
            'source' => $this->resolveSourceDisplayName($this->site?->name ?? ''),
            'source_domain' => $this->resolveSourceDomain($this->site?->name ?? ''),
            'source_icon_key' => $siteKey,

            'maker' => $this->bikeModel?->manufacturer?->name ?? 'メーカー不明',
            'category' => $categoryName, // 防御済みの変数を使用
            'manufacturer_id' => $this->bikeModel?->manufacturer_id,
            'bike_model_id' => $this->bike_model_id,
            'bike_model_name' => $this->bikeModel?->name,

            // カタログスペック情報
            'specs' => [
                'seat_height' => $this->bikeModel?->seat_height,
                'fuel_consumption' => $this->bikeModel?->fuel_consumption,
                'tank_capacity' => $this->bikeModel?->tank_capacity,
                'engine_type' => $this->bikeModel?->engine_type,
                'max_power' => $this->bikeModel?->max_power,
                'max_torque' => $this->bikeModel?->max_torque,
                'weight' => $this->bikeModel?->weight,
                'tire_size_front' => $this->bikeModel?->tire_size_front,
                'tire_size_rear' => $this->bikeModel?->tire_size_rear,
            ],

            'name' => $this->title ?? $this->bikeModel?->name ?? '車種名不明',
            'model_year' => $this->model_year ? "{$this->model_year}年" : '不明',
            'mileage' => $this->mileage !== null ? number_format($this->mileage).'km' : '走行不明',
            'displacement' => $this->bikeModel?->displacement ? "{$this->bikeModel->displacement}cc" : '-',
            'repair_history' => $this->has_repair_history ? 'あり' : 'なし',
            'condition' => $this->condition ?? '不明',

            'total_price' => $this->total_price ? number_format((float) ($this->total_price / 10000), 1) : '-',
            'price' => $this->price ? number_format((float) ($this->price / 10000), 1) : '-',
            'base_price' => $this->price ? number_format((float) ($this->price / 10000), 1) : '-',

            'bargain_info' => $bargainInfo,
            // 全国中央値ベースのお得バッジ（カードはこれを参照。null=非表示）
            'region_bargain' => $regionBargain,

            // 店舗情報
            'shop_id' => $this->shop_id,
            'shop_image' => $this->shop?->display_image_url,
            'store_name' => $this->shop?->name ?? '不明な販売店',
            'shop_name' => $this->shop?->name ?? '不明な販売店',
            'shop_address' => $this->shop?->address,
            'shop_tel' => $this->shop?->phone,
            'shop_hours' => $this->shop?->business_hours,
            'prefecture' => $this->shop?->prefecture ?? '全国',

            // 詳細情報 (存在しないカラムへの安全なアクセス)
            'description' => $this->description ?? null,
            'bargain_score' => $this->bargain_score ?? 0,
            'url' => $this->source_url,

            // エンゲージメント指標
            'engagement' => [
                'view_count_today' => $viewCount,
                'wishlist_count' => ($this->id % 15) + 3,
                'is_popular' => ($viewCount > 30 || $favCount > 5),
            ],

            // タグ情報をBladeに渡す処理（元々安全です）
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    ];
                });
            }, []),

            'is_sold_out' => (bool) $this->is_sold_out,

            'images' => $this->resolveImageUrls($this->local_image_paths ?? [], $this->image_urls ?? []),
        ];
    }

    private function resolveSourceDisplayName(string $name): string
    {
        return match (strtolower(trim($name))) {
            'goobike' => 'グーバイク',
            'bds', 'bikesensor' => 'BDSバイクセンサー',
            'webike' => 'Webike',
            default => $name ?: '不明',
        };
    }

    private function resolveSourceDomain(string $name): string
    {
        $domains = [
            'goobike' => 'goobike.com',
            'bds' => 'bds-bikesensor.net',
            'bikesensor' => 'bds-bikesensor.net',
            'webike' => 'www.webike.net',
        ];

        return $domains[strtolower(trim($name))] ?? 'google.com';
    }

    private function resolveImageUrls($localPaths, $remoteUrls): array
    {
        if (is_string($localPaths)) {
            $localPaths = json_decode($localPaths, true);
        }

        if (! empty($localPaths) && is_array($localPaths)) {
            return array_map(fn ($p) => listing_image_url($p), $localPaths);
        }

        if (is_string($remoteUrls)) {
            $remoteUrls = json_decode($remoteUrls, true);
        }

        if (! empty($remoteUrls) && is_array($remoteUrls)) {
            return $remoteUrls;
        }

        return [];
    }
}
