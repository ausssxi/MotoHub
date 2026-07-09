<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 不適切投稿の通報。当面の対象は [[ShopAcceptanceReport]]（ショップ口コミ）。
 * reportable_type にはモデルクラス名を格納するが、クライアントからは生のクラス名を
 * 受けず REPORTABLE_TYPES の短トークン経由でのみ解決する（任意クラス通報の防止）。
 */
final class Report extends Model
{
    protected $fillable = [
        'reportable_type',
        'reportable_id',
        'reason',
        'reporter_ip_hash',
        'status',
    ];

    /** ステータス（運営の対応状態）。 */
    public const STATUS_OPEN = 'open';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUSES = [
        self::STATUS_OPEN => '未対応',
        self::STATUS_REVIEWED => '対応済み',
        self::STATUS_DISMISSED => '問題なし',
    ];

    /** クライアントの短トークン => 通報対象モデル。ここに無い型は受け付けない。 */
    public const REPORTABLE_TYPES = [
        'shop_comment' => ShopAcceptanceReport::class,
    ];

    /** 通報理由キー => 表示ラベル。 */
    public const REASONS = [
        'fact' => '事実と異なる',
        'defame' => '誹謗中傷',
        'spam' => 'スパム/宣伝',
        'other' => 'その他',
    ];

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /** 理由キーの表示ラベル（未知キーはそのまま返す）。 */
    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? (string) $this->reason;
    }
}
