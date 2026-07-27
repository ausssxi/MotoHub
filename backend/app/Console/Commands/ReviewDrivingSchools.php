<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DrivingSchool;
use App\Models\DrivingSchoolCourse;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * 教習所データの棚卸し（sweep 用リスト出力）。
 *
 * - 要再確認     : status=open の公開校で verified_at が --days より古いもの（確認が古い）。
 * - 再開候補     : status=nirin_suspended（二輪一時停止）の校。再開していないか公式を見に行く対象。
 * - 廃業         : status=closed の校（参考表示）。
 * - 機械判定     : verify_method=machine の校（人の確認より信頼度が一段低い＝優先確認）。
 * - 料金の要再確認: verified_at が --days より古い、または NULL の course 行（料金の確認が古い/未確認）。
 *
 * --tier=focus|nation で県を絞れる（focus=config の重点県のみ / nation=それ以外）。
 *
 * 各行は「都道府県 / 校名 / (市区町村 or コース) / verified_at / URL」。そのまま
 * 公式サイト巡回（sweep）の対象リストとして使える形式で出す。
 */
class ReviewDrivingSchools extends Command
{
    protected $signature = 'schools:review
        {--days=90 : 「要再確認」とみなす verified_at の経過日数}
        {--tier= : focus|nation で県を絞る（focus=重点層/四半期・nation=全国層/年1回）}';

    protected $description = '教習所データの棚卸し（要再確認/再開候補/廃業/機械判定/料金）を一覧出力する';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::today()->subDays($days);
        $tier = $this->option('tier');

        if ($tier !== null && ! in_array($tier, ['focus', 'nation'], true)) {
            $this->error('--tier は focus または nation のみです。');

            return self::FAILURE;
        }

        // tier フィルタ: focus=重点県のみ、nation=それ以外。未指定なら全県。
        $focus = config('driving_schools.focus_prefectures', []);
        $applyTier = function ($q, string $col = 'prefecture_slug') use ($tier, $focus) {
            if ($tier === 'focus') {
                $q->whereIn($col, $focus);
            } elseif ($tier === 'nation') {
                $q->whereNotIn($col, $focus);
            }

            return $q;
        };

        // 要再確認: 公開中(open)かつ確認が古い
        $stale = $applyTier(DrivingSchool::query()
            ->where('status', DrivingSchool::STATUS_OPEN)
            ->whereNotNull('verified_at')
            ->whereDate('verified_at', '<=', $cutoff))
            ->orderBy('verified_at')->orderBy('prefecture_slug')->get();

        // 再開候補: 二輪一時停止
        $suspended = $applyTier(DrivingSchool::query()
            ->where('status', DrivingSchool::STATUS_NIRIN_SUSPENDED))
            ->orderBy('verified_at')->orderBy('prefecture_slug')->get();

        // 廃業(参考)
        $closed = $applyTier(DrivingSchool::query()
            ->where('status', DrivingSchool::STATUS_CLOSED))
            ->orderBy('verified_at')->orderBy('prefecture_slug')->get();

        // 機械判定（要優先確認）: verify_method=machine は人の確認より信頼度が一段低い
        $machine = $applyTier(DrivingSchool::query()
            ->where('verify_method', DrivingSchool::VERIFY_MACHINE))
            ->orderBy('verified_at')->orderBy('prefecture_slug')->get();

        // 料金の要再確認: verified_at が古い or NULL の course（NULL を先頭に、次いで古い順）
        $staleCourses = DrivingSchoolCourse::query()
            ->with('drivingSchool')
            ->when($tier !== null, fn ($q) => $q->whereHas('drivingSchool', fn ($s) => $applyTier($s)))
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('verified_at')->orWhereDate('verified_at', '<=', $cutoff);
            })
            ->orderByRaw('verified_at IS NOT NULL')
            ->orderBy('verified_at')
            ->get();

        $tierLabel = $tier !== null ? "（tier={$tier}）" : '';
        $this->section("要再確認（status=open・verified_at が {$days} 日より古い / 基準日 {$cutoff->toDateString()}）{$tierLabel}", $stale);
        $this->section('再開候補（status=nirin_suspended・二輪の再開を確認しに行く対象）'.$tierLabel, $suspended);
        $this->section('廃業（status=closed・参考）'.$tierLabel, $closed);
        $this->section('機械判定（verify_method=machine・信頼度一段下・優先確認）'.$tierLabel, $machine);
        $this->courseSection("料金の要再確認（course の verified_at が {$days} 日より古い or 未確認）{$tierLabel}", $staleCourses);

        return self::SUCCESS;
    }

    /**
     * 料金(course)セクションを出力する。行=都道府県/校名/コース/verified_at/source_url。
     *
     * @param  \Illuminate\Support\Collection<int, DrivingSchoolCourse>  $rows
     */
    private function courseSection(string $title, $rows): void
    {
        $this->newLine();
        $this->line("=== {$title} — {$rows->count()}件 ===");

        if ($rows->isEmpty()) {
            $this->line('  （該当なし）');

            return;
        }

        foreach ($rows as $c) {
            $school = $c->drivingSchool;
            $this->line(implode("\t", [
                $school?->prefecture ?? '(不明)',
                $school?->name ?? '(不明)',
                $c->label,
                $c->verified_at?->toDateString() ?? '(未確認)',
                $c->source_url ?: '(URLなし)',
            ]));
        }
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
