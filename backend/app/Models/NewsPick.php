<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NewsPick extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'news_id',
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function news(): BelongsTo
    {
        return $this->belongsTo(BikeNews::class, 'news_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
