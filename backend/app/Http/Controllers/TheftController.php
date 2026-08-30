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
        $crossLinks = [
            ['label' => '駐車場マップ', 'url' => route('parking.index'), 'icon' => 'square-parking', 'description' => '全国の駐車場を探す'],
            ['label' => 'バイクショップを探す', 'url' => route('shops.map'), 'icon' => 'store', 'description' => '近くのショップを探す'],
            ['label' => '中古バイク検索', 'url' => route('bikes.search'), 'icon' => 'search', 'description' => '全国の在庫を検索'],
        ];

        return view('theft', [
            'hasData' => TheftStats::hasData(),
            'latest' => TheftStats::latest(),
            'series' => TheftStats::series(),
            'source' => TheftStats::sourceMeta(),
            'affiliate' => config('theft.affiliate'),
            'faqs' => $this->faqs(),
            'crossLinks' => $crossLinks,
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
                // ★回答文はグラフと同じ theft_stats.json（national）から導出し、本文とデータの食い違いを防ぐ。
                //   数値・トレンドを直書きしない（年次更新で再び矛盾するため）。theftTrendAnswer() を参照。
                'a' => $this->theftTrendAnswer(),
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

    /**
     * 「盗難は増えている？」の回答文を、ページのグラフと同一の theft_stats.json（national）から導出する。
     *
     * ★グラフと同じ出所から導出し、本文とデータの食い違いを防ぐ。数値やトレンドを本文に直書きすると
     *   年次データ更新で再び食い違うため、必ず TheftStats::series() から組み立てる。
     * ・言及するのは「本ページに掲載している期間」だけ。ページに載っていない長期トレンドには触れない。
     * ・増減の向きは端点（最初の年↔最後の年）の比較で決め、単調増減でなくても事実として破綻しないようにする。
     *   期間内に増減が混在する場合は「増減を伴いながら」と言い添える。
     * ・この文言は $faqs 経由で本文（theft.blade.php）と FAQPage の JSON-LD の両方に使われる＝両者は常に一致する。
     */
    private function theftTrendAnswer(): string
    {
        $series = TheftStats::series();

        // データ未投入時は数値に依存しない安全な一般文（ハブ側は hasData()=false で「準備中」表示）。
        if ($series === []) {
            return '本ページで最新のオートバイ盗（全国）の認知件数と推移を確認できます。';
        }

        $first = $series[0];
        $last = end($series);

        // 単一年のみの場合は推移を語らない（起点・終点が同じで「増加/減少」と言えないため）。
        if (count($series) === 1) {
            return sprintf(
                '本ページに掲載している%d年のオートバイ盗（全国）の認知件数は%s件です。',
                $first['year'],
                number_format($first['recognized']),
            );
        }

        // 端点比較で増減の向きを決める（単調でなくても net の符号で一意に定まる）。
        $net = $last['recognized'] - $first['recognized'];
        $direction = $net > 0 ? '増加' : ($net < 0 ? '減少' : '横ばい');

        // 期間内で増減が混在するか（連続差分が net と逆符号を含むか）。混在時は言い回しを和らげる。
        $mixed = false;
        for ($i = 1, $n = count($series); $i < $n; $i++) {
            $step = $series[$i]['recognized'] - $series[$i - 1]['recognized'];
            if (($net > 0 && $step < 0) || ($net < 0 && $step > 0)) {
                $mixed = true;
                break;
            }
        }

        if ($direction === '横ばい') {
            $trend = sprintf(
                '本ページに掲載している%d年〜%d年では、オートバイ盗（全国）の認知件数は%s件から%s件でほぼ横ばいに推移しています。',
                $first['year'], $last['year'],
                number_format($first['recognized']), number_format($last['recognized']),
            );
        } else {
            $trend = sprintf(
                '本ページに掲載している%d年〜%d年では、オートバイ盗（全国）の認知件数は%s件から%s件へ%s%sしています。',
                $first['year'], $last['year'],
                number_format($first['recognized']), number_format($last['recognized']),
                $mixed ? '増減を伴いながら' : '', $direction,
            );
        }

        // 最新年の前年比（TheftStats::latest と同一算出・ページ表示と同じ符号/桁）。
        $latest = TheftStats::latest();
        $yoy = $latest['yoy_pct'] ?? null;
        $yoyText = $yoy !== null
            ? sprintf('最新年の%d年は前年比%s%%でした。', $last['year'], ($yoy > 0 ? '+' : '').number_format($yoy, 1))
            : '';

        return $trend.$yoyText;
    }
}
