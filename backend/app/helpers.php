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

if (! function_exists('listing_image_url')) {
    /**
     * 在庫画像（listings/{site}/{shard}/{id}/{index}.{ext}）の相対パスから
     * 公開URLを組み立てる単一の切替口。
     *
     * DB の local_image_paths には "listings/..." の相対パスが入る。表示側は
     * この関数だけを通すことで、保存先をローカル(public)⇔R2(r2_images)で
     * 切り替えられる。切替は env('LISTING_IMAGE_DISK') の1箇所で行う。
     *
     *   'public'    → asset('storage/'.$path)（＝従来と完全に同一のURL・既定）
     *   'r2_images' → https://img.motohub.jp/{path}（R2 Custom Domain）
     *
     * 既定は 'public'。未設定なら必ず現状と同一のURL文字列を返す。
     *
     * @param  string  $path  "listings/..." の相対パス
     */
    function listing_image_url(string $path): string
    {
        $path = ltrim($path, '/');

        if (env('LISTING_IMAGE_DISK', 'public') === 'r2_images') {
            $base = rtrim((string) config('filesystems.disks.r2_images.url'), '/');

            return $base.'/'.$path;
        }

        return asset('storage/'.$path);
    }
}

if (! function_exists('diagnosis_repair_slugs')) {
    /**
     * 症状診断ツール（/trouble）の答えカードが深掘り先として指すブログ記事の
     * slug 一覧を返す ＝「修理記事」の単一の真実。
     *
     * config('diagnosis.cards') の article（/blog/{slug}）から導出するため、
     * タグやカラムに依存しない。trouble-cta / trouble-related コンポーネントと
     * show.blade.php の重複除外で共有する。
     *
     * @return list<string>
     */
    function diagnosis_repair_slugs(): array
    {
        return collect(config('diagnosis.cards', []))
            ->pluck('article')
            ->filter()
            ->map(fn ($p) => ltrim(str_replace('/blog/', '', (string) $p), '/'))
            ->unique()
            ->values()
            ->all();
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
