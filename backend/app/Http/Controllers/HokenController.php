<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * 保険ハブ（/hoken・恒久slug）。[[ShinkijunGentsukiController]] の型を踏襲。
 * ★情報提供に徹する（保険募集行為はしない）。固定額（軽自動車税・自賠責）は config/insurance.php
 *   から出典付きで表示。任意保険の額は出さず一括見積もりCTA（config未設定時は非表示）へ誘導。
 */
final class HokenController extends Controller
{
    public function show(): View
    {
        // 早見表（区分×税×自賠責12ヶ月）。config の固定額のみ＝DBアクセス不要。
        $brackets = collect(config('insurance.brackets'))
            ->map(function (array $b, string $key): array {
                $jib = config('insurance.jibaiseki.'.$b['jibaiseki']);

                return [
                    'key' => $key,
                    'label' => $b['label'],
                    'tax' => $b['tax'],
                    'jibaiseki_12' => $jib['terms'][12] ?? null,
                    'family_tokuyaku' => $b['family_tokuyaku'],
                    'shaken' => $b['shaken'],
                ];
            })
            ->values();

        return view('hoken', [
            'brackets' => $brackets,
            'sources' => config('insurance.sources'),
            'lastVerified' => config('insurance.last_verified'),
            'revisionNote' => config('insurance.jibaiseki_revision_note'),
            'affiliate' => config('insurance.affiliate'),
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
                'q' => 'バイクの任意保険は必要ですか？',
                'a' => '自賠責保険（強制保険）は対人賠償のみで上限もあり、物損や自分のケガ、上限超の対人賠償は補償されません。事故の賠償は高額になりうるため、対人・対物を無制限にできる任意保険での上乗せが実務的に重要です。加入は任意ですが、多くのライダーが加入しています。',
            ],
            [
                'q' => 'ファミリーバイク特約とは？',
                'a' => '自動車保険に付帯できる特約で、125cc以下の原付（原付一種・原付二種、2025年の新基準原付を含む）が対象です。等級に影響せず、複数台でも保険料が定額なのが特徴です。125cc以下を所有し家族が自動車保険に入っている場合は、単独の任意保険より割安になることがあります。',
            ],
            [
                'q' => '新基準原付の保険はどうなりますか？',
                'a' => '2025年の新基準原付は総排気量125cc以下ですが最高出力を制御して原付一種として扱われます。軽自動車税は原付一種と同じ年額2,000円で、ファミリーバイク特約の対象にもなります。自賠責保険は原付（125cc以下）の料率が適用されます。',
            ],
            [
                'q' => '自賠責保険料はいつ変わりますか？',
                'a' => '自賠責保険料は損害保険料率算出機構の基準料率に基づき、全国一律です。2026年11月1日に改定（全車種平均で約6.2%引き上げ）が予定されています。契約期間が長いほど1年あたりは割安になります。',
            ],
        ];
    }
}
