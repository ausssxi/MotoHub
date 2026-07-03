<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Services\MyBike\ImageReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ショップ投稿画像の処理。
 * - 承認前は「非公開ディスク(local = storage/app/private)」にのみ置く（公開URL不到達）。
 * - 保存時に再エンコード（orient + 長辺1600 + JPEG q80）して EXIF/GPS を確実に除去。
 * - 承認時のみ公開ディスク(public)へ昇格し shops.local_image_path へ。
 */
final class ShopSubmissionImageService
{
    private const PENDING_DISK = 'local';    // storage/app/private（非公開）

    private const PENDING_DIR = 'shop-submissions';

    private const PUBLIC_DISK = 'public';    // storage/app/public（storage:link で公開）

    private const PUBLIC_DIR = 'shop-user';

    private const MAX_EDGE = 1600;

    private const JPEG_QUALITY = 80;

    public function __construct(private readonly ImageReader $reader) {}

    /**
     * アップロード画像を再エンコード（EXIF除去）して非公開ディスクへ保存。相対パスを返す。
     *
     * @throws \RuntimeException デコード不能（未対応形式・破損）
     */
    public function storePending(UploadedFile $file): string
    {
        $image = $this->reader->read($file->getRealPath()); // 読込不可は RuntimeException
        $image->orient();                                    // EXIF回転を実ピクセルへ
        $image->scaleDown(self::MAX_EDGE, self::MAX_EDGE);   // 長辺を1600に収める（拡大なし）
        $jpeg = (string) $image->toJpeg(self::JPEG_QUALITY); // 再エンコードで EXIF/GPS 完全除去

        $path = self::PENDING_DIR.'/'.Str::random(40).'.jpg';
        Storage::disk(self::PENDING_DISK)->put($path, $jpeg);

        return $path;
    }

    /**
     * 非公開の画像を公開ディスクへ移動し、shops.local_image_path 用の相対パスを返す。元は削除。
     */
    public function promoteToPublic(string $pendingPath): ?string
    {
        if (! Storage::disk(self::PENDING_DISK)->exists($pendingPath)) {
            return null;
        }

        $publicRel = self::PUBLIC_DIR.'/'.basename($pendingPath);
        Storage::disk(self::PUBLIC_DISK)->put($publicRel, Storage::disk(self::PENDING_DISK)->get($pendingPath));
        Storage::disk(self::PENDING_DISK)->delete($pendingPath);

        return $publicRel; // shops.local_image_path
    }

    /**
     * 非公開の画像を削除（却下・統合で既存画像ありのとき／孤児防止）。
     */
    public function deletePending(?string $pendingPath): void
    {
        if ($pendingPath && Storage::disk(self::PENDING_DISK)->exists($pendingPath)) {
            Storage::disk(self::PENDING_DISK)->delete($pendingPath);
        }
    }

    /** 非公開ディスク（プレビュー配信用にディスク名を公開）。 */
    public function pendingDisk(): string
    {
        return self::PENDING_DISK;
    }
}
