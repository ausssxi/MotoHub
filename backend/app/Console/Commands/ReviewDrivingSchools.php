<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DrivingSchool;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * 教習所データの棚卸し（sweep 用リスト出力）。
 *
 * - 要再確認 : status=open の公開校で verified_at が --days より古いもの（確認が古い）。
 * - 再開候補 : status=nirin_suspended（二輪一時停止）の校。再開していないか公式を見に行く対象。
 * - 廃業     : status=closed の校（参考表示）。
 *
 * 各行は「都道府県 / 校名 / 市区町村 / verified_at / official_url」。そのまま
 * 公式サイト巡回（sweep）の対象リストとして使える形式で出す。
 */
class ReviewDrivingSchools extends Command
{
    protected $signature = 'schools:review {--days=90 : 「要再確認」とみなす verified_at の経過日数}';

    protected $description = '教習所データの棚卸し（要再確認/再開候補/廃業）を一覧出力する';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::today()->subDays($days);

        // 要再確認: 公開中(open)かつ確認が古い
        $stale = DrivingSchool::query()
            ->where('status', DrivingSchool::STATUS_OPEN)
            ->whereNotNull('verified_at')
            ->whereDate('verified_at', '<=', $cutoff)
            ->orderBy('verified_at')
            ->orderBy('prefecture_slug')
            ->get();

        // 再開候補: 二輪一時停止
        $suspended = DrivingSchool::query()
            ->where('status', DrivingSchool::STATUS_NIRIN_SUSPENDED)
            ->orderBy('verified_at')
            ->orderBy('prefecture_slug')
            ->get();

        // 廃業(参考)
        $closed = DrivingSchool::query()
            ->where('status', DrivingSchool::STATUS_CLOSED)
            ->orderBy('verified_at')
            ->orderBy('prefecture_slug')
            ->get();

        $this->section("要再確認（status=open・verified_at が {$days} 日より古い / 基準日 {$cutoff->toDateString()}）", $stale);
        $this->section('再開候補（status=nirin_suspended・二輪の再開を確認しに行く対象）', $suspended);
        $this->section('廃業（status=closed・参考）', $closed);

        return self::SUCCESS;
    }

    /**
     * 1セクションを出力する。
     *
     * @param  \Illuminate\Support\Collection<int, DrivingSchool>  $rows
     */
    private function section(string $title, $rows): void
    {
        $this->newLine();
        $this->line("=== {$title} — {$rows->count()}件 ===");

        if ($rows->isEmpty()) {
            $this->line('  （該当なし）');

            return;
        }

        foreach ($rows as $s) {
            $verified = $s->verified_at?->toDateString() ?? '(未確認)';
            $url = $s->official_url ?: '(URLなし)';
            // タブ区切り: そのまま sweep 対象リストとして使える
            $this->line(implode("\t", [
                $s->prefecture,
                $s->name,
                $s->city,
                $verified,
                $url,
            ]));
        }
    }
}
