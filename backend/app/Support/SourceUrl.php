<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 適合表の出典URL（model_fitments.source_1_url / source_2_url）を表示するときのガード。
 *
 * 出典は52車種を手作業で登録しており（今後148車種へ拡大予定）、打ち間違いが起こりうる。
 * 実際に「ここにURL」という値が本番に出て、相対URLとして解釈され
 * motohub.jp/bikes/honda/ここにURL という自サイトの404へリンクしていた。
 *
 * そこで「http:// または https:// で始まる値だけをリンクにしてよい」という判定を1か所に集約する。
 * 複数のビュー（fitments/_disclaimer と maintenance-battery/plug/oil の各partial）が
 * 同じ判定を持つと片方だけ直し忘れるため、ここに寄せて全ビューから呼ぶ。
 *
 * ホワイトリスト方式（http/https で始まるものだけ許可）なので、javascript: スキームや
 * 相対パス・プロトコル相対（//host）はすべて弾ける。
 */
final class SourceUrl
{
    /**
     * リンクにしてよい外部URLならその値を、そうでなければ null を返す。
     *
     * null が返った出典は、ビュー側でリンクにせず出典名のテキストだけを表示する。
     */
    public static function externalHref(?string $url): ?string
    {
        $url = trim((string) ($url ?? ''));

        if ($url === '') {
            return null;
        }

        // 先頭が http:// または https:// のときだけリンクにする（大小文字は問わない）。
        return preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }
}
