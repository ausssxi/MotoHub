<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RoadsideStation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * 道の駅の image_url（Wikimedia Commons）から画像クレジット（著者・ライセンス・ライセンスURL）を取得する。
 * 既定は dry-run。実際の書き込みは --execute を明示したときのみ（ApplyRoadsideOfficialList と同方針）。
 *
 * Commons のライセンス（CC BY-SA 等）は「著者名＋ライセンス表記」の掲示が必須のため、
 * 表示に必要な最小限のメタデータ（Artist / LicenseShortName / LicenseUrl）を imageinfo/extmetadata から取得する。
 *
 * 対象: image_url が NOT NULL かつ image_author が NULL の行。
 * URL 形式: https://commons.wikimedia.org/wiki/Special:FilePath/<ファイル名>（?width= 等のクエリは無視）。
 * Special:FilePath 形式でないURLは「対象外」として報告しスキップ。
 */
final class FetchRoadsideImageCredits extends Command
{
    protected $signature = 'roadside:fetch-image-credits
        {--execute : 実際に書き込む（未指定は dry-run）}
        {--limit=0 : 処理件数の上限（0は無制限）}';

    protected $description = '道の駅 image_url(Commons) から著者・ライセンスを取得（既定 dry-run・--execute で実行）';

    /** Commons API のバッチサイズ（titles= の | 連結上限）。日本語ファイル名は1文字9バイト（%XX×3）に
     *  エンコードされ GETクエリが肥大するため、URL長がサーバ上限に触れないよう控えめに20とする。 */
    private const BATCH_SIZE = 20;

    private const COMMONS_API = 'https://commons.wikimedia.org/w/api.php';

    private const USER_AGENT = 'MotoHubBot/1.0 (+https://motohub.jp/)';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $limit = (int) $this->option('limit');

        $this->info('道の駅 画像クレジット取得'.($execute ? '（--execute: 実書き込み）' : '（dry-run）'));

        // 1. 対象取得（image_url あり・image_author なし）
        $query = RoadsideStation::query()
            ->whereNotNull('image_url')
            ->whereNull('image_author')
            ->orderBy('station_code');
        if ($limit > 0) {
            $query->limit($limit);
        }
        $stations = $query->get(['id', 'station_code', 'name', 'image_url']);

        $this->line('対象候補: '.$stations->count().' 件');
        $this->newLine();

        // 2. URL → Commons ファイル名を解決。非 FilePath はスキップ（対象外）。
        //    fileName => [station, ...] で、同一ファイルを複数駅が使うケースにも対応。
        $skippedUrl = 0;
        $byFile = [];       // fileName(スペース区切り正規化) => array<RoadsideStation>
        $fileNameOf = [];   // station->id => 元のファイル名（表示用）
        foreach ($stations as $station) {
            $fileName = $this->commonsFileName((string) $station->image_url);
            if ($fileName === null) {
                $skippedUrl++;

                continue;
            }
            $fileNameOf[$station->id] = $fileName;
            // Commons は File: タイトルでスペースとアンダースコアを同一視。突合キーはスペース区切りに正規化。
            $key = str_replace('_', ' ', $fileName);
            $byFile[$key][] = $station;
        }

        $success = 0;
        $missing = 0;   // Commons に存在せず
        $apiFail = 0;   // API 失敗（バッチスキップ）
        $samples = [];

