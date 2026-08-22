<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 楽天商品検索APIへ渡す検索キーワードの正規化。
 *
 * 楽天は「スペース区切りの各単語が2文字以上」でないと {"error_description":"keyword is not valid"}（400）を返す。
 * 車種名に含まれる単独の "R"/"S"（例: 「R nineT」「Panigale V4 R」）がそのまま渡ると毎回400になるため、
 * 楽天に渡す直前で半角英数1文字の語を落とす。日本語（かな・カナ・漢字）の1文字は制限対象外なので残す。
 */
final class RakutenKeyword
{
    /**
     * キーワードを正規化して返す。有効語（2文字以上）が1つも残らなければ null（＝楽天を呼ばずスキップ）。
     */
    public static function normalize(string $keyword): ?string
    {
        // 全角・半角の空白を区切りとし、連続空白は1つにまとめる（空トークンは捨てる）。
        $tokens = preg_split('/[\s\x{3000}]+/u', trim($keyword), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $kept = [];
        foreach ($tokens as $token) {
            // 半角英数字のみで構成された1文字トークンだけを除去（"r"/"s"/"v" 等）。
            // 日本語1文字・記号を含む語・2文字以上は残す（保守的に半角英数1文字だけ落とす）。
            if (mb_strlen($token) === 1 && preg_match('/^[0-9A-Za-z]$/', $token) === 1) {
                continue;
            }
            $kept[] = $token;
        }

        // 除去後、2文字以上の語が1つも残らなければ楽天を呼ぶ価値がない（無駄な400を避ける）。
        $hasValid = false;
        foreach ($kept as $token) {
            if (mb_strlen($token) >= 2) {
                $hasValid = true;
                break;
            }
        }

        if (! $hasValid) {
            return null;
        }

        return implode(' ', $kept);
    }
}
