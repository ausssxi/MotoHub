<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * レンタルバイクの車格別参考価格（事業者×車格×プランで1行）。
 * ★保険・補償・在庫・画像は持たない。
 */
final class RentalBikePrice extends Model
{
    /** 表示・保存で使う MotoHub 5車格の並び順。 */
    public const CLASS_ORDER = ['原付', '125cc', '250cc', '400cc', '大型'];

    /**
     * 表示に使える鮮度（日数）。取得からこの日数を「超えた」料金は表示しない。
     * 月1回実行なので70日＝1回失敗しても消えず、2回連続失敗（約60日超）で消える。
     */
    public const FRESH_DAYS = 70;

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

    /**
     * 取得から FRESH_DAYS 日以内の（表示してよい）料金だけに絞る。
     * 「FRESH_DAYS を超えたら非表示」＝ ちょうど FRESH_DAYS 日前は表示（境界を含む）、それより古いと非表示。
     * 自動更新が止まっても古い料金を出し続けないための安全弁。
     */
    public function scopeFresh(Builder $query): Builder
    {
        return $query->where('fetched_at', '>=', now()->subDays(self::FRESH_DAYS));
    }
}
