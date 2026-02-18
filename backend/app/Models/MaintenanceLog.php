<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceLog extends Model
{
    protected $fillable = [
        'my_bike_id',
        'maintained_at',
        'odometer',
        'title',
        'cost',
        'note',
    ];

    protected $casts = [
        'maintained_at' => 'date',
        'odometer' => 'integer',
        'cost' => 'integer',
    ];

    public function myBike(): BelongsTo
    {
        return $this->belongsTo(MyBike::class);
    }
}