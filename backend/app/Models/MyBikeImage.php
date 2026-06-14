<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MyBikeImage extends Model
{
    protected $fillable = [
        'my_bike_id',
        'path',
        'caption',
        'sort_order',
    ];

    protected static function booted(): void
    {
        // 行削除時に実ファイルも削除（owner個別削除・愛車削除カスケードの両方で発火）。
        // FKのDBカスケードはEloquentイベントを発火しないため、削除はモデル経由で行うこと。
        static::deleting(function (MyBikeImage $image): void {
            if ($image->path) {
                Storage::disk(config('garage.image_disk'))->delete($image->path);
            }
        });
    }

    public function myBike(): BelongsTo
    {
        return $this->belongsTo(MyBike::class);
    }
}
