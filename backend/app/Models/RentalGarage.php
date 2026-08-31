<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class RentalGarage extends Model
{
    use SoftDeletes;

    protected $table = 'rental_garages';

    protected $fillable = [
        'name',
        'operator',
        'garage_type',
        'postal_code',
        'prefecture',
        'city',
        'address',
        'latitude',
        'longitude',
        'monthly_fee_min',
        'monthly_fee_max',
        'size_text',
        'is_24h',
        'has_power',
        'has_security',
        'has_shutter',
        'capacity',
        'phone',
        'website_url',
        'description',
        'source',
        'source_url',
        'submitted_by',
        'is_active',
        'is_verified',
        'geocode_status',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_24h' => 'boolean',
        'has_power' => 'boolean',
        'has_security' => 'boolean',
        'has_shutter' => 'boolean',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
    ];

    private const GARAGE_TYPE_LABELS = [
        'indoor' => '屋内ガレージ',
        'container' => '屋外コンテナ',
        'open' => '青空月極',
        'other' => 'その他',
    ];

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * garage_type の日本語ラベル。
     */
    public function getGarageTypeLabelAttribute(): string
    {
        return self::GARAGE_TYPE_LABELS[$this->garage_type] ?? 'その他';
    }

    /**
     * size_text の記号の表記ゆれを正規化する（唯一の正解の置き場所）。
     *
     * - 波ダッシュ U+301C「〜」と半角チルダ U+007E「~」を全角チルダ U+FF5E「～」に統一
     * - 帖 U+5E16 を畳 U+7573 に統一
     * - 前後の空白を trim
     * - null / 空文字は null
     *
     * ※ 数値書式や「2.8畳～10.1畳」のように単位が両側に付く形は変えない（機械的補正で情報を壊さない）。
     */
    public static function normalizeSizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = str_replace(["\u{301C}", "\u{007E}"], "\u{FF5E}", $value);
        $value = str_replace("\u{5E16}", "\u{7573}", $value);
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * size_text ミューテタ。スクレイパー・ユーザー投稿・管理画面など全書き込み経路を
     * Eloquent 経由で正規化に集約する（normalizeSizeText を通す）。
     */
    public function setSizeTextAttribute(?string $value): void
    {
        $this->attributes['size_text'] = self::normalizeSizeText($value);
    }

    /** 加瀬倉庫の運営会社名（分類・注記の判定に使う唯一の定数）。 */
    private const KASE_OPERATOR = '加瀬倉庫';

    /** バイク収納可能とされる最小サイズ（畳）。加瀬IT戦略推進部の確認: 原則「下段・1.6畳以上」。 */
    private const KASE_BIKE_MIN_JO = 1.6;

    /**
     * 加瀬倉庫の「バイクヤード」（バイク専用施設）か。1.6畳ルールの対象外。
     *
     * ★加瀬物件の2分類（バイクヤード / レンタルボックス）の判定はここ1か所に集約する。
     *   name に「バイクヤード」を含むかで確実に分類できる（該当0件のグレーは無い）。
     */
    public function isKaseBikeYard(): bool
    {
        return $this->operator === self::KASE_OPERATOR
            && mb_strpos((string) $this->name, 'バイクヤード') !== false;
    }

    /** 加瀬倉庫の「レンタルボックス」（バイクヤード以外）。1.6畳ルールの注記対象。 */
    public function isKaseRentalBox(): bool
    {
        return $this->operator === self::KASE_OPERATOR && ! $this->isKaseBikeYard();
    }

    /**
     * size_text から下限・上限（畳）を取り出す。畳/帖・各種チルダ対応。取れなければ [null, null]。
     *
     * @return array{0: ?float, 1: ?float}
     */
    public function sizeJoBounds(): array
    {
        $s = $this->size_text;
        if ($s === null || $s === '') {
            return [null, null];
        }
        $t = str_replace(["\u{301C}", "\u{007E}", "\u{FF5E}"], '~', $s);
        if (! preg_match_all('/([0-9]+(?:\.[0-9]+)?)\s*(?:畳|帖)/u', $t, $m)) {
            return [null, null];
        }
        $nums = array_map('floatval', $m[1]);

        return [min($nums), max($nums)];
    }

    /**
     * 加瀬レンタルボックスで、size_text の下限がバイク不可（1.6畳未満）か。
     * true のとき下限サイズ・monthly_fee_min はバイク不可区画のものなので、表示側で断り書きが要る。
     */
    public function kaseLowerBelowBikeMin(): bool
    {
        if (! $this->isKaseRentalBox()) {
            return false;
        }
        [$lo] = $this->sizeJoBounds();

        return $lo !== null && $lo < self::KASE_BIKE_MIN_JO;
    }

    /**
     * 表示用サイズ文字列。加瀬レンタルボックスで下限がバイク不可なら「1.6畳以上〜{上限}畳」に置換する。
     * それ以外（バイクヤード・他社・下限が1.6畳以上）は size_text をそのまま返す。
     *
     * ※全レンタルボックスで上限≥1.6畳（最大<1.6は0件）＝「1.6畳以上の区画が存在する」は断定可。
     *   一方、ちょうど1.6畳区画の存在はデータから不明なので「1.6畳〜」とは書かない。
     */
    public function displaySizeText(): ?string
    {
        if (! $this->kaseLowerBelowBikeMin()) {
            return $this->size_text;
        }
        [, $hi] = $this->sizeJoBounds();
        if ($hi === null) {
            return '1.6畳以上';
        }
        $upper = rtrim(rtrim(number_format($hi, 1), '0'), '.'); // 8.0→"8" / 10.1→"10.1"

        return '1.6畳以上〜'.$upper.'畳';
    }
}
