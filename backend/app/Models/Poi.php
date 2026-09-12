<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AddressFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class Poi extends Model
{
    protected $fillable = [
        'osm_id',
        'type',
        'name',
        'latitude',
        'longitude',
        'address',
        'brand',
        'opening_hours',
        'self_service',
        'automated',
        'geocode_failed_at',
        'nearest_same_type_id',
        'nearest_same_type_m',
        'nearest_computed_at',
    ];

    protected $casts = [
        'osm_id' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'geocode_failed_at' => 'datetime',
        'nearest_same_type_id' => 'integer',
        'nearest_same_type_m' => 'integer',
        'nearest_computed_at' => 'datetime',
    ];

    /** POI種別の日本語名（表示名フォールバックの最終段）。 */
    private const TYPE_LABELS = [
        'gas_station' => 'ガソリンスタンド',
        'convenience_store' => 'コンビニ',
        'michi_no_eki' => '道の駅',
        'car_wash' => '洗車場',
    ];

    /**
     * 表示名フォールバック（name が null/空でも空文字にしない・センチネルも使わない）。
     * 順序: name → brand → 種別名（町名） → 種別名。「種別名（町名）」段は住所から町名を取り出せた時だけ
     * （例: 洗車場（新砂一丁目））。address/prefecture/city が未SELECTだと町名段は静かに飛ばされ種別名に落ちる。
     *
     * ※ map.js の resolveName（poi-name.js）は name→brand→種別名 で、この「町名」段を持たない＝1段ずれる。
     *   ただし地図APIは display_name を出力に含めない（resolveName が item.name 等を直接読む）ため実害は無い。
     *   使用箇所: レンタルガレージ/道の駅 詳細の周辺リスト（モデルを full-select して表示時に解決）。
     */
    public function getDisplayNameAttribute(): string
    {
        $name = trim((string) ($this->name ?? ''));
        if ($name !== '') {
            return $name;
        }
        $brand = trim((string) ($this->brand ?? ''));
        if ($brand !== '') {
            return $brand;
        }

        $label = self::TYPE_LABELS[$this->type] ?? 'スポット';
        $town = AddressFormatter::townPart($this->prefecture, $this->city, $this->address);
        if ($town !== '') {
            return $label.'（'.$town.'）';
        }

        return $label;
    }

    public function scopeInBounds(Builder $query, float $swLat, float $swLng, float $neLat, float $neLng): Builder
    {
        return $query
            ->whereBetween('latitude', [$swLat, $neLat])
            ->whereBetween('longitude', [$swLng, $neLng]);
    }

    public function scopeOfType(Builder $query, string|array $type): Builder
    {
        if (is_array($type)) {
            return $query->whereIn('type', $type);
        }

        return $query->where('type', $type);
    }

    /**
     * 最近傍が計算済みか（nearest_computed_at IS NOT NULL）。
     * これが false の行は「離島」でも「近傍あり」でもなく“判定不能”。
     * poi:fetch(毎晩) が追加した未計算行を、間違って離島扱いしないための唯一の判定入口。
     */
    public function nearestComputed(): bool
    {
        return $this->nearest_computed_at !== null;
    }

    /**
     * 本物の離島か（計算済み かつ 100km以内に同種別POIが無い）。
     * 未計算（nearest_computed_at IS NULL）は false を返す（「他にありません」を出さない）。
     */
    public function isGenuinelyIsolated(): bool
    {
        return $this->nearest_computed_at !== null && $this->nearest_same_type_id === null;
    }

    /**
     * 本物の離島だけを選ぶスコープ（表示・サイトマップ・各種別で共通の唯一の条件）。
     * 未計算行は除外する。isGenuinelyIsolated() の SQL 版。
     */
    public function scopeGenuinelyIsolated(Builder $query): Builder
    {
        return $query->whereNotNull('nearest_computed_at')->whereNull('nearest_same_type_id');
    }

    /**
     * GS(type=gas_station)の brand から地図ピンの運営分類キーを返す。
     *
     *   'eneos'|'idemitsu'|'cosmo'|'ja-ss'|'hokuren'|'kygnus'|'solato' … 固有色
     *   'other'   … 実在の独立系GS屋号（共通色バッジ）
     *   'exclude' … 明確な非GS（config/gas.php exclude ＋ ハングル）→ ペイロードから落とす
     *   null      … brand 空/不明 → 従来の赤⛽
     *
     * 評価順: 空 → ハングル/exclude → JAガード → cosmoガード → 固有patterns → other。
     * 誤爆源（JA→latin ja、cosmo→コスモス薬品）は専用ガードで先に確定させる。
     */
    public static function gasBrand(?string $brand): ?string
    {
        if ($brand === null || trim($brand) === '') {
            return null; // brand 不明 → 赤⛽（除外しない）
        }

        // ハングルを含む＝韓国系表記（GS칼텍스/현대오일뱅크 等）→ 非GS扱いで除外。
        if (preg_match('/[\x{AC00}-\x{D7A3}\x{1100}-\x{11FF}\x{3130}-\x{318F}]/u', $brand)) {
            return 'exclude';
        }

        $n = \App\Support\ShopNameNormalizer::normalize($brand);

        // 明確な非GS（小売/水素/都市ガス/EV/ノイズ）→ 除外。ブランド照合より前に殺す。
        foreach ((array) config('gas.exclude', []) as $bad) {
            $needle = \App\Support\ShopNameNormalizer::normalize((string) $bad);
            if ($needle !== '' && str_contains($n, $needle)) {
                return 'exclude';
            }
        }

        // JA-SS（農協系）ガード: bare「JA」＋ JA-SS/JASS/全農/農協/「JA＋非ラテン(JAおきなわ等)」。
        // latin の JAF/JAL/japan 等は巻き込まない（'ja' 直後がラテン英数なら不一致）。
        if ($n === 'ja'
            || str_contains($n, 'ja-ss') || str_contains($n, 'jass')
            || str_contains($n, '全農') || str_contains($n, '農協')
            || preg_match('/^ja[^a-z0-9]/u', $n)) {
            return 'ja-ss';
        }

        // コスモガード: カナ「コスモ」/ latin「cosmo」。ただし曖昧な「cosmos」(=薬局の疑い)は除外。
        if (str_contains($n, 'コスモ') || (str_contains($n, 'cosmo') && ! str_contains($n, 'cosmos'))) {
            return 'cosmo';
        }

        // 固有ブランド patterns（正規化済の部分一致）。
        foreach ((array) config('gas.brands', []) as $key => $def) {
            foreach (($def['patterns'] ?? []) as $pattern) {
                $needle = \App\Support\ShopNameNormalizer::normalize((string) $pattern);
                if ($needle !== '' && str_contains($n, $needle)) {
                    return $key;
                }
            }
        }

        return 'other'; // 実在の独立系屋号（無理に元売判定しない）
    }

    /**
     * 地図ピン/詳細に出す運営表示名。固有ブランドは config の表示名、
     * 'other' は生の屋号を短縮。null/'exclude' は表示名なし。
     */
    public static function gasOperatorLabel(?string $brand, ?string $gasBrand): ?string
    {
        if ($gasBrand === null || $gasBrand === 'exclude') {
            return null;
        }
        if ($gasBrand !== 'other') {
            return config("gas.brands.$gasBrand.name", $gasBrand);
        }

        $name = trim((string) $brand);

        return mb_strlen($name) > 10 ? mb_substr($name, 0, 10).'…' : $name;
    }

    /**
     * コンビニ(type=convenience_store)の brand から運営分類キーを返す（GSと同型）。
     *
     *   固有色7: seven/familymart/lawson/ministop/daily-yamazaki/seicomart/newdays
     *   'other'   … その他チェーン（ポプラ/Heart・in 等）
     *   'exclude' … 閉店タグ(disused:) → ペイロードから除外
     *   null      … brand 空/不明 → 従来アイコン
     */
    public static function cvsBrand(?string $brand): ?string
    {
        if ($brand === null || trim($brand) === '') {
            return null;
        }

        $n = \App\Support\ShopNameNormalizer::normalize($brand);

        // 閉店タグ等の除外（ブランド照合より前）。
        foreach ((array) config('convenience.exclude', []) as $bad) {
            $needle = \App\Support\ShopNameNormalizer::normalize((string) $bad);
            if ($needle !== '' && str_contains($n, $needle)) {
                return 'exclude';
            }
        }

        foreach ((array) config('convenience.brands', []) as $key => $def) {
            foreach (($def['patterns'] ?? []) as $pattern) {
                $needle = \App\Support\ShopNameNormalizer::normalize((string) $pattern);
                if ($needle !== '' && str_contains($n, $needle)) {
                    return $key;
                }
            }
        }

        return 'other';
    }

    /**
     * コンビニのピン/詳細に出す運営表示名。固有ブランドは config の表示名、
     * 'other' は生の屋号を短縮。null/'exclude' は表示名なし。
     */
    public static function cvsOperatorLabel(?string $brand, ?string $cvsBrand): ?string
    {
        if ($cvsBrand === null || $cvsBrand === 'exclude') {
            return null;
        }
        if ($cvsBrand !== 'other') {
            return config("convenience.brands.$cvsBrand.name", $cvsBrand);
        }

        $name = trim((string) $brand);

        return mb_strlen($name) > 10 ? mb_substr($name, 0, 10).'…' : $name;
    }
}