        // 3. 50件ずつ Commons API へ。バッチ間 sleep(1)・直列。
        $keys = array_keys($byFile);
        $batches = array_chunk($keys, self::BATCH_SIZE);
        foreach ($batches as $batchIndex => $batchKeys) {
            if ($batchIndex > 0) {
                sleep(1); // レート制御（並列化しない）
            }

            $titles = implode('|', array_map(fn ($k) => 'File:'.$k, $batchKeys));

            try {
                $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                    ->timeout(30)
                    ->get(self::COMMONS_API, [
                        'action' => 'query',
                        'format' => 'json',
                        'formatversion' => 2,
                        'prop' => 'imageinfo',
                        'iiprop' => 'extmetadata',
                        'iiextmetadatafilter' => 'Artist|LicenseShortName|LicenseUrl',
                        'titles' => $titles,
                    ]);
            } catch (\Throwable $e) {
                $apiFail += count($batchKeys);
                $this->warn('  APIバッチ失敗（例外・スキップ）: '.$e->getMessage());

                continue;
            }

            if (! $response->successful()) {
                $apiFail += count($batchKeys);
                $this->warn('  APIバッチ失敗（HTTP '.$response->status().'・スキップ）');

                continue;
            }

            $data = $response->json();
            $pages = $data['query']['pages'] ?? [];

            // 4. normalized（from→to）を先に適用し、title で元データと突合。
            $normalized = [];
            foreach (($data['query']['normalized'] ?? []) as $n) {
                if (isset($n['from'], $n['to'])) {
                    $normalized[$n['to']] = $n['from'];
                }
            }

            foreach ($pages as $page) {
                $title = (string) ($page['title'] ?? '');
                // "File:xxx" → 突合キー（スペース区切りファイル名）。normalized があれば元 from に戻す。
                $fromTitle = $normalized[$title] ?? $title;
                $key = preg_replace('/^File:/', '', $fromTitle);

                $targets = $byFile[$key] ?? [];
                if ($targets === []) {
                    // 正規化差異の保険: normalize後タイトルでも一致を試す。
                    $key2 = preg_replace('/^File:/', '', $title);
                    $targets = $byFile[$key2] ?? [];
                }
                if ($targets === []) {
                    continue;
                }

                if (($page['missing'] ?? false) === true) {
                    $missing += count($targets);

                    continue;
                }

                $meta = $page['imageinfo'][0]['extmetadata'] ?? [];
                $author = $this->cleanText($meta['Artist']['value'] ?? null, 255);
                $license = $this->cleanText($meta['LicenseShortName']['value'] ?? null, 100);
                $licenseUrl = $this->cleanText($meta['LicenseUrl']['value'] ?? null, 255, false);

                foreach ($targets as $station) {
                    if ($execute) {
                        $station->update([
                            'image_author' => $author,
                            'image_license' => $license,
                            'image_license_url' => $licenseUrl,
                        ]);
                    }
                    $success++;
                    if (count($samples) < 5) {
                        $samples[] = sprintf(
                            '  %s: %s → %s / %s',
                            $station->station_code,
                            $fileNameOf[$station->id] ?? $key,
                            $author ?? '(著者不明)',
                            $license ?? '(ライセンス不明)'
                        );
                    }
                }
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '対象 %d 件 / 取得成功 %d / Commonsに存在せず %d / 対象外URL %d / API失敗 %d',
            $stations->count(),
            $success,
            $missing,
            $skippedUrl,
            $apiFail
        ));
        if ($samples !== []) {
            $this->line('  サンプル（最大5件・station_code: ファイル名 → 著者 / ライセンス）:');
            foreach ($samples as $line) {
                $this->line($line);
            }
        }
        if (! $execute) {
            $this->newLine();
            $this->warn('dry-run のため書き込みはしていません。--execute で実書き込みします。');
        }

        return self::SUCCESS;
    }

    /**
     * Commons の Special:FilePath 形式URLからファイル名を取り出す。
     * それ以外の形式は null（＝対象外）。
     *   例: https://commons.wikimedia.org/wiki/Special:FilePath/Foo_Bar.jpg?width=800 → "Foo Bar.jpg"
     */
    private function commonsFileName(string $url): ?string
    {
        // クエリ・フラグメントを除去してからパスを見る。
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }
        // Special:FilePath 形式でなければ対象外。
        if (! str_contains($path, 'Special:FilePath/')) {
            return null;
        }

        $segment = substr($path, strpos($path, 'Special:FilePath/') + strlen('Special:FilePath/'));
        // 念のため最後のセグメントのみ（配下にさらに / が来ることは通常ないが保険）。
        if (str_contains($segment, '/')) {
            $segment = substr($segment, strrpos($segment, '/') + 1);
        }
        $decoded = rawurldecode($segment);
        $decoded = trim($decoded);

        return $decoded !== '' ? $decoded : null;
    }

    /**
     * extmetadata の値を表示用に整形する。
     * $stripHtml=true: HTMLタグ除去→エンティティ復元→連続空白畳み→trim→$maxLen切り。
     * 整形後に空文字なら null。
     */
    private function cleanText(?string $value, int $maxLen, bool $stripHtml = true): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = $value;
        if ($stripHtml) {
            $text = strip_tags($text);
        }
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $maxLen);
    }
}
