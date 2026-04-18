<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'bike_model_id',
        'nickname',
        'title',
        'body',
        'rating',
        'rating_design',
        'rating_engine',
        'rating_handling',
        'rating_fuel_economy',
        'rating_cost_performance',
        'is_approved',
        'tweeted_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'rating_design' => 'integer',
        'rating_engine' => 'integer',
        'rating_handling' => 'integer',
        'rating_fuel_economy' => 'integer',
        'rating_cost_performance' => 'integer',
        'is_approved' => 'boolean',
    ];

    public function bikeModel(): BelongsTo
    {
        return $this->belongsTo(BikeModel::class);
    }
}