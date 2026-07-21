<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\TheftStats;
use Illuminate\View\View;

/**
 * バイク盗難データハブ（/theft・恒久slug）。HokenController の型を踏襲。
 * ★都道府県別は機械可読データが無いため断念し、「全国のオートバイ盗トレンド1枚」に縮小（案A）。
 *   統計は警察庁犯罪統計（e-Stat）を出典付きで表示。盗難保険CTAは config/theft.php の affiliate
 *   （env未設定時は非表示・偽ボタンを出さない）。数値は表示時に TheftStats（静的JSON）から算出＝bump不要。
 */
final class TheftController extends Controller
{
    public function show(): View
    {
        return view('theft', [
            'hasData' => TheftStats::hasData(),
            'latest' => TheftStats::latest(),
            'series' => TheftStats::series(),
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
                'q' => 'バイクの盗難（オートバイ盗）は増えていますか？',
                'a' => '警察庁の犯罪統計によると、オートバイ盗の全国の認知件数は長期的には減少傾向が続いています。ただし年により増減があり、依然として一定数の被害が発生しています。本ページで最新年の件数と推移を確認できます。',
            ],
            [
                'q' => 'バイクの盗難対策で効果的なものは？',
                'a' => '動かせない構造物に施錠する「地球ロック」、種類の異なる複数ロックの併用、屋内保管や防犯カメラのある駐輪、車体カバーやアラームの使用が有効とされています。解錠や運び出しの手間を増やすことが抑止につながります。',
            ],
            [
                'q' => '盗難に備える保険はありますか？',
                'a' => 'バイクの任意保険の車両補償や、盗難・いたずらに対応する専用の盗難保険があります。補償範囲・保険料は車種や条件で異なるため、各社の公式情報でご確認ください。',
            ],
            [
                'q' => 'この盗難データの出典は？',
                'a' => '警察庁『犯罪統計』（e-Stat）のオートバイ盗（全国）の認知・検挙件数です。検挙率は検挙件数÷認知件数、前年比は前年の認知件数との比較として算出しています。',
            ],
        ];
    }
}
