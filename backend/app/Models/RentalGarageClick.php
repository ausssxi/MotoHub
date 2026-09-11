<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * レンタルガレージ外部サイトへの送客クリック記録。
 *
 * 中継ルート /go/rental-garage/{id} が1件ずつ挿入する。IPアドレスは保持しない。
 * is_bot=true のレコードは残すが、送客実数の集計からは除外する。
 */
final class RentalGarageClick extends Model
{
    public $timestamps = false;

    protected $table = 'rental_garage_clicks';

    protected $fillable = [
        'rental_garage_id',
        'clicked_at',
        'referrer_path',
        'is_bot',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
        'is_bot' => 'boolean',
    ];

    public function rentalGarage(): BelongsTo
    {
        return $this->belongsTo(RentalGarage::class);
    }
}
