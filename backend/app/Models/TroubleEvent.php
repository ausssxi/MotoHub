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
    public const EVENTS = ['symptom_selected', 'step_answered', 'verdict_shown', 'cta_clicked'];

    /** 許可CTA種別 */
    public const CTAS = ['article', 'shop', 'parts', 'register', 'retry', 'submit_shop'];

    /** 許可 source */
    public const SOURCES = ['deeplink'];
}
