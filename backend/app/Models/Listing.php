<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 出品情報モデル
 */
final class Listing extends Model
{
    /**
     * カラムの型変換（キャスト）設定
     * ここに定義することで、DBのJSON文字列が自動的にPHPの配列に変換されます
     *
     * @var array<string, string>
     */
    protected $casts = [
        'image_urls' => 'array',
        'local_image_paths' => 'array',
        'price' => 'decimal:0',
        'total_price' => 'decimal:0',
        'is_sold_out' => 'boolean',
    ];

    /**
     * 所属する車種マスタを取得
     */
    public function bikeModel(): BelongsTo
    {
        return $this->belongsTo(BikeModel::class);
    }

    /**
     * 出品元の販売店を取得
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * 取得元のサイト情報を取得
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}