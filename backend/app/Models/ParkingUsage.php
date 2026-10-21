<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 駐車場の「使ったことある」ワンタップ反応（レビューより軽い・ログイン不要）。
 *
 * - visitor_hash は sha256(ip|user-agent|app.key)。生IPは保存しない。
 * - (parking_id, visitor_hash) のユニーク制約で同じ訪問者は1回だけ記録する。
 * - 表示件数はこのテーブルの行数（bike_parkings にはカラムを足さない＝週次取り込みで上書きされない）。
 */
final class ParkingUsage extends Model
{
    // created_at のみ管理する（updated_at は持たない）。
    public const UPDATED_AT = null;

    protected $fillable = [
        'parking_id',
        'visitor_hash',
    ];

    public function parking(): BelongsTo
    {
        return $this->belongsTo(BikeParking::class, 'parking_id');
    }
}
