<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Models\User;
use App\Services\MyBike\ImageReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * プロフィールアバター（public）の保存・最適化・削除。
 * - 画像処理は愛車写真の作法（[[MyBikeImageService]]）を流用: ImageReader(HEIC対応) →
 *   EXIF回転焼き込み → 正方形クロップ → JPEG再エンコード（★EXIF/GPS完全除去）。
 * - 保存先だけ public ディスクにする（アバターは全公開面に出るため owner配信は使えない）。
 * - 差し替え時は旧ファイルを必ず削除（ストレージにゴミを溜めない）。
 */
final class AvatarImageService
{
    public function __construct(private readonly ImageReader $reader) {}

    private function disk(): string
    {
        return (string) config('avatar.disk');
    }

    /**
     * 新しいアバターを保存し、users.avatar_path を更新する。旧ファイルがあれば削除。
     */
    public function update(User $user, UploadedFile $file): void
    {
        $old = $user->avatar_path;

        $path = config('avatar.dir').'/'.$user->id.'/'.Str::uuid()->toString().'.jpg';
        Storage::disk($this->disk())->put($path, $this->encodeSquare($file));

        $user->forceFill(['avatar_path' => $path])->save();

        // 差し替え成功後に旧ファイルを削除（新規保存が失敗したら旧アバターは温存される）。
        $this->deleteFile($old);
    }

    /**
     * アバターを外す（行を null 化＋実ファイル削除）。未設定なら何もしない。
     */
    public function remove(User $user): void
    {
        $old = $user->avatar_path;
        if ($old === null) {
            return;
        }

        $user->forceFill(['avatar_path' => null])->save();
        $this->deleteFile($old);
    }

    /**
     * アップロード画像を「EXIF回転焼き込み＋正方形クロップ＋JPEG再エンコード」して bytes 化。
     * ★再エンコードで EXIF/GPS は完全に除去される（privacy の肝）。読込不可(非画像/破損)は例外。
     */
    private function encodeSquare(UploadedFile $file): string
    {
        // HEIC は ImageReader が Imagick でデコードして GD パスへ合流。非画像/破損は RuntimeException。
        $image = $this->reader->read($file->getRealPath());

        $image->orient(); // EXIF回転を実ピクセルへ適用
        $size = (int) config('avatar.size');
        $image->coverDown($size, $size); // 中央を正方形にクロップ（拡大はしない）

        // 再エンコードで EXIF/GPS は完全に除去される（HEIC 由来でも同様）。
        return (string) $image->toJpeg((int) config('avatar.jpeg_quality'));
    }

    private function deleteFile(?string $path): void
    {
        if ($path !== null && Storage::disk($this->disk())->exists($path)) {
            Storage::disk($this->disk())->delete($path);
        }
    }
}
