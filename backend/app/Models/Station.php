<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Station extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'old_slug',
        'kana',
        'prefecture',
        'city',
        'latitude',
        'longitude',
        'line_names',
        'company_names',
        'passengers_per_day',
        'description',
        'is_major',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'passengers_per_day' => 'integer',
        'is_major' => 'boolean',
    ];

    public function bikeParkings(): HasMany
    {
        return $this->hasMany(BikeParking::class);
    }

    /**
     * 半径内の駐車場を取得（Haversine距離でソート）
     */
    public function nearbyParkings(float $radiusKm = 0.5): Builder
    {
        $lat = $this->latitude;
        $lng = $this->longitude;

        return BikeParking::active()
            ->selectRaw('*, (
                6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )
            ) AS distance', [$lat, $lng, $lat])
            ->having('distance', '<=', $radiusKm)
            ->orderBy('distance');
    }

    public function scopeMajor(Builder $query): Builder
    {
        return $query->where('is_major', true);
    }

    public function scopeByPrefecture(Builder $query, string $prefecture): Builder
    {
        return $query->where('prefecture', $prefecture);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
