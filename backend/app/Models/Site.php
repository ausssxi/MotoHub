<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 取得元サイトマスタモデル
 */
final class Site extends Model
{
    /**
     * このサイトから取得された出品情報を取得
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }
}