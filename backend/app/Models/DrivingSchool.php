<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** verify_method: 人が確認 / 機械が判定。 */
    public const VERIFY_HUMAN = 'human';

    public const VERIFY_MACHINE = 'machine';

    public const VERIFY_METHODS = [self::VERIFY_HUMAN, self::VERIFY_MACHINE];

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
        'verify_method',
        'address',
        'latitude',
        'longitude',
        'geocoded_at',
        'geocode_status',
    ];

    protected $casts = [
        'futsuu_nirin' => 'boolean',
        'oogata_nirin' => 'boolean',
        'verified_at' => 'date',
        'latitude' => 'float',
        'longitude' => 'float',
        'geocoded_at' => 'datetime',
    ];

    /** この校の二輪コース料金（区分×MT/AT×所持免許）。 */
    public function courses(): HasMany
    {
        return $this->hasMany(DrivingSchoolCourse::class);
    }

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

    /**
     * verified_at からの経過が確認頻度の閾値を超えたら true（鮮度の注意書き用）。
     *
     * 閾値は tier で分ける: 重点層（config の focus_prefectures）=6ヶ月、全国層=13ヶ月。
     * tier は prefecture_slug から config で引く（DBに列は持たない）。verified_at が未設定なら stale 扱い。
     */
    public function isStale(): bool
    {
        if ($this->verified_at === null) {
            return true;
        }

        $focus = config('driving_schools.focus_prefectures', []);
        $months = in_array($this->prefecture_slug, $focus, true) ? 6 : 13;

        return $this->verified_at->lt(now()->subMonthsNoOverflow($months));
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
