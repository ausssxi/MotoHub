<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * レンタルバイクの車格別参考価格（事業者×車格×プランで1行）。
 * ★保険・補償・在庫・画像は持たない。
 */
final class RentalBikePrice extends Model
{
    /** 表示・保存で使う MotoHub 5車格の並び順。 */
    public const CLASS_ORDER = ['原付', '125cc', '250cc', '400cc', '大型'];

    protected $fillable = [
        'company_slug',
        'vehicle_class',
        'plan',
        'plan_label',
        'price_yen',
        'price_is_from',
        'note',
        'source_url',
        'fetched_at',
    ];

    protected $casts = [
        'price_yen' => 'integer',
        'price_is_from' => 'boolean',
        'fetched_at' => 'datetime',
    ];
}
