<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 車種識別番号モデル
 * 外部サイト（GooBike/BDS等）のIDと内部車種IDを紐付けます
 */
final class BikeModelIdentifier extends Model
{
    /**
     * 所属する車種マスタを取得
     */
    public function bikeModel(): BelongsTo
    {
        return $this->belongsTo(BikeModel::class);
    }

    /**
     * 紐付いているサイト情報を取得
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}