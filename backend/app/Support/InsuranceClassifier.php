<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BikeModel;

/**
 * 車種を保険/税の区分（ブラケット）に分類する。config/insurance.php と 新基準原付config を参照。
 * ★正確性が命: 排気量が不明(null/0)なら null を返し、車種ページ側は維持費ブロックを出さない
 *   （曖昧な区分で誤った固定額を出さないため）。
 */
final class InsuranceClassifier
{
    /**
     * 車種のブラケットキーを返す。判定不能（排気量不明）なら null。
     * 新基準原付（config/shinkijun.target_models 該当）は排気量に関わらず原付一種扱い。
     */
    public static function bracketKeyForModel(BikeModel $model): ?string
    {
        // 新基準原付は最優先（125ccでも原付一種扱い＝独自性の核）
        if (in_array($model->name, config('shinkijun.target_models', []), true)) {
            return 'shinkijun';
        }

        $cc = (int) ($model->displacement ?? 0);
        if ($cc <= 0) {
            return null; // 排気量不明＝断定しない
        }

        return match (true) {
            $cc <= 50 => 'gentsuki_1',
            $cc <= 90 => 'gentsuki_2_90',
            $cc <= 125 => 'gentsuki_2_125',
            $cc <= 250 => 'kei_nirin',
            default => 'kogata_nirin',
        };
    }

    /**
     * ブラケット定義（label/tax/jibaiseki/family_tokuyaku/shaken 等）を返す。判定不能なら null。
     *
     * @return array<string, mixed>|null
     */
    public static function bracketForModel(BikeModel $model): ?array
    {
        $key = self::bracketKeyForModel($model);
        if ($key === null) {
            return null;
        }

        $bracket = config('insurance.brackets.'.$key);
        if (! is_array($bracket)) {
            return null;
        }

        // 自賠責の料金表（区分に対応）を同梱して返す（partial 側の参照を簡潔に）。
        $bracket['key'] = $key;
        $bracket['jibaiseki_table'] = config('insurance.jibaiseki.'.$bracket['jibaiseki']);

        return $bracket;
    }
}
