<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 車種マスタモデル
 */
final class BikeModel extends Model
{
    /**
     * @var array<string, string>
     */
    protected $casts = [
        'displacement' => 'integer',
    ];

    /**
     * 所属するメーカーを取得
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * この車種に関連する出品情報を取得
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * 各サイト別の識別番号を取得
     */
    public function identifiers(): HasMany
    {
        return $this->hasMany(BikeModelIdentifier::class);
    }
}