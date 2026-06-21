<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MyBikeImage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'my_bike_id',
        'maintenance_log_id', // null=ギャラリー / notnull=記録(整備/カスタム)の添付写真
        'path',
        'caption',
        'sort_order',
    ];

    protected static function booted(): void
    {
        // ★実ファイル削除は「物理削除(forceDelete)」のときだけ。論理削除では復元のためファイルを残す。
        // FKのDBカスケードはEloquentイベントを発火しないため、削除はモデル経由で行うこと。
        static::deleting(function (MyBikeImage $image): void {
            if ($image->isForceDeleting() && $image->path) {
                Storage::disk(config('garage.image_disk'))->delete($image->path);
            }
        });
    }

    public function myBike(): BelongsTo
    {
        return $this->belongsTo(MyBike::class);
    }

    public function maintenanceLog(): BelongsTo
    {
        return $this->belongsTo(MaintenanceLog::class);
    }
}
