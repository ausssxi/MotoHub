<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class RoadsideStation extends Model
{
    protected $fillable = [
        'station_code',
        'name',
        'nickname',
        'address',
        'latitude',
        'longitude',
        'prefecture',
        'city',
        'route',
        'image_url',
        'image_author',
        'image_license',
        'image_license_url',
        'summary',
        'website_url',
        'wikipedia_url',
        'has_atm',
        'has_restaurant',
        'has_onsen',
        'has_ev_charging',
        'has_wifi',
        'has_shower',
        'has_camp',
        'has_gas_station',
        'has_observatory',
        'has_shop',
        'designated_year',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'has_atm' => 'boolean',
        'has_restaurant' => 'boolean',
        'has_onsen' => 'boolean',
        'has_ev_charging' => 'boolean',
        'has_wifi' => 'boolean',
        'has_shower' => 'boolean',
        'has_camp' => 'boolean',
        'has_gas_station' => 'boolean',
        'has_observatory' => 'boolean',
        'has_shop' => 'boolean',
        'designated_year' => 'integer',
    ];
}
