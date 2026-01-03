<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 販売店モデル
 */
final class Shop extends Model
{
    /**
     * この店舗が出品している車両を取得
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * 各サイト別の店舗識別番号を取得
     */
    public function identifiers(): HasMany
    {
        return $this->hasMany(ShopIdentifier::class);
    }
}