<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class RentalGarage extends Model
{
    use SoftDeletes;

    protected $table = 'rental_garages';

    protected $fillable = [
        'name',
        'operator',
        'garage_type',
        'postal_code',
        'prefecture',
        'city',
        'address',
        'latitude',
        'longitude',
        'monthly_fee_min',
        'monthly_fee_max',
        'size_text',
        'is_24h',
        'has_power',
        'has_security',
        'has_shutter',
        'capacity',
        'phone',
        'website_url',
        'description',
        'source',
        'source_url',
        'submitted_by',
        'is_active',
        'is_verified',
        'geocode_status',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_24h' => 'boolean',
        'has_power' => 'boolean',
        'has_security' => 'boolean',
        'has_shutter' => 'boolean',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
    ];

    private const GARAGE_TYPE_LABELS = [
        'indoor' => '屋内ガレージ',
        'container' => '屋外コンテナ',
        'open' => '青空月極',
        'other' => 'その他',
    ];

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * garage_type の日本語ラベル。
     */
    public function getGarageTypeLabelAttribute(): string
    {
        return self::GARAGE_TYPE_LABELS[$this->garage_type] ?? 'その他';
    }
}
