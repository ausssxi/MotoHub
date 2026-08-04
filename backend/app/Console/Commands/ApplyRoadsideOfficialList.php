<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RoadsideStation;
use Illuminate\Console\Command;

/**
 * 道の駅台帳を国交省公式一覧（CSV群）に合わせて更新する。
 * 既定は dry-run。実際の書き込みは --execute を明示したときのみ（FixPoiUnknownNames と同方針）。
 *
 * 4処理（すべて station_code をキーに突合）:
 *   1. roadside_name_fix.csv   … current_name がDB現在値と一致する場合のみ official_name に更新
 *   2. roadside_rename_20.csv  … current_name 一致確認のうえ new_name に更新
 *   3. roadside_delist_4.csv   … この段階では削除しない。存在確認のみ報告
 *   4. roadside_new_108.csv    … 新規INSERT（station_code 既存はスキップ）。lat/lng/address は NULL のまま
 */
final class ApplyRoadsideOfficialList extends Command
{
    protected $signature = 'roadside:apply-official
        {--dir=database/data/roadside_2026_08 : CSV群のディレクトリ}
        {--execute : 実際に更新/挿入する（未指定は dry-run）}';

    protected $description = '道の駅台帳を国交省公式一覧に合わせて更新（既定 dry-run・--execute で実行）';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $dirOpt = (string) $this->option('dir');
        $dir = str_starts_with($dirOpt, '/') ? $dirOpt : base_path($dirOpt);

        $this->info('道の駅公式一覧の適用'.($execute ? '（--execute: 実書き込み）' : '（dry-run）'));
        $this->line("CSVディレクトリ: {$dir}");
        $this->newLine();

