<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\TheftStats;
use Illuminate\View\View;

/**
 * バイク盗難データハブ（/theft・恒久slug）。HokenController の型を踏襲。
 * ★情報提供に徹する（不安を過度に煽らない）。統計は警察庁犯罪統計（第9表）を出典付きで表示。
 *   盗難保険CTAは config/theft.php の affiliate（env未設定時は非表示・偽ボタンを出さない）。
 * 統計値は表示時に TheftStats（静的JSON）から算出＝DB非依存・キャッシュbump不要。
 */
final class TheftController extends Controller
{
    public function show(): View
    {
        return view('theft', [
            'hasData' => TheftStats::hasData(),
            'ranking' => TheftStats::rankingTable(),
            'nationalSeries' => TheftStats::nationalSeries(),
            'source' => TheftStats::sourceMeta(),
            'affiliate' => config('theft.affiliate'),
            'faqs' => $this->faqs(),
        ]);
    }

    /**
     * FAQPage schema 兼 本文用。一般論のみ（特定商品の推奨・比較はしない）。
     *
     * @return array<int, array{q: string, a: string}>
     */
    private function faqs(): array
    {
        return [
            [
                'q' => 'バイクの盗難が多い都道府県は？',
                'a' => '警察庁の犯罪統計（街頭犯罪 都道府県別・オートバイ盗）では、人口や登録台数の多い都市部で認知件数が多い傾向があります。件数は年により変動するため、本ページで最新の都道府県別ランキングを確認できます。',
            ],
            [
                'q' => 'バイクの盗難対策で効果的なものは？',
                'a' => '動かせない構造物に施錠する「地球ロック」、種類の異なる複数ロックの併用、屋内保管や防犯カメラのある駐輪、車体カバーの使用が有効とされています。物理的な手間を増やすことが抑止につながります。',
            ],
            [
                'q' => '盗難に備える保険はありますか？',
                'a' => 'バイクの任意保険の車両補償や、盗難・いたずらに対応する専用の盗難保険があります。補償範囲・保険料は車種や条件で異なるため、各社の公式情報でご確認ください。',
            ],
            [
                'q' => 'この盗難データの出典は？',
                'a' => '警察庁『犯罪統計』第9表「街頭犯罪等 都道府県別」（e-Stat）のオートバイ盗の認知・検挙件数です。検挙率・全国順位はこの認知・検挙件数から算出しています。',
            ],
        ];
    }
}
