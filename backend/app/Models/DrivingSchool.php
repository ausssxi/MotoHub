<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 二輪教習に対応した指定自動車教習所。
 *
 * - verified_at が非NULL の行だけが公開対象（人手確認ゲート）。取得時は必ず published() を通す。
 * - futsuu_nirin / oogata_nirin のどちらかが true の行だけがこのテーブルに入る（二輪校のみ）。
 */
class DrivingSchool extends Model
{
    /** 通常営業・二輪教習を受付中。公開対象。 */
    public const STATUS_OPEN = 'open';

    /** 二輪教習を一時停止／見合せ中。校は存続。再開したら open に戻す候補。非公開。 */
    public const STATUS_NIRIN_SUSPENDED = 'nirin_suspended';

    /** 廃業・営業終了。恒久的に非公開。 */
    public const STATUS_CLOSED = 'closed';

    /** 取りうる status の全値。 */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_NIRIN_SUSPENDED,
        self::STATUS_CLOSED,
    ];

    protected $fillable = [
        'prefecture',
        'prefecture_slug',
        'city',
        'name',
        'official_url',
        'futsuu_nirin',
        'oogata_nirin',
        'source_url',
        'verified_at',
        'status',
    ];

    protected $casts = [
        'futsuu_nirin' => 'boolean',
        'oogata_nirin' => 'boolean',
        'verified_at' => 'date',
    ];

    /** 公開対象（人手確認済み かつ status=open）だけに絞る。 */
    public function scopePublished(Builder $q): Builder
    {
        return $q->whereNotNull('verified_at')->where('status', self::STATUS_OPEN);
    }

    /** 普通二輪または大型二輪に対応する校だけに絞る。 */
    public function scopeNirin(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->where('futsuu_nirin', true)->orWhere('oogata_nirin', true));
    }

    /** 対応している免許区分のラベル配列（true のものだけ）。 */
    public function getLicenseLabelsAttribute(): array
    {
        $labels = [];
        if ($this->futsuu_nirin) {
            $labels[] = '普通二輪';
        }
        if ($this->oogata_nirin) {
            $labels[] = '大型二輪';
        }

        return $labels;
    }
}
