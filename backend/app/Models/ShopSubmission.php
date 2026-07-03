<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ユーザー投稿の店舗登録申請。承認で shops(source='user') に昇格、
 * または既存 shop に統合される。スクレイパーとは無関係の独立テーブル。
 */
final class ShopSubmission extends Model
{
    protected $fillable = [
        'shop_name',
        'prefecture',
        'city',
        'shop_type',
        'address',
        'phone',
        'website_url',
        'image_path',
        'service_tags',
        'acceptance_flags',
        'comment',
        'submitter_name',
        'user_id',
        'ip_hash',
        'status',
        'linked_shop_id',
        'target_shop_id',
        'processed_at',
    ];

    protected $casts = [
        'service_tags' => 'array',
        'acceptance_flags' => 'array',
        'processed_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_MERGED = 'merged';

    public const STATUS_REJECTED = 'rejected';

    /** 店種の選択肢（キー => ラジオ表示ラベル）。承認時に shops.shop_type へ反映。 */
    public const SHOP_TYPE_OPTIONS = [
        'dealer' => 'バイクの販売もしている店（新車・中古車販売）',
        'repair_only' => '整備・修理の専門店（販売なし）',
        'unknown' => 'わからない',
    ];

    /**
     * 投稿フォームで選べる対応サービス（Webikeの service_tags 語彙に合わせる。
     * /shops/repair のファセットでスクレイプ由来タグと自然に合流する）。
     */
    public const SERVICE_TAG_OPTIONS = [
        '認証工場',
        '修理・点検整備',
        '車検受付',
        'オイル交換',
        'タイヤ交換',
        'カスタム・取付',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function linkedShop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'linked_shop_id');
    }

    public function targetShop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'target_shop_id');
    }

    /** 公式URL提案（既存店対象）かどうか。 */
    public function isUrlSuggestion(): bool
    {
        return $this->target_shop_id !== null;
    }
}
