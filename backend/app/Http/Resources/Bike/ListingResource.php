<?php

declare(strict_types=1);

namespace App\Http\Resources\Bike;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * 出品データをフロントエンド向けのJSON/配列に変換するリソースクラス。
 * APIとBladeビューの両方で使用します。
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

        // お買い得判定ロジック
        // 紐付いている車種の相場情報を取得
        $marketStats = $this->bikeModel?->marketStats;
        $bargainInfo = null;

        // 相場情報があり、かつ車両価格が設定されている場合のみ計算
        if ($marketStats && $this->total_price && $this->total_price > 0) {
            $avgPrice = $marketStats->avg_price;
            
            // 平均価格が0円などの異常値でない場合
            if ($avgPrice > 0) {
                $diff = $avgPrice - $this->total_price;

                // 判定基準:
                // 1. 平均より 50,000円以上 安い
                // 2. 平均より 5%以上 安い (安いバイクで数千円の差でバッジが出ないように)
                if ($diff >= 50000 && ($diff / $avgPrice) >= 0.05) {
                    $diffMan = floor($diff / 10000); // 万円単位に変換
                    $bargainInfo = [
                        'diff' => $diffMan, // 差額(万円)
                        'label' => "相場より{$diffMan}万円お得！",
                        'is_bargain' => true
                    ];
                }
            }
        }

        return [
            'id'             => $this->id,
            'site_name'      => $this->resolveSourceDisplayName($this->site?->name ?? ''),
            'source'         => $this->resolveSourceDisplayName($this->site?->name ?? ''),
            'source_domain'  => $this->resolveSourceDomain($this->site?->name ?? ''),
            
            'maker'          => $this->bikeModel?->manufacturer?->name ?? 'メーカー不明',
            'category'       => $this->bikeModel?->categoryData?->name ?? 'その他',
            // パンくずリスト用のIDを追加
            'manufacturer_id' => $this->bikeModel?->manufacturer_id,
            'bike_model_id'   => $this->bike_model_id,
            'bike_model_name' => $this->bikeModel?->name,

            'name'           => $this->title ?? $this->bikeModel?->name ?? '車種名不明',
            'model_year'     => $this->model_year ? "{$this->model_year}年" : '不明',
            'mileage'        => $this->mileage !== null ? number_format($this->mileage) . 'km' : '走行不明',
            'displacement'   => $this->bikeModel?->displacement ? "{$this->bikeModel->displacement}cc" : '-',
            'repair_history' => $this->has_repair_history ? 'あり' : 'なし',
            'condition'      => $this->condition ?? '不明',
            
            // 価格フォーマット (Bladeでの表示に合わせて整形)
            'total_price'    => $this->total_price ? number_format((float)($this->total_price / 10000), 1) : '-',
            'price'          => $this->price ? number_format((float)($this->price / 10000), 1) : '-',
            'base_price'     => $this->price ? number_format((float)($this->price / 10000), 1) : '-', // API互換用

            // お買い得情報（バッジ表示用）
            'bargain_info'   => $bargainInfo,
            
            // 店舗情報
            'shop_id'        => $this->shop_id,
            'shop_image'     => $this->shop?->display_image_url,
            'store_name'     => $this->shop?->name ?? '不明な販売店', // API互換用
            'shop_name'      => $this->shop?->name ?? '不明な販売店', // Blade互換用
            'shop_address'   => $this->shop?->address,
            'shop_tel'       => $this->shop?->phone,
            'shop_hours'     => $this->shop?->business_hours,
            'prefecture'     => $this->shop?->prefecture ?? '全国',

            // 詳細情報
            'description'    => $this->description,
            'bargain_score'  => $this->bargain_score ?? 0,
            'url'            => $this->source_url,

            // エンゲージメント指標
            'engagement' => [
                'view_count_today' => $viewCount,
                'wishlist_count'   => ($this->id % 15) + 3, // 仮の数値（お気に入り数）
                'is_popular'       => ($viewCount > 30 || $favCount > 5), // 人気判定ロジック
            ],

            // タグ情報をBladeに渡す処理
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(function ($tag) {
                    return [
                        'id'   => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    ];
                });
            }, []),
            
            // 画像 (ローカルパスがあれば優先してURL化)
            'images'         => $this->resolveImageUrls($this->local_image_paths, $this->image_urls),
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

    /**
     * 画像URLの解決
     * local_image_paths があればそれを優先し、なければ image_urls (外部URL) を使う
     */
    private function resolveImageUrls($localPaths, $remoteUrls): array
    {
        // 1. ローカル画像の確認
        if (is_string($localPaths)) $localPaths = json_decode($localPaths, true);
        
        if (!empty($localPaths) && is_array($localPaths)) {
            // Storage::url() で /storage/listings/... の形式に変換
            return array_map(fn($p) => Storage::disk('public')->url(ltrim($p, '/')), $localPaths);
        }

        // 2. なければ外部URLの確認 (フォールバック)
        if (is_string($remoteUrls)) $remoteUrls = json_decode($remoteUrls, true);
        
        if (!empty($remoteUrls) && is_array($remoteUrls)) {
            return $remoteUrls;
        }

        return [];
    }
}