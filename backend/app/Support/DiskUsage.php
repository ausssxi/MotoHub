<?php

declare(strict_types=1);

namespace App\Support;

/**
 * ディスク使用率の取得。
 *
 * もとは system:check-disk（CheckDisk コマンド）の private 実装だったが、
 * ops:daily-report からも同じ値が要る。取得方法が2箇所に分かれると、片方だけ直したときに
 * 「日次サマリの数字」と「しきい値アラートの数字」が食い違うため、ここへ一本化する。
 *
 * storage_path() を対象に disk_free_space()/disk_total_space() で算出する。
 * 本番では backend/ がホストの /dev/vda2 上のバインドマウントのため、この値でホストの
 * ディスク逼迫を検知できる。du は 42GB ツリーで数分ハングするため一切使わない（統計値のみ）。
 */
final class DiskUsage
{
    /**
     * 現在のディスク使用状況。取得できない場合は null。
     *
     * @return array{path: string, total: float, free: float, used: float, used_percent: int}|null
     */
    public static function current(?string $path = null): ?array
    {
        $path ??= storage_path();

        $total = disk_total_space($path);
        $free = disk_free_space($path);

        if ($total === false || $free === false || $total <= 0) {
            return null;
        }

        $used = $total - $free;

        return [
            'path' => $path,
            'total' => $total,
            'free' => $free,
            'used' => $used,
            'used_percent' => (int) round($used / $total * 100),
        ];
    }

    /**
     * バイト数を人間可読形式（B/KB/MB/GB/TB）に整形する。
     */
    public static function humanize(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
