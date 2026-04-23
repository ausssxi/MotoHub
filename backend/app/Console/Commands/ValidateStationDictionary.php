<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Station;
use Illuminate\Console\Command;

final class ValidateStationDictionary extends Command
{
    protected $signature = 'station:validate-dictionary';

    protected $description = '駅名ローマ字辞書を検証する';

    public function handle(): int
    {
        // Docker内: backend/ = /var/www, docs/ は未マウント
        // ホスト実行時は ../docs、Docker内はフォールバック
        $dictionaryPath = base_path('../docs/station-romaji/index.php');
        if (! file_exists($dictionaryPath)) {
            // Docker環境: configにコピーされた辞書を参照
            $dictionaryPath = config_path('station-romaji/index.php');
        }

        if (! file_exists($dictionaryPath)) {
            $this->error('辞書ファイルが見つかりません: ' . $dictionaryPath);
            return self::FAILURE;
        }

        $dictionary = require $dictionaryPath;

        $this->info('=== 駅名ローマ字辞書 検証レポート ===');
        $this->newLine();

        // --- a. 辞書エントリー数 ---
        $totalEntries = $this->countAllEntries();
        $uniqueEntries = count($dictionary);
        $duplicateCount = $totalEntries - $uniqueEntries;

        $this->info("【a】辞書エントリー数");
        $this->line("  全ファイル合計（重複込み）: {$totalEntries}");
        $this->line("  統合後（重複排除）: {$uniqueEntries}");
        $this->line("  重複キー数: {$duplicateCount}");
        $this->newLine();

        // --- b. DBのstationsテーブルとの突合 ---
        $dbStations = Station::pluck('name')->toArray();
        $dbStationNames = array_unique($dbStations);
        $dictKeys = array_keys($dictionary);

        $matchedInDb = array_intersect($dictKeys, $dbStationNames);

        $this->info("【b】DBとの突合");
        $this->line("  stationsテーブルの駅数: " . count($dbStationNames));
        $this->line("  辞書にあり＆DBにもある駅: " . count($matchedInDb));
        $this->newLine();

        // --- c. 辞書にあるがDBにない駅 ---
        $inDictNotInDb = array_diff($dictKeys, $dbStationNames);

        $this->info("【c】辞書にあるがDBにない駅（" . count($inDictNotInDb) . "件）");
        if (count($inDictNotInDb) > 0) {
            // 多いので最初の50件だけ表示
            $sample = array_slice($inDictNotInDb, 0, 50);
            foreach ($sample as $name) {
                $this->line("  - {$name} => {$dictionary[$name]}");
            }
            if (count($inDictNotInDb) > 50) {
                $this->line("  ... 他 " . (count($inDictNotInDb) - 50) . " 件省略");
            }
        } else {
            $this->line("  なし（全駅がDBに存在）");
        }
        $this->newLine();

        // --- d. 主要駅（is_major=true）で辞書にないもの ---
        $majorStations = Station::where('is_major', true)->pluck('name')->toArray();
        $majorNotInDict = array_diff($majorStations, $dictKeys);

        $this->info("【d】主要駅（is_major=true）で辞書にないもの");
        $this->line("  主要駅の総数: " . count($majorStations));
        if (count($majorNotInDict) > 0) {
            $this->error("  辞書にない主要駅: " . count($majorNotInDict) . " 件");
            foreach ($majorNotInDict as $name) {
                $this->line("  - {$name}");
            }
        } else {
            $this->info("  全主要駅が辞書に存在 ✓");
        }
        $this->newLine();

        // --- e. slug重複チェック ---
        $slugToNames = [];
        foreach ($dictionary as $name => $slug) {
            $slugToNames[$slug][] = $name;
        }

        $duplicateSlugs = array_filter($slugToNames, fn ($names) => count($names) > 1);

        $this->info("【e】slug重複チェック");
        if (count($duplicateSlugs) > 0) {
            $this->warn("  重複slugあり: " . count($duplicateSlugs) . " 件");
            foreach ($duplicateSlugs as $slug => $names) {
                $this->line("  slug=\"{$slug}\" => " . implode(', ', $names));
            }
        } else {
            $this->info("  slug重複なし ✓");
        }
        $this->newLine();

        // --- サマリー ---
        $this->info('=== サマリー ===');
        $hasIssues = count($majorNotInDict) > 0 || count($duplicateSlugs) > 0;
        if ($hasIssues) {
            $this->warn('要対応の問題があります');
        } else {
            $this->info('重大な問題はありません');
        }

        return $hasIssues ? self::FAILURE : self::SUCCESS;
    }

    private function countAllEntries(): int
    {
        $dir = base_path('../docs/station-romaji');
        if (! is_dir($dir)) {
            // Docker環境ではファイル個別カウント不可、統合辞書のみカウント
            $dictionaryPath = config_path('station-romaji/index.php');
            if (file_exists($dictionaryPath)) {
                $data = require $dictionaryPath;
                return is_array($data) ? count($data) : 0;
            }
            return 0;
        }
        $total = 0;

        $files = glob($dir . '/*.php');
        foreach ($files as $file) {
            if (basename($file) === 'index.php') {
                continue;
            }
            $data = require $file;
            if (is_array($data)) {
                $total += count($data);
            }
        }

        return $total;
    }
}
