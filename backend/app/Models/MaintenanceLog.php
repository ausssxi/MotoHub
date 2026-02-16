<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceLog extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'maintained_at' => 'date',
    ];

    public function myBike(): BelongsTo
    {
        return $this->belongsTo(MyBike::class);
    }
}