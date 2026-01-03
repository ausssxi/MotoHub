<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * メーカーマスタモデル
 */
final class Manufacturer extends Model
{
    /**
     * このメーカーに属する車種一覧を取得
     */
    public function bikeModels(): HasMany
    {
        return $this->hasMany(BikeModel::class);
    }
}