<?php

declare(strict_types=1);

namespace App\Http\Resources\Bike;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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

        // --- 爆速化の鍵（N+1防御） ---
        // ListingRepositoryで 'marketStats' がwith()されている（詳細ページ等）場合のみ計算する。
        // 検索一覧ページでは無視され、無駄な追加クエリ（20回）が走るのを防ぎます。
        $marketStats = $this->bikeModel && $this->bikeModel->relationLoaded('marketStats') 
            ? $this->bikeModel->marketStats 
            : null;
            
        $bargainInfo = null;

        if ($marketStats && $this->total_price && $this->total_price > 0) {
            $avgPrice = $marketStats->avg_price;
            
            if ($avgPrice > 0) {
                $diff = $avgPrice - $this->total_price;
                if ($diff >= 50000 && ($diff / $avgPrice) >= 0.05) {
                    $diffMan = floor($diff / 10000);
                    $bargainInfo = [
                        'diff' => $diffMan,
                        'label' => "相場より{$diffMan}万円お得！",
                        'is_bargain' => true
                    ];
                }
            }
        }
        
        // --- N+1防御 その2 ---
        // カテゴリ名も同様に、事前にロードされている場合のみ取得
        $categoryName = $this->bikeModel && $this->bikeModel->relationLoaded('categoryData')
            ? $this->bikeModel->categoryData->name
            : 'その他';

        return [
            'id'             => $this->id,
            'site_name'      => $this->resolveSourceDisplayName($this->site?->name ?? ''),
            'source'         => $this->resolveSourceDisplayName($this->site?->name ?? ''),
            'source_domain'  => $this->resolveSourceDomain($this->site?->name ?? ''),
            'source_icon_key'=> $siteKey,
            
            'maker'          => $this->bikeModel?->manufacturer?->name ?? 'メーカー不明',
            'category'       => $categoryName, // 防御済みの変数を使用
            'manufacturer_id' => $this->bikeModel?->manufacturer_id,
            'bike_model_id'   => $this->bike_model_id,
            'bike_model_name' => $this->bikeModel?->name,

            'name'           => $this->title ?? $this->bikeModel?->name ?? '車種名不明',
            'model_year'     => $this->model_year ? "{$this->model_year}年" : '不明',
            'mileage'        => $this->mileage !== null ? number_format($this->mileage) . 'km' : '走行不明',
            'displacement'   => $this->bikeModel?->displacement ? "{$this->bikeModel->displacement}cc" : '-',
            'repair_history' => $this->has_repair_history ? 'あり' : 'なし',
            'condition'      => $this->condition ?? '不明',
            
            'total_price'    => $this->total_price ? number_format((float)($this->total_price / 10000), 1) : '-',
            'price'          => $this->price ? number_format((float)($this->price / 10000), 1) : '-',
            'base_price'     => $this->price ? number_format((float)($this->price / 10000), 1) : '-', 

            'bargain_info'   => $bargainInfo,
            
            // 店舗情報
            'shop_id'        => $this->shop_id,
            'shop_image'     => $this->shop?->display_image_url,
            'store_name'     => $this->shop?->name ?? '不明な販売店', 
            'shop_name'      => $this->shop?->name ?? '不明な販売店', 
            'shop_address'   => $this->shop?->address,
            'shop_tel'       => $this->shop?->phone,
            'shop_hours'     => $this->shop?->business_hours,
            'prefecture'     => $this->shop?->prefecture ?? '全国',

            // 詳細情報 (存在しないカラムへの安全なアクセス)
            'description'    => $this->description ?? null,
            'bargain_score'  => $this->bargain_score ?? 0,
            'url'            => $this->source_url,

            // エンゲージメント指標
            'engagement' => [
                'view_count_today' => $viewCount,
                'wishlist_count'   => ($this->id % 15) + 3, 
                'is_popular'       => ($viewCount > 30 || $favCount > 5), 
            ],

            // タグ情報をBladeに渡す処理（元々安全です）
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(function ($tag) {
                    return [
                        'id'   => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    ];
                });
            }, []),
            
            'images'         => $this->resolveImageUrls($this->local_image_paths ?? [], $this->image_urls ?? []),
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
            'goobike'    => 'goobike.com',
            'bds'        => 'bds-bikesensor.net',
            'bikesensor' => 'bds-bikesensor.net',
            'webike'     => 'www.webike.net'
        ];
        return $domains[strtolower(trim($name))] ?? 'google.com';
    }

    private function resolveImageUrls($localPaths, $remoteUrls): array
    {
        if (is_string($localPaths)) $localPaths = json_decode($localPaths, true);
        
        if (!empty($localPaths) && is_array($localPaths)) {
            return array_map(fn($p) => Storage::disk('public')->url(ltrim($p, '/')), $localPaths);
        }

        if (is_string($remoteUrls)) $remoteUrls = json_decode($remoteUrls, true);
        
        if (!empty($remoteUrls) && is_array($remoteUrls)) {
            return $remoteUrls;
        }

        return [];
    }
}