<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 症状診断ファネルの計測イベント（PIIなし）。
 * created_at のみ（updated_at は持たない）。
 */
final class TroubleEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** 許可イベント種別 */
    public const EVENTS = ['symptom_selected', 'step_answered', 'verdict_shown', 'cta_clicked', 'feedback'];

    /** feedback イベントの許可 answer */
    public const FEEDBACK_ANSWERS = ['yes', 'no'];

    /** 許可CTA種別 */
    public const CTAS = ['article', 'shop', 'parts', 'register', 'retry', 'submit_shop', 'fitment', 'battery_rescue'];

    /** 許可 source */
    /** source 許可値。deeplink=症状ディープリンク / deeplink_card=結果パーマリンク直着地 */
    public const SOURCES = ['deeplink', 'deeplink_card'];
}
