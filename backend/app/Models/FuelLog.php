<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FuelLog extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'my_bike_id',
        'filled_at',
        'odometer',
        'quantity',
        'cost',
        'is_full_tank',
        'efficiency',
        'memo',
    ];

    protected $casts = [
        'filled_at' => 'date',
        'is_full_tank' => 'boolean',
        'odometer' => 'decimal:1',
        'quantity' => 'decimal:2',
        'efficiency' => 'decimal:2',
    ];

    public function myBike(): BelongsTo
    {
        return $this->belongsTo(MyBike::class);
    }
}
