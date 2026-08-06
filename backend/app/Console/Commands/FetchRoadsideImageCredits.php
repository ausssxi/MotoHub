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
        {--limit=0 : 処理件数の上限（0は無制限）}
        {--clean-only : 外部APIを呼ばず、DB保存済みの著者名を正規化するだけ（既定 dry-run・--execute で実行）}';

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

        // --clean-only: 外部APIを一切呼ばず、DB保存済みの著者名を読み直して正規化するだけ。
        // ここで return するため、以降の Commons API 呼び出し経路（Http::get）には到達しない
        // ＝ネットワークアクセスが発生しないことをコードで保証する。
        if ((bool) $this->option('clean-only')) {
            return $this->cleanOnly($execute, $limit);
        }

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
                // Artist のみ追加正規化（cleanText の後段。license/licenseUrl の経路は変えない）。
                if ($author !== null) {
                    $author = $this->normalizeAuthor($author);
                    $author = $author === '' ? null : $author;
                }
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

    /**
     * Wikimedia Commons の Artist 文字列を著者表示用に正規化する（Artist 専用）。
     * cleanText() でタグ除去・エンティティ復元・空白畳み済みの文字列にさらに適用する。
     * 下記6規則を「この順序どおり」適用（順序変更不可）。
     *   1. 不可視文字(LRM/RLM/BOM/SHY)を除去
     *   2. "No machine-readable author provided. X assumed (based on copyright claims)." → X
     *   3. 先頭の "photo:" を除去
     *   4. "(talk)" を除去（前後を空白1個に畳めるよう空白へ置換）
     *   5. 撮影機材の記述("... taken with ...")以降を切り捨て
     *   6. 連続空白を単一空白に畳み、前後を trim
     */
    private function normalizeAuthor(string $value): string
    {
        // 1. 不可視文字（LRM/RLM/BOM/SHY）を除去
        $value = preg_replace('/[\x{200E}\x{200F}\x{FEFF}\x{00AD}]/u', '', $value) ?? $value;
        // 2. "No machine-readable author provided. X assumed (based on copyright claims)." → キャプチャ1
        $value = preg_replace('/^No machine-readable author provided\.\s*(.+?)\s+assumed\s*\(based on copyright claims\)\.?$/u', '$1', $value) ?? $value;
        // 3. 先頭の "photo:" を除去
        $value = preg_replace('/^\s*photo\s*:\s*/iu', '', $value) ?? $value;
        // 4. "(talk)" を除去（語の結合を避けるため空白1個へ置換。規則6で畳む）
        $value = preg_replace('/\s*\(\s*talk\s*\)\s*/iu', ' ', $value) ?? $value;
        // 5. 撮影機材の記述以降を切り捨てる（"with" を含む句のみ対象。"Taken by ..." は非該当）
        $value = preg_replace('/\s*\b(This photo was taken with|Photo taken with|Taken with|taken with)\b.*$/us', '', $value) ?? $value;
        // 6. 連続空白を単一空白に畳み trim
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * --clean-only モード: 外部APIを一切呼ばず、DB保存済みの image_author を読み直して
     * normalizeAuthor() で正規化するだけ。--execute 未指定時はDBを一切更新しない（既定 dry-run）。
     * この関数内には Commons API 呼び出し(Http)は存在しない＝ネットワーク非発生。
     */
    private function cleanOnly(bool $execute, int $limit): int
    {
        $this->info('道の駅 著者名クリーニング（--clean-only）'.($execute ? '（--execute: 実書き込み）' : '（dry-run）'));

        $query = RoadsideStation::query()
            ->whereNotNull('image_author')
            ->orderBy('station_code');
        if ($limit > 0) {
            $query->limit($limit);
        }
        $stations = $query->get(['id', 'station_code', 'name', 'image_author']);

        $total = $stations->count();
        $changed = 0;
        $samples = [];

        foreach ($stations as $station) {
            $before = (string) $station->image_author;
            $normalized = $this->normalizeAuthor($before);
            $after = $normalized === '' ? null : $normalized;

            // 正規化後が元と同一なら変化なし（型・null 差も含めて厳密比較）。
            if ($after === $station->image_author) {
                continue;
            }

            $changed++;
            if ($execute) {
                $station->update(['image_author' => $after]);
            }
            if (count($samples) < 10) {
                $samples[] = sprintf('  %s: %s → %s', $station->station_code, $before, $after ?? '(空→null)');
            }
        }

        $this->newLine();
        $this->info(sprintf('対象 %d 件 / 変化あり %d 件', $total, $changed));
        foreach ($samples as $line) {
            $this->line($line);
        }
        if ($changed > count($samples)) {
            $this->line('  他 '.($changed - count($samples)).' 件');
        }
        if (! $execute) {
            $this->newLine();
            $this->warn('dry-run のため書き込みはしていません。--execute で実書き込みします。');
        }

        return self::SUCCESS;
    }
}
