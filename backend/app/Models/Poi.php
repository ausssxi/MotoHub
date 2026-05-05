<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class Poi extends Model
{
    protected $fillable = [
        'osm_id',
        'type',
        'name',
        'latitude',
        'longitude',
        'address',
        'brand',
        'opening_hours',
    ];

    protected $casts = [
        'osm_id' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function scopeInBounds(Builder $query, float $swLat, float $swLng, float $neLat, float $neLng): Builder
    {
        return $query
            ->whereBetween('latitude', [$swLat, $neLat])
            ->whereBetween('longitude', [$swLng, $neLng]);
    }

    public function scopeOfType(Builder $query, string|array $type): Builder
    {
        if (is_array($type)) {
            return $query->whereIn('type', $type);
        }

        return $query->where('type', $type);
    }
}
