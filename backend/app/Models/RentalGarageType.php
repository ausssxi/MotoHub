<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 加瀬倉庫の物件が持つ区画種別（cntn / bike / bike-out / trnk）。
 * 1物件が複数種別を持つため rental_garages との中間テーブルとして分離する。
 *
 * ★ 保持するのは type_code だけ。空き状況（isAvailable / availableCount）は
 *   リアルタイム情報のため取得・保存・表示のいずれもしない。
 */
final class RentalGarageType extends Model
{
    protected $table = 'rental_garage_types';

    protected $fillable = [
        'rental_garage_id',
        'type_code',
    ];

    public function rentalGarage(): BelongsTo
    {
        return $this->belongsTo(RentalGarage::class);
    }
}
