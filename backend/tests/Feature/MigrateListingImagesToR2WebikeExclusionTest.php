<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

/**
 * listings:migrate-images-to-r2 が掲載停止サイト（webike）を R2 へ移行しないことの回帰テスト。
 * 権利者（ウェビック）より 2026-08-10 付で「取得済の画像も含めた掲載の停止」を要請され承諾済み。
 */
beforeEach(function () {
    // 誤って空ディスクへ書かない早期エラーを通すため、endpoint を設定しておく。
    config(['filesystems.disks.r2_images.endpoint' => 'https://fake.r2.example']);
});

/** 各サイトに1ファイルずつ在庫画像を置く（Storage::fake を使うケース専用）。 */
function seedListingImages(): void
{
    Storage::fake('public');
    Storage::fake('r2_images');
    Storage::disk('public')->put('listings/goobike/00/100/0.webp', 'GOO');
    Storage::disk('public')->put('listings/bds/00/200/0.webp', 'BDS');
    Storage::disk('public')->put('listings/webike/00/300/0.webp', 'WEBIKE');
}

it('--site=webike を指定するとエラーで止まり、何も移行しない', function () {
    // Storage を touch する前（handle 冒頭）で弾けること。fake 不要＝環境非依存で確認できる。
    $this->artisan('listings:migrate-images-to-r2', ['--site' => 'webike'])
        ->assertFailed();
});

it('--site 無指定でも webike を R2 へ移行しない', function () {
    seedListingImages();

    $this->artisan('listings:migrate-images-to-r2')
        ->assertSuccessful();

    // goobike / bds は移行される。
    Storage::disk('r2_images')->assertExists('listings/goobike/00/100/0.webp');
    Storage::disk('r2_images')->assertExists('listings/bds/00/200/0.webp');

    // webike は移行されない。
    Storage::disk('r2_images')->assertMissing('listings/webike/00/300/0.webp');

    // ローカルの元ファイルは一切消さない（純粋コピー）。
    Storage::disk('public')->assertExists('listings/webike/00/300/0.webp');
})->skip(fn () => ! canFakeDisks(), 'Storage::fake がこの環境で使えない（storage 権限）');

it('--dry-run でも webike を対象に数えない', function () {
    seedListingImages();
    // webike だけを残して他サイトを消し、dry-run の「転送予定 0 件」を確認する。
    Storage::disk('public')->delete('listings/goobike/00/100/0.webp');
    Storage::disk('public')->delete('listings/bds/00/200/0.webp');

    $this->artisan('listings:migrate-images-to-r2', ['--dry-run' => true])
        ->expectsOutputToContain('転送予定')
        ->assertSuccessful();

    Storage::disk('r2_images')->assertMissing('listings/webike/00/300/0.webp');
})->skip(fn () => ! canFakeDisks(), 'Storage::fake がこの環境で使えない（storage 権限）');

/** Storage::fake がこの実行環境で機能するか（CI/本番では true、権限制約のある CC 環境では false）。 */
function canFakeDisks(): bool
{
    try {
        Storage::fake('r2_images');
        Storage::disk('r2_images')->put('__probe', 'x');

        return Storage::disk('r2_images')->exists('__probe');
    } catch (\Throwable $e) {
        return false;
    }
}
