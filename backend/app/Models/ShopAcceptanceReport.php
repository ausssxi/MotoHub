<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ユーザー投稿の店舗受け入れ情報（他店購入車OK 等）。
 * スクレイパーとは無関係の独立テーブル（[[shops.service_tags]] とは別系統）。
 */
final class ShopAcceptanceReport extends Model
{
    protected $fillable = [
        'shop_id',
        'accepts_other_store',
        'accepts_bring_in',
        'pickup_service',
        'walk_in_ok',
        'comment',
        'submitter_name',
        'is_approved',
        'user_id',
        'submitter_ip_hash',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'accepts_other_store' => 'boolean',
        'accepts_bring_in' => 'boolean',
        'pickup_service' => 'boolean',
        'walk_in_ok' => 'boolean',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
    ];

    /** 表示可能なフラグの定義（カラム => 表示ラベル）。集計・UI で共用する。 */
    public const FLAGS = [
        'accepts_other_store' => '他店購入車OK',
        'accepts_bring_in' => '持ち込みOK',
        'pickup_service' => '引き取り対応',
        'walk_in_ok' => '飛び込みOK',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }
}
