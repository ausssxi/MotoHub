<?php

declare(strict_types=1);

namespace App\Services\MyBike;

use App\Models\MyBike;
use App\Models\MyBikeImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * 愛車ギャラリー画像（private）の保存・最適化・削除。
 * - 保存先は非公開ディスク（config('garage.image_disk')）。配信はowner-checkルート経由のみ。
 * - アップロード時に EXIF回転補正 + 長辺リサイズ + JPEG再エンコード（容量/向き/メタ除去）。
 */
final class MyBikeImageService
{
    private function disk(): string
    {
        return (string) config('garage.image_disk');
    }

    /**
     * 1台あたりの上限に達しているか。
     */
    public function atLimit(MyBike $myBike): bool
    {
        return $myBike->images()->count() >= (int) config('garage.max_images');
    }

    /**
     * 画像を1枚追加（最適化して非公開ディスクに保存し、行を作成）。
     */
    public function add(MyBike $myBike, UploadedFile $file, ?string $caption = null): MyBikeImage
    {
        $manager = new ImageManager(new Driver);
        $image = $manager->read($file->getRealPath());
        $image->orient(); // EXIF回転を実ピクセルへ適用（スマホ写真の横倒れ防止）

        $maxEdge = (int) config('garage.resize_max_edge');
        $image->scaleDown($maxEdge, $maxEdge); // 長辺を箱に収める（アスペクト維持・拡大なし）

        $encoded = $image->toJpeg((int) config('garage.jpeg_quality'));

        $path = 'garage/'.$myBike->id.'/'.Str::uuid()->toString().'.jpg';
        Storage::disk($this->disk())->put($path, (string) $encoded);

        $nextOrder = (int) ($myBike->images()->max('sort_order') ?? -1) + 1;

        return $myBike->images()->create([
            'path' => $path,
            'caption' => $caption !== null && $caption !== '' ? $caption : null,
            'sort_order' => $nextOrder,
        ]);
    }

    /**
     * 画像を1枚削除（行＋実ファイル）。指定愛車に属する画像のみ対象。
     */
    public function delete(MyBike $myBike, int $imageId): void
    {
        $image = $myBike->images()->findOrFail($imageId);
        $image->delete(); // MyBikeImage::deleting で実ファイルも削除
    }

    /**
     * キャプションを更新。指定愛車に属する画像のみ対象。
     */
    public function updateCaption(MyBike $myBike, int $imageId, ?string $caption): void
    {
        $image = $myBike->images()->findOrFail($imageId);
        $image->update([
            'caption' => $caption !== null && $caption !== '' ? $caption : null,
        ]);
    }
}
