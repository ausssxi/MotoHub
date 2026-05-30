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
