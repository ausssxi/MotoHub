<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class RoadsideStation extends Model
{
    protected $fillable = [
        'station_code',
        'name',
        'nickname',
        'address',
        'latitude',
        'longitude',
        'prefecture',
        'city',
        'route',
        'image_url',
        'image_author',
        'image_license',
        'image_license_url',
        'summary',
        'website_url',
        'wikipedia_url',
        'has_atm',
        'has_restaurant',
        'has_onsen',
        'has_ev_charging',
        'has_wifi',
        'has_shower',
        'has_camp',
        'has_gas_station',
        'has_observatory',
        'has_shop',
        'designated_year',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'has_atm' => 'boolean',
        'has_restaurant' => 'boolean',
        'has_onsen' => 'boolean',
        'has_ev_charging' => 'boolean',
        'has_wifi' => 'boolean',
        'has_shower' => 'boolean',
        'has_camp' => 'boolean',
        'has_gas_station' => 'boolean',
        'has_observatory' => 'boolean',
        'has_shop' => 'boolean',
        'designated_year' => 'integer',
    ];

    /**
     * image_url（Commons の Special:FilePath 形式）から、人間が閲覧できる
     * ファイル説明ページ（https://commons.wikimedia.org/wiki/File:<ファイル名>）のURLを返す。
     * クレジット表記の「Wikimedia Commons」リンク先に使う。
     *
     * ファイル名の取り出しは FetchRoadsideImageCredits::commonsFileName と同一規則
     * （クエリ除去 → Special:FilePath/ 以降 → rawurldecode）。
     * Special:FilePath 形式でない、または image_url が null のときは null。
     * ※ 表示時のみ計算するアクセサ。$appends には追加しない。
     */
    public function getImageSourceUrlAttribute(): ?string
    {
        $url = $this->image_url;
        if (! is_string($url) || $url === '') {
            return null;
        }

        // クエリ・フラグメントを除去してからパスを見る。
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! str_contains($path, 'Special:FilePath/')) {
            return null;
        }

        $segment = substr($path, strpos($path, 'Special:FilePath/') + strlen('Special:FilePath/'));
        // 念のため最後のセグメントのみ（保険）。
        if (str_contains($segment, '/')) {
            $segment = substr($segment, strrpos($segment, '/') + 1);
        }
        $fileName = trim(rawurldecode($segment));
        if ($fileName === '') {
            return null;
        }

        return 'https://commons.wikimedia.org/wiki/File:'.$fileName;
    }

    /**
     * nickname（"あさひかわ|旭川地場産業振興センター|あさひかわ" のような
     * パイプ区切りの別名リスト）を、表示用の配列にして返す。
     * '|' で分割 → 各要素 trim → 空文字除去 → 完全一致の重複除去 → 添字振り直し。
     * nickname が null / 空文字なら空配列。
     * ※ 表示時のみ計算するアクセサ。$appends には追加しない。
     */
    public function getNicknameListAttribute(): array
    {
        return self::splitPipeList($this->nickname);
    }

    /**
     * route（"国道12号|国道233号" のようなパイプ区切りの路線リスト）を表示用の配列で返す。
     * nickname_list と同作法：'|' 分割 → trim → 空除去 → 完全一致の重複除去 → 添字振り直し。
     * route が null / 空文字なら空配列。
     * ※ 表示時のみ計算するアクセサ。$appends には追加しない。
     */
    public function getRouteListAttribute(): array
    {
        return self::splitPipeList($this->route);
    }

    /**
     * パイプ区切り文字列を表示用配列へ。'|' 分割 → 各要素 trim → 空文字除去 → 完全一致の重複除去。
     *
     * @return array<int, string>
     */
    public static function splitPipeList(?string $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $parts = array_map('trim', explode('|', $value));
        $parts = array_filter($parts, static fn (string $p): bool => $p !== '');

        return array_values(array_unique($parts));
    }

    /**
     * 表記ゆれ吸収キー（比較専用・表示文字列は変えない）:
     *   - 全角英数記号(U+FF01–FF5E)を半角へ（！→! ｉ→i （）→() など）
     *   - 「ヶ」U+30F6 → 「ケ」U+30B1（一方向のみ）
     *   - 空白（半角・全角 U+3000）を除去
     * 別名の重複判定（NormalizeRoadsideNicknames）と、一覧での「愛称=名称」判定に共通で使う単一実装。
     */
    public static function normalizationKey(string $s): string
    {
        // 全角英数記号 U+FF01–FF5E → 半角 U+0021–007E（差は 0xFEE0 固定）。
        $s = preg_replace_callback(
            '/[\x{FF01}-\x{FF5E}]/u',
            static fn (array $m): string => mb_chr(mb_ord($m[0]) - 0xFEE0),
            $s
        ) ?? $s;

        // 「ヶ」→「ケ」
        $s = str_replace("\u{30F6}", "\u{30B1}", $s);

        // 空白（半角・全角）を除去
        return preg_replace('/[\s\x{3000}]+/u', '', $s) ?? $s;
    }

    /**
     * name から「道の駅」を除いた判定用の部分文字列を返す。
     */
    public static function nameCore(string $name): string
    {
        return trim(str_replace('道の駅', '', $name));
    }

    /**
     * 与えた愛称が、名称（「道の駅」除去後）と表記ゆれを無視して実質同一か。
     * true のとき、一覧では愛称を出さない（名称と半角/全角 i やヶ/ケ の差しかない重複を隠す）。
     */
    public static function nicknameMatchesName(?string $nickname, ?string $name): bool
    {
        if (! is_string($nickname) || $nickname === '' || ! is_string($name)) {
            return false;
        }

        $key = self::normalizationKey($nickname);

        return $key !== '' && $key === self::normalizationKey(self::nameCore($name));
    }
}
