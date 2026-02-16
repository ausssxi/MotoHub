<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelLog extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'filled_at' => 'date',
        'quantity' => 'decimal:2',
        'efficiency' => 'decimal:2',
    ];

    public function myBike(): BelongsTo
    {
        return $this->belongsTo(MyBike::class);
    }
}