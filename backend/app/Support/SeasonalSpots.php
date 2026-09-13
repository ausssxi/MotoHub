<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TouringSpot;

/**
 * 季節キーワード（例「紅葉」）で touring_spots を確度別に集める汎用ロジック。
 *
 * 確度スコア（ビューで判定しない・キーワードもここに持たない＝呼び出し側が渡す）:
 *   3  recommended_season に含む（見頃の時期まで書かれている。最も確実）
 *   2  name または description に含む（概要で触れている。確実）
 *   1  content にだけ含む（本文に一度出るだけ。弱い＝テキストリンクで軽く出す）
 *
 * ★ content は longText。スコア1判定のために全文を読み込むと重いので、
 *    LIKE を SELECT の真偽式に落として content 本文はロードしない。
 */
final class SeasonalSpots
{
    /** 8地方区分 → 所属都道府県（フルネーム＝touring_spots.prefecture と一致）。表示順もこの順。 */
    private const REGIONS = [
        '北海道' => ['北海道'],
        '東北' => ['青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県'],
        '関東' => ['茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県'],
        '中部' => ['新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県', '静岡県', '愛知県'],
        '近畿' => ['三重県', '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県'],
        '中国' => ['鳥取県', '島根県', '岡山県', '広島県', '山口県'],
        '四国' => ['徳島県', '香川県', '愛媛県', '高知県'],
        '九州' => ['福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'],
    ];

    /**
     * キーワードで確度別に集める。1クエリで取得し PHP 側でスコア分け・エリア分けする。
     *
     * @return array{
     *   score3: array<int, array<string,mixed>>,
     *   score2_areas: array<int, array{region:string, items:array<int, array<string,mixed>>}>,
     *   score1: array<int, array<string,mixed>>,
     *   total: int
     * }
     */
    public static function forKeyword(string $keyword): array
    {
        $like = '%'.$keyword.'%';

        // content 本文は読み込まず、マッチ判定だけ真偽式で取り出す（スコア分け用）。
        $rows = TouringSpot::query()
            ->select('id', 'prefecture', 'slug', 'name', 'description', 'recommended_season', 'image_url')
            ->selectRaw('(recommended_season LIKE ?) as m_season', [$like])
            ->selectRaw('(name LIKE ? OR description LIKE ?) as m_desc', [$like, $like])
            ->where(function ($q) use ($like) {
                $q->where('recommended_season', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('content', 'like', $like);
            })
            ->orderBy('name')
            ->get();

        $score3 = [];
        $score2ByRegion = [];
        $score1 = [];

        foreach ($rows as $s) {
            $score = ((int) $s->m_season) === 1 ? 3 : (((int) $s->m_desc) === 1 ? 2 : 1);

            if ($score === 3) {
                $score3[] = self::card($s, withSeason: true);
            } elseif ($score === 2) {
                $region = self::regionOf((string) $s->prefecture);
                $score2ByRegion[$region][] = self::card($s);
            } else {
                $score1[] = self::linkItem($s);
            }
        }

        // エリアは規定順・0件は落とす。
        $score2Areas = [];
        foreach (array_keys(self::REGIONS) as $region) {
            if (! empty($score2ByRegion[$region])) {
                $score2Areas[] = ['region' => $region, 'items' => $score2ByRegion[$region]];
            }
        }

        return [
            'score3' => $score3,
            'score2_areas' => $score2Areas,
            'score1' => $score1,
            'total' => $rows->count(),
        ];
    }

    /** カード用の素配列（score3/2）。 */
    private static function card(TouringSpot $s, bool $withSeason = false): array
    {
        return [
            'name' => (string) $s->name,
            'prefecture' => (string) $s->prefecture,
            'description' => self::excerpt($s->description),
            'image_url' => filled($s->image_url) ? (string) $s->image_url : null,
            'season' => $withSeason ? trim((string) $s->recommended_season) : null,
            'url' => self::url($s),
        ];
    }

    /** テキストリンク用の軽い配列（score1）。 */
    private static function linkItem(TouringSpot $s): array
    {
        return [
            'name' => (string) $s->name,
            'prefecture' => (string) $s->prefecture,
            'url' => self::url($s),
        ];
    }

    private static function url(TouringSpot $s): ?string
    {
        $prefSlug = TouringSpot::slugFromPrefectureName((string) $s->prefecture);
        if ($prefSlug === null || ! filled($s->slug)) {
            return null; // 404 を作らない（リンク不能はテキスト表示にフォールバック）
        }

        return route('touring.spot.show', ['prefectureSlug' => $prefSlug, 'spot' => $s->slug]);
    }

    private static function excerpt(?string $description): ?string
    {
        $d = trim((string) $description);
        if ($d === '') {
            return null;
        }

        return mb_substr($d, 0, 80);
    }

    private static function regionOf(string $prefecture): string
    {
        foreach (self::REGIONS as $region => $prefs) {
            if (in_array($prefecture, $prefs, true)) {
                return $region;
            }
        }

        return 'その他';
    }
}
