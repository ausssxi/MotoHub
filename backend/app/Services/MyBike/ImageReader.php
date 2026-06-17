<?php

declare(strict_types=1);

namespace App\Services\MyBike;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;

/**
 * アップロード画像を GD(Intervention) で読み込む。
 * GD が読めない形式（HEIC/iPhone 等）は Imagick でデコードして JPEG blob に変換し、
 * その blob を GD パスへ合流させる（HEIC 独自パスを作らない＝EXIF 除去は後段 toJpeg に一本化）。
 */
final class ImageReader
{
    /**
     * @throws RuntimeException デコード不能（未対応形式・破損）
     */
    public function read(string $path): ImageInterface
    {
        $manager = new ImageManager(new Driver);

        try {
            return $manager->read($path);
        } catch (\Throwable $e) {
            // GD 非対応（HEIC 等）→ Imagick でデコードして JPEG blob にしてから GD へ
            $jpeg = $this->heicToJpegBlob($path);
            if ($jpeg === null) {
                throw new RuntimeException('画像を読み込めませんでした（対応形式: JPEG / PNG / WebP / HEIC）。', 0, $e);
            }

            return $manager->read($jpeg);
        }
    }

    /**
     * HEIC 等を Imagick で JPEG blob に変換（先頭フレームのみ）。imagick 未導入や失敗時は null。
     * ★ここでは EXIF を残しても良い（中間 blob・保存しない）。除去は後段の GD toJpeg で一本化される。
     */
    private function heicToJpegBlob(string $path): ?string
    {
        if (! class_exists(\Imagick::class)) {
            return null;
        }

        try {
            $im = new \Imagick;
            $im->readImage($path);
            $im->setFirstIterator(); // HEIC が複数フレームの場合は先頭のみ
            $im->setImageFormat('jpeg');
            $blob = $im->getImageBlob();
            $im->clear();
            $im->destroy();

            return $blob;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
