<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SeoCompare extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function model1(): BelongsTo
    {
        return $this->belongsTo(BikeModel::class, 'model1_id');
    }

    public function model2(): BelongsTo
    {
        return $this->belongsTo(BikeModel::class, 'model2_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('created_at');
    }

    public function getUrlAttribute(): string
    {
        return route('bikes.model_compare', $this->slug);
    }
}
