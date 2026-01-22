<?php

declare(strict_types=1);

namespace App\Http\Resources\Bike;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * 出品データをフロントエンド向けのJSONに変換するリソースクラス。
 * これまで Service 内にあった整形ロジックをここに集約します。
 */
class ListingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'source'         => $this->resolveSourceDisplayName($this->site?->name ?? ''),
            'source_domain'  => $this->resolveSourceDomain($this->site?->name ?? ''),
            'maker'          => $this->bikeModel?->manufacturer?->name ?? '不明',
            'name'           => $this->title ?? $this->bikeModel?->name ?? '車種名不明',
            'model_year'     => $this->model_year ? "{$this->model_year}年" : '不明',
            'mileage'        => $this->mileage !== null ? number_format($this->mileage) . 'km' : '走行不明',
            'displacement'   => $this->bikeModel?->displacement ? "{$this->bikeModel->displacement}cc" : '-',
            'repair_history' => $this->has_repair_history ? 'あり' : 'なし',
            'condition'      => $this->condition ?? '不明', 
            'total_price'    => $this->total_price ? number_format((float)($this->total_price / 10000), 1) : '-',
            'base_price'     => $this->price ? number_format((float)($this->price / 10000), 1) : '-',
            'store_name'     => $this->shop?->name ?? '個人出品等',
            'url'            => $this->source_url,
            'images'         => $this->resolveImageUrls($this->local_image_paths),
        ];
    }

    /**
     * サイト名の日本語表示を解決
     */
    private function resolveSourceDisplayName(string $name): string
    {
        return match (strtolower(trim($name))) {
            'goobike' => 'グーバイク',
            'bds', 'bikesensor' => 'BDSバイクセンサー',
            'webike' => 'Webike',
            default => $name ?: '不明',
        };
    }

    /**
     * 出典サイトのドメインを解決
     */
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
     * 保存済みの画像パスをフルURLに変換
     */
    private function resolveImageUrls(?array $paths): array
    {
        if (empty($paths)) {
            return [];
        }
        return array_map(fn($p) => Storage::disk('public')->url(ltrim($p, '/')), $paths);
    }
}