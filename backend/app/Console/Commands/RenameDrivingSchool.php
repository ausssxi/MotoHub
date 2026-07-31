<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DrivingSchool;
use Illuminate\Console\Command;

/**
 * 指定自動車教習所（driving_schools）の校名（name）を1件だけリネームする。
 *
 * - 照合キーは (prefecture_slug, name) の完全一致のみ。あいまい一致・LIKE はしない。
 * - 対象が 0 件／2 件以上なら中止（安全側。誤って複数行を巻き込まない）。
 * - 移動先の name が同一 prefecture_slug に既存なら中止（unique(prefecture_slug, name) 衝突回避）。
 * - 変更するのは name 列のみ。city / address / 緯度経度 / verified_at / status 等は読み取り表示のみ。
 * - 行の作成・削除は一切しない。--dry-run では DB を一切変更せず、差分だけを出力する。
 *
 * ImportDrivingSchoolAddresses（schools:import-address）と同じ作法（dry-run ガード・
 * 件数集計の出力）に揃えてある。
 */
class RenameDrivingSchool extends Command
{
    protected $signature = 'schools:rename
        {--pref= : prefecture_slug}
        {--from= : 現在の校名（完全一致）}
        {--to= : 新しい校名}
        {--dry-run : DBへ書き込まず結果のみ表示}';

    protected $description = '教習所の校名（name）を1件だけリネームする（照合キー = prefecture_slug + name 完全一致）';

    public function handle(): int
    {
        $pref = (string) ($this->option('pref') ?? '');
        $from = (string) ($this->option('from') ?? '');
        $to = (string) ($this->option('to') ?? '');
        $dryRun = (bool) $this->option('dry-run');

        // 1. pref・from・to のいずれかが空なら中止。
        if ($pref === '' || $from === '' || $to === '') {
            $this->error('--pref / --from / --to は必須です。');

            return self::FAILURE;
        }

        // 2. from と to が同一なら中止。
        if ($from === $to) {
            $this->error('--from と --to が同一です。');

            return self::FAILURE;
        }

        // 3. prefecture_slug と name の完全一致で対象を取得（LIKE・部分一致はしない）。
        $targets = DrivingSchool::query()
            ->where('prefecture_slug', $pref)
            ->where('name', $from)
            ->get();

        // 4. 0 件なら中止。
        if ($targets->isEmpty()) {
            $this->error("該当なし: {$pref} / {$from}");

            return self::FAILURE;
        }

        // 5. 2 件以上なら中止。
        if ($targets->count() > 1) {
            $this->error("複数該当のため中止: {$pref} / {$from}（{$targets->count()}件）");

            return self::FAILURE;
        }

        // 6. 移動先の校名が同一 prefecture_slug に既存なら中止。
        $existsTo = DrivingSchool::query()
            ->where('prefecture_slug', $pref)
            ->where('name', $to)
            ->exists();
        if ($existsTo) {
            $this->error("移動先の校名が既に存在するため中止: {$pref} / {$to}");

            return self::FAILURE;
        }

        // 7. 対象行の情報を表示（name 以外は読み取り表示のみ）。
        $school = $targets->first();
        $this->line("id: {$school->id}");
        $this->line('city: '.($school->city ?? '(null)'));
        $this->line('address: '.(($school->address ?? '') !== '' ? $school->address : '(null)'));
        $this->line("name（現在）: {$school->name}");
        $this->line("      name: {$from} → {$to}");

        $updated = 0;

        // 9. --dry-run でないときは name 列のみを更新して保存する。
        if (! $dryRun) {
            $school->name = $to;
            $school->save();
            $updated = 1;
        }
        // 8. --dry-run のときは書き込まない（$updated は 0 のまま）。

        // 10. 要約。
        $this->newLine();
        $this->info(sprintf(
            '%s対象: 1件 / 更新: %d件',
            $dryRun ? '[dry-run] ' : '',
            $updated,
        ));

        return self::SUCCESS;
    }
}
