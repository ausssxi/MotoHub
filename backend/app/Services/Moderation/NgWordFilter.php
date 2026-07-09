<?php

declare(strict_types=1);

namespace App\Services\Moderation;

/**
 * NGワード照合。UGC投稿（口コミ・将来はレビュー）を保存前に検査する共有ロジック。
 * リストは config/ng_words.php（運営が編集可）。部分一致・大小文字無視。
 *
 * ⚠️ ヒット語は呼び出し側でユーザーに開示しない（回避を防ぐため）。中立な文言で弾く。
 */
final class NgWordFilter
{
    /**
     * @param  array<int,string>|null  $words  テスト等での上書き用。null なら config を読む。
     */
    public function __construct(private readonly ?array $words = null) {}

    /**
     * テキストにNGワードが含まれるか。空文字・null は false。
     */
    public function contains(?string $text): bool
    {
        return $this->firstMatch($text) !== null;
    }

    /**
     * 最初にヒットしたNGワードを返す（ログ・管理用。ユーザーには見せない）。null=ヒットなし。
     */
    public function firstMatch(?string $text): ?string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }

        $haystack = mb_strtolower($text);

        foreach ($this->words() as $word) {
            $word = (string) $word;
            if ($word === '') {
                continue;
            }
            if (mb_strpos($haystack, mb_strtolower($word)) !== false) {
                return $word;
            }
        }

        return null;
    }

    /**
     * @return array<int,string>
     */
    private function words(): array
    {
        if ($this->words !== null) {
            return $this->words;
        }

        return (array) config('ng_words.words', []);
    }
}
