<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Station;
use App\Services\Station\JapaneseToRomajiConverter;
use Illuminate\Console\Command;

final class GenerateStationSlugs extends Command
{
    protected $signature = 'station:generate-slugs {--dry-run : 変更を保存せずにプレビューのみ}';

    protected $description = 'ローマ字辞書を使って駅slugを人間が読める形式に変換する';

    /** @var array<string, string> */
    private const PREFECTURE_ROMAJI = [
        '北海道' => 'hokkaido',
        '青森県' => 'aomori',
        '岩手県' => 'iwate',
        '宮城県' => 'miyagi',
        '秋田県' => 'akita',
        '山形県' => 'yamagata',
        '福島県' => 'fukushima',
        '茨城県' => 'ibaraki',
        '栃木県' => 'tochigi',
        '群馬県' => 'gunma',
        '埼玉県' => 'saitama',
        '千葉県' => 'chiba',
        '東京都' => 'tokyo',
        '神奈川県' => 'kanagawa',
        '新潟県' => 'niigata',
        '富山県' => 'toyama',
        '石川県' => 'ishikawa',
        '福井県' => 'fukui',
        '山梨県' => 'yamanashi',
        '長野県' => 'nagano',
        '岐阜県' => 'gifu',
        '静岡県' => 'shizuoka',
        '愛知県' => 'aichi',
        '三重県' => 'mie',
        '滋賀県' => 'shiga',
        '京都府' => 'kyoto',
        '大阪府' => 'osaka',
        '兵庫県' => 'hyogo',
        '奈良県' => 'nara',
        '和歌山県' => 'wakayama',
        '鳥取県' => 'tottori',
        '島根県' => 'shimane',
        '岡山県' => 'okayama',
        '広島県' => 'hiroshima',
        '山口県' => 'yamaguchi',
        '徳島県' => 'tokushima',
        '香川県' => 'kagawa',
        '愛媛県' => 'ehime',
        '高知県' => 'kochi',
        '福岡県' => 'fukuoka',
        '佐賀県' => 'saga',
        '長崎県' => 'nagasaki',
        '熊本県' => 'kumamoto',
        '大分県' => 'oita',
        '宮崎県' => 'miyazaki',
        '鹿児島県' => 'kagoshima',
        '沖縄県' => 'okinawa',
    ];

    public function handle(JapaneseToRomajiConverter $converter): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('=== ドライランモード（変更は保存されません）===');
        }

        $stations = Station::all();
        $this->info("対象駅数: {$stations->count()}");

        // 既存slugの一覧を取得（重複チェック用）
        $existingSlugs = Station::pluck('slug', 'id')->toArray();

        // 新しいslugとして割り当て済みのものを追跡
        $assignedSlugs = $existingSlugs;

        $results = [];
        $updated = 0;
        $skippedMajor = 0;
        $skippedNoEntry = 0;
        $conflicts = 0;

        foreach ($stations as $station) {
            // 主要駅はスキップ（既にhuman-readable slug）
            if ($station->is_major) {
                $skippedMajor++;

                continue;
            }

            // 辞書にエントリーがなければスキップ
            if (! $converter->hasEntry($station->name)) {
                $skippedNoEntry++;

                continue;
            }

            $romajiSlug = $converter->convert($station->name);
            $oldSlug = $station->slug;

            // 同じslugなら何もしない
            if ($oldSlug === $romajiSlug) {
                continue;
            }

            // slug重複チェック: 別の駅が同じslugを使っていないか
            $conflictId = array_search($romajiSlug, $assignedSlugs);
            $newSlug = $romajiSlug;

            if ($conflictId !== false && $conflictId !== $station->id) {
                // 都道府県サフィックスを付与
                $prefRomaji = self::PREFECTURE_ROMAJI[$station->prefecture] ?? null;
                if ($prefRomaji) {
                    $newSlug = "{$romajiSlug}-{$prefRomaji}";
                } else {
                    $newSlug = "{$romajiSlug}-{$station->id}";
                }
                $conflicts++;
            }

            // サフィックス付きでもまだ重複する場合はIDを付与
            $finalConflict = array_search($newSlug, $assignedSlugs);
            if ($finalConflict !== false && $finalConflict !== $station->id) {
                $newSlug = "{$romajiSlug}-{$station->id}";
            }

            $results[] = [
                $station->name,
                $oldSlug,
                $newSlug,
                $conflictId !== false && $conflictId !== $station->id ? '重複解決' : '変換',
            ];

            // 追跡マップを更新
            $assignedSlugs[$station->id] = $newSlug;

            if (! $dryRun) {
                $updateData = ['slug' => $newSlug];

                // old_slugが未設定の場合のみ保存（再実行時に元のst-XXXXXXを上書きしない）
                if (! $station->old_slug) {
                    $updateData['old_slug'] = $oldSlug;
                }

                $station->update($updateData);
            }

            $updated++;
        }

        if (count($results) > 0) {
            $this->table(['駅名', '旧slug', '新slug', 'ステータス'], $results);
        }

        $this->newLine();
        $this->info('=== サマリー ===');
        $this->line("  変換対象: {$updated} 件");
        $this->line("  主要駅スキップ: {$skippedMajor} 件");
        $this->line("  辞書なしスキップ: {$skippedNoEntry} 件");
        $this->line("  slug重複解決: {$conflicts} 件");

        if ($dryRun) {
            $this->warn('ドライランのため変更は保存されていません。実行するには --dry-run を外してください。');
        } else {
            $this->info("完了: {$updated} 件のslugを更新しました。");
        }

        return self::SUCCESS;
    }
}
