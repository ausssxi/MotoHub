<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 自賠責2026年11月値上げ記事の統合による恒久リダイレクト（カニバリ解消）。
 * 後発 slug -2026-11 を SEO評価のある -2026-bike へ 301 で寄せる。
 * このリダイレクトは blog/{slug} catch-all より前で解決されるため、DB上に記事が
 * 存在しなくても（draftに戻しても）成立する。
 */
it('301s the merged jibaiseki-2026-11 slug to the canonical -2026-bike article', function () {
    $this->get('/blog/jibaiseki-neage-2026-11')
        ->assertStatus(301)
        ->assertRedirect('/blog/jibaiseki-neage-2026-bike');
});
