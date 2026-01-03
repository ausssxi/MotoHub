<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 販売店識別番号モデル
 * 外部サイトの店舗IDと内部店舗IDを紐付けます
 */
final class ShopIdentifier extends Model
{
    /**
     * 所属する販売店マスタを取得
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * 紐付いているサイト情報を取得
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}