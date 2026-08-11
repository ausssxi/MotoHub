<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * スケジュール実行の失敗記録（RecordScheduledTaskFailure が書き、ops:daily-report が読む）。
 *
 * failed_at を自前で持つため created_at/updated_at は使わない（同じ意味の列を2つ持たない）。
 */
final class ScheduledTaskFailure extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'command',
        'exit_code',
        'output',
        'failed_at',
    ];

    protected $casts = [
        'exit_code' => 'integer',
        'failed_at' => 'datetime',
    ];
}
