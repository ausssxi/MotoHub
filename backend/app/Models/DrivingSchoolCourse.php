<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 教習所の二輪コース料金（区分×MT/AT×所持免許×通学形態）。
 *
 * 行の存在＝その区分を開講している。開講しているが料金非公表なら price_yen=NULL。
 */
class DrivingSchoolCourse extends Model
{
    // vehicle_class
    public const CLASS_KOGATA_NIRIN = 'kogata_nirin';

    public const CLASS_FUTSUU_NIRIN = 'futsuu_nirin';

    public const CLASS_OOGATA_NIRIN = 'oogata_nirin';

    public const VEHICLE_CLASSES = [
        self::CLASS_KOGATA_NIRIN,
        self::CLASS_FUTSUU_NIRIN,
        self::CLASS_OOGATA_NIRIN,
    ];

    // transmission
    public const TRANSMISSION_MT = 'mt';

    public const TRANSMISSION_AT = 'at';

    public const TRANSMISSIONS = [self::TRANSMISSION_MT, self::TRANSMISSION_AT];

    // prerequisite（前提となる所持免許）
    public const PREREQ_NONE = 'none';

    public const PREREQ_CAR = 'car';

    public const PREREQ_KOGATA_NIRIN = 'kogata_nirin';

    public const PREREQ_FUTSUU_NIRIN_AT = 'futsuu_nirin_at';

    public const PREREQ_FUTSUU_NIRIN_MT = 'futsuu_nirin_mt';

    public const PREREQUISITES = [
        self::PREREQ_NONE,
        self::PREREQ_CAR,
        self::PREREQ_KOGATA_NIRIN,
        self::PREREQ_FUTSUU_NIRIN_AT,
        self::PREREQ_FUTSUU_NIRIN_MT,
    ];

    // enrollment_type
    public const ENROLLMENT_COMMUTE = 'commute';

    public const ENROLLMENT_CAMP = 'camp';

    public const ENROLLMENT_TYPES = [self::ENROLLMENT_COMMUTE, self::ENROLLMENT_CAMP];

    /** 表示用ラベル辞書。 */
    private const VEHICLE_CLASS_LABELS = [
        self::CLASS_KOGATA_NIRIN => '小型二輪',
        self::CLASS_FUTSUU_NIRIN => '普通二輪',
        self::CLASS_OOGATA_NIRIN => '大型二輪',
    ];

    private const PREREQ_LABELS = [
        self::PREREQ_NONE => '免許なし',
        self::PREREQ_CAR => '普通車所持',
        self::PREREQ_KOGATA_NIRIN => '小型二輪所持',
        self::PREREQ_FUTSUU_NIRIN_AT => '普通二輪AT所持',
        self::PREREQ_FUTSUU_NIRIN_MT => '普通二輪MT所持',
    ];

    protected $fillable = [
        'driving_school_id',
        'vehicle_class',
        'transmission',
        'prerequisite',
        'enrollment_type',
        'price_yen',
        'price_note',
        'source_url',
        'verified_at',
    ];

    protected $casts = [
        'price_yen' => 'integer',
        'verified_at' => 'date',
    ];

    public function drivingSchool(): BelongsTo
    {
        return $this->belongsTo(DrivingSchool::class);
    }

    /** 表示用ラベル。例: 「大型二輪MT（普通二輪MT所持）」。 */
    public function getLabelAttribute(): string
    {
        $vehicle = self::VEHICLE_CLASS_LABELS[$this->vehicle_class] ?? $this->vehicle_class;
        $trans = strtoupper((string) $this->transmission);
        $prereq = self::PREREQ_LABELS[$this->prerequisite] ?? $this->prerequisite;

        return "{$vehicle}{$trans}（{$prereq}）";
    }
}
