<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PurgesReportsOnDelete;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    use PurgesReportsOnDelete;

    protected $fillable = [
        'bike_model_id',
        'user_id',
        'nickname',
        'submitter_ip_hash',
        'title',
        'body',
        'rating',
        'rating_design',
        'rating_engine',
        'rating_handling',
        'rating_fuel_economy',
        'rating_cost_performance',
        'is_approved',
        'helpful_count',
        'tweeted_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'rating_design' => 'integer',
        'rating_engine' => 'integer',
        'rating_handling' => 'integer',
        'rating_fuel_economy' => 'integer',
        'rating_cost_performance' => 'integer',
        'helpful_count' => 'integer',
        'is_approved' => 'boolean',
    ];

    public function bikeModel(): BelongsTo
    {
        return $this->belongsTo(BikeModel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function helpfulVotes(): HasMany
    {
        return $this->hasMany(ReviewHelpfulVote::class);
    }
}
