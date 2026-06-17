<?php

declare(strict_types=1);

namespace App\Services\MyBike;

use App\Models\MaintenanceLog;
use App\Models\MyBike;
use App\Models\MyBikeImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use RuntimeException;

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
        $path = 'garage/'.$myBike->id.'/'.Str::uuid()->toString().'.jpg';
        Storage::disk($this->disk())->put($path, $this->encodeStripped($file));

        $nextOrder = (int) ($myBike->images()->max('sort_order') ?? -1) + 1;

        return $myBike->images()->create([
            'path' => $path,
            'caption' => $caption !== null && $caption !== '' ? $caption : null,
            'sort_order' => $nextOrder,
        ]);
    }

    /**
     * 記録（整備/カスタム）の添付上限に達しているか。
     */
    public function recordAtLimit(MaintenanceLog $record): bool
    {
        return $record->images()->count() >= (int) config('garage.max_record_images', 10);
    }

    /**
     * 記録に写真を1枚添付（EXIF/GPS除去・非公開ディスク保存・my_bike_id も保持）。
     */
    public function addToRecord(MaintenanceLog $record, UploadedFile $file): MyBikeImage
    {
        $path = 'garage/'.$record->my_bike_id.'/records/'.$record->id.'/'.Str::uuid()->toString().'.jpg';
        Storage::disk($this->disk())->put($path, $this->encodeStripped($file));

        $nextOrder = (int) ($record->images()->max('sort_order') ?? -1) + 1;

        return $record->images()->create([
            'my_bike_id' => $record->my_bike_id,
            'path' => $path,
            'sort_order' => $nextOrder,
        ]);
    }

    /**
     * 記録の添付写真を1枚削除（行＋実ファイル）。指定記録に属する画像のみ対象。
     */
    public function deleteRecordImage(MaintenanceLog $record, int $imageId): void
    {
        $record->images()->findOrFail($imageId)->delete();
    }

    /**
     * アップロード画像を「EXIF回転焼き込み＋長辺リサイズ＋JPEG再エンコード」して bytes 化。
     * ★再エンコードで EXIF/GPS メタは完全に除去される（privacy の肝）。読込不可(HEIC等)は例外。
     */
    private function encodeStripped(UploadedFile $file): string
    {
        try {
            $manager = new ImageManager(new Driver);
            $image = $manager->read($file->getRealPath());
        } catch (\Throwable $e) {
            // GD は HEIC をデコードできない。対応形式に変換して再アップロードを促す（呼び出し側で422）。
            throw new RuntimeException('画像を読み込めませんでした（対応形式: JPEG / PNG / WebP）。', 0, $e);
        }

        $image->orient(); // EXIF回転を実ピクセルへ適用（スマホ写真の横倒れ防止）
        $maxEdge = (int) config('garage.resize_max_edge');
        $image->scaleDown($maxEdge, $maxEdge); // 長辺を箱に収める（アスペクト維持・拡大なし）

        return (string) $image->toJpeg((int) config('garage.jpeg_quality'));
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