        try {
            $this->applyNameChange("{$dir}/roadside_name_fix.csv", 'official_name', '名称修正(name_fix)', $execute);
            $this->applyNameChange("{$dir}/roadside_rename_20.csv", 'new_name', '改称(rename_20)', $execute);
            $this->reportDelist("{$dir}/roadside_delist_4.csv");
            $this->applyNew("{$dir}/roadside_new_108.csv", $execute);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * name_fix / rename: current_name がDB現在値と一致した行のみ新名称へ更新。不一致は個別報告。
     */
    private function applyNameChange(string $path, string $newCol, string $label, bool $execute): void
    {
        $rows = $this->readCsv($path, ['station_code', 'current_name', $newCol]);

        $updated = [];
        $mismatch = [];
        $notfound = [];

        foreach ($rows as $r) {
            $code = trim((string) ($r['station_code'] ?? ''));
            $current = trim((string) ($r['current_name'] ?? ''));
            $newName = trim((string) ($r[$newCol] ?? ''));
            if ($code === '') {
                continue;
            }

            $station = RoadsideStation::query()->where('station_code', $code)->first();
            if ($station === null) {
                $notfound[] = ['code' => $code, 'current' => $current];

                continue;
            }
            if (trim((string) $station->name) !== $current) {
                $mismatch[] = ['code' => $code, 'db_name' => (string) $station->name, 'csv_current' => $current];

                continue;
            }

            if ($execute) {
                $station->update(['name' => $newName]);
            }
            $updated[] = ['code' => $code, 'from' => $current, 'to' => $newName];
        }

        $this->info("[{$label}] 対象 ".count($rows)." 件 → 更新".($execute ? '' : '予定')." ".count($updated)." / 不一致 ".count($mismatch)." / 該当なし ".count($notfound));
        $this->sample('更新'.($execute ? '' : '予定'), $updated, fn ($u) => "  {$u['code']}: 「{$u['from']}」→「{$u['to']}」");
        // 不一致は「個別に報告」（サンプルではなく全件）
        if ($mismatch !== []) {
            $this->warn('  不一致（更新せず・全件）:');
            foreach ($mismatch as $m) {
                $this->line("    {$m['code']}: DB=「{$m['db_name']}」 / CSV current_name=「{$m['csv_current']}」");
            }
        }
        $this->sample('該当なし', $notfound, fn ($n) => "  {$n['code']}: current=「{$n['current']}」");
        $this->newLine();
    }

    /**
     * delist: 削除しない。存在確認のみ（参照監査は【0】で「FK・相互参照なし＝削除安全」と確認済み）。
     */
    private function reportDelist(string $path): void
    {
        $rows = $this->readCsv($path, ['station_code']);

        $exists = [];
        $missing = [];
        foreach ($rows as $r) {
            $code = trim((string) ($r['station_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $station = RoadsideStation::query()->where('station_code', $code)->first(['station_code', 'name']);
            if ($station !== null) {
                $exists[] = ['code' => $code, 'name' => (string) $station->name];
            } else {
                $missing[] = ['code' => $code];
            }
        }

        $this->info('[廃止候補(delist_4)] '.count($rows)." 件 → DB存在 ".count($exists)." / 不在 ".count($missing)."（★このコマンドでは削除しません）");
        foreach ($exists as $e) {
            $this->line("    存在: {$e['code']} 「{$e['name']}」");
        }
        foreach ($missing as $m) {
            $this->line("    不在: {$m['code']}");
        }
        $this->line('    参照監査: roadside_stations は FK/相互参照なし（TouringPlanner/map.js は座標検索・websiteリンクのみ）＝削除しても他機能への波及なし。');
        $this->newLine();
    }

    /**
     * new_108: station_code 未存在の行を INSERT。lat/lng/address は NULL のまま（'' も 0 も入れない）。
     */
    private function applyNew(string $path, bool $execute): void
    {
        $rows = $this->readCsv($path, ['station_code', 'name']);

        $inserts = [];
        $skipped = [];
        foreach ($rows as $r) {
            $code = trim((string) ($r['station_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            if (RoadsideStation::query()->where('station_code', $code)->exists()) {
                $skipped[] = ['code' => $code, 'name' => trim((string) ($r['name'] ?? ''))];

                continue;
            }

            $year = trim((string) ($r['designated_year'] ?? ''));
            $website = trim((string) ($r['website_url'] ?? ''));
            // 公式一覧の短縮名（official_name）は既存台帳の nickname と同種（パイプ区切り別名）なので nickname へ。
            $nickname = trim((string) ($r['official_name'] ?? ''));
            $data = [
                'station_code' => $code,
                'name' => trim((string) ($r['name'] ?? '')),
                'nickname' => $nickname !== '' ? $nickname : null,
                'prefecture' => ($p = trim((string) ($r['prefecture'] ?? ''))) !== '' ? $p : null,
                'city' => ($c = trim((string) ($r['city'] ?? ''))) !== '' ? $c : null,
                'designated_year' => ctype_digit($year) ? (int) $year : null,
                'website_url' => $website !== '' ? $website : null,
                // 公式一覧の所在地は市町村止まりで住所として不正確 → NULL のまま
                'address' => null,
                // 座標未確定 → NULL（'' も 0 もセンチネルなので入れない）
                'latitude' => null,
                'longitude' => null,
            ];

            if ($execute) {
                RoadsideStation::create($data);
            }
            $inserts[] = $data;
        }

        $this->info('[新規(new_108)] '.count($rows)." 件 → 挿入".($execute ? '' : '予定')." ".count($inserts)." / スキップ(既存) ".count($skipped));
        $this->sample('挿入'.($execute ? '' : '予定'), $inserts, fn ($i) => "  {$i['station_code']}: {$i['name']}（通称:".($i['nickname'] ?? '-')."） / {$i['prefecture']}{$i['city']} / {$i['designated_year']} / ".($i['website_url'] ?? '(URLなし)'));
        $this->sample('スキップ(既存)', $skipped, fn ($s) => "  {$s['code']}: {$s['name']}");
        $this->newLine();
    }

    /**
     * ヘッダ行を読み、列名でアクセスできる連想配列群を返す（列順非依存・UTF-8 BOM除去）。
     *
     * @param  array<int, string>  $required  必須列名
     * @return array<int, array<string, string|null>>
     */
    private function readCsv(string $path, array $required = []): array
    {
        if (! is_file($path)) {
            throw new \RuntimeException("CSVが見つかりません: {$path}");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("CSVを開けません: {$path}");
        }

        $header = null;
        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map('trim', $data);
                if (isset($header[0])) {
                    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // UTF-8 BOM 除去
                }
                $missing = array_diff($required, $header);
                if ($missing !== []) {
                    fclose($handle);

                    throw new \RuntimeException("{$path} に必須列がありません: ".implode(', ', $missing));
                }

                continue;
            }
            // 空行スキップ
            if (count($data) === 1 && trim((string) $data[0]) === '') {
                continue;
            }
            $vals = array_pad(array_slice($data, 0, count($header)), count($header), null);
            $rows[] = array_combine($header, $vals);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * サンプル5件を出力。
     *
     * @param  array<int, mixed>  $items
     */
    private function sample(string $label, array $items, callable $fmt): void
    {
        if ($items === []) {
            return;
        }
        $this->line("  {$label} サンプル（最大5件）:");
        foreach (array_slice($items, 0, 5) as $it) {
            $this->line($fmt($it));
        }
    }
}
