<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Manufacturer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Transliterator;

/**
 * slug が NULL/空のメーカーについて、slug 生成「案」を出力する（読み取り専用・出力のみ）。
 *
 * メーカーslugが無いと車種ページ /bikes/{メーカーslug}/{車種slug} が生成されずサイトマップにも載らない。
 * その規模と、生成案の品質・衝突を人が確認するための調査コマンド。書き込みは別途人手で行う。
 *
 * ★書き込みは一切しない（UPDATE/INSERT/DELETE も --execute も無い）。
 * ★カタカナ→ローマ字は新しい辞書を書かず、既存 GenerateMissingSlugs::improvedSlug
 *   （KANA_WORD_MAP 辞書＋Transliterator）を reflection で再利用する（DetectNameSystemDupes と同方式）。
 *   GenerateMissingSlugs は一切変更しない。
 * ★在庫台数は listings.manufacturer_id を直接数える（bike_models 経由だと車種未紐付けの在庫が漏れるため）。
 *   参考として車種経由の数え方とも比較し、差があれば示す。
 */
final class ProposeManufacturerSlugs extends Command
{
    protected $signature = 'manufacturers:propose-slugs';

    protected $description = 'slug が NULL/空のメーカーの slug 生成案を出力（読み取り専用・衝突/空も検査・書き込みなし）';

    /**
     * メーカー名が重複し slug が衝突するため今回の対象から除外する id。
     * 70 mvアグスタ/153 mv アグスタ・82 ガス ガス/173 ガスガス・38 ボス ホス/147 ボスホス・76 モトモリーニ/156 モト・モリーニ
     */
    private const EXCLUDED_IDS = [70, 153, 82, 173, 38, 147, 76, 156];

    public function handle(): int
    {
        // 変換は既存 GenerateMissingSlugs::improvedSlug を reflection で再利用（辞書・変換を二重化しない）。
        $transliterator = Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        if ($transliterator === null) {
            $this->error('Transliterator を初期化できませんでした（intl拡張を確認してください）');

            return self::FAILURE;
        }
        $generator = new GenerateMissingSlugs();
        $improvedSlug = new ReflectionMethod($generator, 'improvedSlug');
        $improvedSlug->setAccessible(true);

        // 既存 slug（非空）の集合。生成案との衝突判定に使う。
        $existingSlugs = Manufacturer::query()
            ->whereNotNull('slug')->where('slug', '!=', '')
            ->pluck('slug')
            ->map(fn ($s) => (string) $s)
            ->flip(); // slug => index の連想にして isset で高速判定

        // 車種経由の active 在庫数（listings→bike_models）をメーカー別に1クエリで取得（比較用）。
        $viaModelCounts = DB::table('listings')
            ->join('bike_models', 'bike_models.id', '=', 'listings.bike_model_id')
            ->where('listings.is_sold_out', false)
            ->groupBy('bike_models.manufacturer_id')
            ->selectRaw('bike_models.manufacturer_id as mid, COUNT(*) as cnt')
            ->pluck('cnt', 'mid');

        // 対象: slug が NULL または空、かつ除外id以外。
        // 在庫は listings.manufacturer_id を直接（Manufacturer::listings + active）。車種数は統合済み除く。
        $targets = Manufacturer::query()
            ->where(fn ($q) => $q->whereNull('slug')->orWhere('slug', '=', ''))
            ->whereNotIn('id', self::EXCLUDED_IDS)
            ->withCount([
                'listings as active_listings_count' => fn ($q) => $q->active(),
                'bikeModels as bike_models_count' => fn ($q) => $q->whereNull('merged_into_id'),
            ])
            ->get(['id', 'name']);

        // 生成案を作り、対象内での重複（案どうしの衝突）を数える。
        $proposalCounts = [];
        $rows = [];
        foreach ($targets as $m) {
            $slug = $improvedSlug->invoke($generator, $transliterator, (string) $m->name);
            $slug = $slug === null ? '' : (string) $slug;
            $direct = (int) $m->active_listings_count;
            $viaModel = (int) ($viaModelCounts[$m->id] ?? 0);
            $rows[] = [
                'id' => (int) $m->id,
                'name' => (string) $m->name,
                'slug' => $slug,
                'models' => (int) $m->bike_models_count,
                'stock' => $direct,
                'stock_via_model' => $viaModel,
            ];
            if ($slug !== '') {
                $proposalCounts[$slug] = ($proposalCounts[$slug] ?? 0) + 1;
            }
        }

        // 在庫台数の多い順（品質が重要な順）。
        usort($rows, fn ($a, $b) => $b['stock'] <=> $a['stock']);

        $this->newLine();
        $this->line('==== メーカー slug 生成案（読み取り専用・DB変更なし）====');
        $this->comment('※ 在庫は listings.manufacturer_id を直接カウント。印: [既存衝突]既存slugと重複 / [対象内衝突]生成案どうしで重複 / [空/1文字]案が空か1文字');
        $this->newLine();

        $collisionCount = 0;
        $emptyCount = 0;
        $sumDirect = 0;
        $sumViaModel = 0;
        $diffMakers = 0;
        foreach ($rows as $r) {
            $sumDirect += $r['stock'];
            $sumViaModel += $r['stock_via_model'];

            $flags = [];
            if ($r['slug'] !== '' && isset($existingSlugs[$r['slug']])) {
                $flags[] = '[既存衝突]';
            }
            if ($r['slug'] !== '' && ($proposalCounts[$r['slug']] ?? 0) > 1) {
                $flags[] = '[対象内衝突]';
            }
            if ($r['slug'] === '' || mb_strlen($r['slug']) <= 1) {
                $flags[] = '[空/1文字]';
                $emptyCount++;
            }
            if ($flags !== [] && ($flags !== ['[空/1文字]'])) {
                // 「衝突」は既存衝突または対象内衝突があるもの（空/1文字だけは品質印で別集計）。
                $collisionCount++;
            }

            $note = '';
            if ($r['stock'] !== $r['stock_via_model']) {
                $diffMakers++;
                $note = sprintf('（車種経由では %s台）', number_format($r['stock_via_model']));
            }

            $this->line(sprintf(
                '  id=%-4d 在庫%-6s 車種%-4d 案=%-24s %s%s  name="%s"',
                $r['id'],
                number_format($r['stock']),
                $r['models'],
                $r['slug'] === '' ? '(生成不能)' : $r['slug'],
                implode('', $flags),
                $note,
                $r['name'],
            ));
        }

        $this->newLine();
        $this->line('==== 集計 ====');
        $this->line(sprintf('  対象件数（slug空のメーカー・除外後）: %d', count($rows)));
        $this->line(sprintf('  衝突件数（既存slug or 対象内で重複）: %d', $collisionCount));
        $this->line(sprintf('  除外件数（メーカー名重複の指定8件）  : %d', count(self::EXCLUDED_IDS)));
        $this->line(sprintf('  参考: 案が空/1文字（要手当て）        : %d', $emptyCount));
        $this->newLine();
        $this->line('  除外したメーカーid: '.implode(', ', self::EXCLUDED_IDS));
        $this->newLine();
        $this->line('==== 在庫の数え方の比較 ====');
        $this->line(sprintf('  listings.manufacturer_id 直接（採用）: 合計 %s台', number_format($sumDirect)));
        $this->line(sprintf('  bike_models 経由（参考）            : 合計 %s台', number_format($sumViaModel)));
        $this->line(sprintf('  差（直接 − 車種経由・車種未紐付け等）: %s台 / 差のあるメーカー %d件', number_format($sumDirect - $sumViaModel), $diffMakers));

        return self::SUCCESS;
    }
}
