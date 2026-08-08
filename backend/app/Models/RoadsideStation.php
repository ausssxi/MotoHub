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
        $nickname = $this->nickname;
        if (! is_string($nickname) || $nickname === '') {
            return [];
        }

        $parts = array_map('trim', explode('|', $nickname));
        $parts = array_filter($parts, static fn (string $p): bool => $p !== '');

        return array_values(array_unique($parts));
    }
}
