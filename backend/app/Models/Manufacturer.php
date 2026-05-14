<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * メーカーマスタモデル
 */
final class Manufacturer extends Model
{
    protected $fillable = [
        'slug',
    ];
    
    /**
     * このメーカーに属する車種一覧を取得
     */
    public function bikeModels(): HasMany
    {
        return $this->hasMany(BikeModel::class);
    }

    /**
     * このメーカーの出品一覧を取得
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }
}