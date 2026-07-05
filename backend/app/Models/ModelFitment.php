<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 車種×作業（型番）適合行。CSV由来のキュレーションデータのみ。
 * verified_at が入った行のみ公開対象（FitmentPageController のゲート）。
 */
final class ModelFitment extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'compatible_part_nos' => 'array',
        'spec' => 'array',
        'verified_at' => 'date',
    ];

    public function bikeModel(): BelongsTo
    {
        return $this->belongsTo(BikeModel::class);
    }

    /** 公開対象（検証済み）に限定するスコープ。 */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }
}
