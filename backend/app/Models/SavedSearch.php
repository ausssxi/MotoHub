<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSearch extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'conditions',
        'is_active',
        'mail_notify',
        'last_notified_at',
    ];

    protected $casts = [
        'conditions' => 'array', // JSONを配列として扱う
        'is_active' => 'boolean',
        'mail_notify' => 'boolean',
        'last_notified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}