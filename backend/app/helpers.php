<?php

declare(strict_types=1);

if (! function_exists('asset_buster')) {
    /**
     * 自前アセット（public/js, public/css 等）のキャッシュバスター値を返す。
     *
     * filemtime はファイル内容が変わっても mtime が更新されない環境
     * （rsync/CI のタイムスタンプ保持等）で値が進まず、immutable キャッシュが
     * 更新されない問題があった。内容ハッシュ（md5_file 先頭8桁）を使うことで
     * 内容が変わったときだけ ?v= が変化し、確実にキャッシュが無効化される。
     *
     * @param  string  $absPath  public_path() で得た絶対パス
     */
    function asset_buster(string $absPath): string
    {
        return is_file($absPath) ? substr(md5_file($absPath), 0, 8) : '0';
    }
}

if (! function_exists('hit_count_cap')) {
    /**
     * Meilisearch の pagination.maxTotalHits（ヒット件数の上限）を返す。
     * これ以上の件数はカウントが打ち切られるため、表示側で「N+」に丸める基準になる。
     */
    function hit_count_cap(): int
    {
        return (int) (config('scout.meilisearch.index-settings.listings.pagination.maxTotalHits') ?? 1000);
    }
}

if (! function_exists('format_hit_count')) {
    /**
     * 検索ヒット件数を表示用に整形する。
     * maxTotalHits 以上は Meilisearch 側で頭打ちになるため「50,000+」のように表示する。
     */
    function format_hit_count(?int $n): string
    {
        $n = (int) $n;
        $cap = hit_count_cap();

        return $n >= $cap ? number_format($cap) . '+' : number_format($n);
    }
}
