<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Report;

/**
 * UGC本体を物理削除したら、それを指す polymorphic な通報(Report)も一緒に消す。
 * reports は morphs（FK無し）なので DB カスケードが効かず、放置すると
 * 通報キューに「対象なし」の行と未対応バッジ件数のズレが残るため手動でpurgeする。
 * reportable_type は FQCN 保存のため getMorphClass() で一致する。
 */
trait PurgesReportsOnDelete
{
    public static function bootPurgesReportsOnDelete(): void
    {
        static::deleting(function ($model): void {
            Report::where('reportable_type', $model->getMorphClass())
                ->where('reportable_id', $model->getKey())
                ->delete();
        });
    }
}
