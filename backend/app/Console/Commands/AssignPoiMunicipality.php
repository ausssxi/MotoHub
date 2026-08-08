<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Poi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * POI（コンビニ・GS・洗車場・道の駅）の座標から市区町村を判定し、pois に書き戻す。
 *
 * 判定は行政区域ポリゴン（municipality_polygons）への内包判定。
 *
 * 【SRID / 軸順（実測済み・厳守）】
 *   geom は SRID 0 で、座標は「経度・緯度」の順で格納されている。
 *   よって判定は必ず ST_Contains(geom, ST_GeomFromText('POINT(経度 緯度)', 0)) の形にする。
 *   SRID 4326 は使わない。緯度・経度の順にしない。
 *
 * 更新するのは municipality_code / prefecture / city の3カラムのみ。
 *   prefecture = municipalities.prefecture、city = municipalities.full_name。
 */
class AssignPoiMunicipality extends Command
{
    protected $signature = 'pois:assign-municipality
        {--execute : 実際に更新する（未指定は dry-run）}
        {--type= : gas_station / convenience_store / car_wash / michi_no_eki で絞る}
        {--limit= : 処理件数の上限}
        {--force : 既に municipality_code が入っている行も再判定する}';

    protected $description = 'POIの座標から市区町村を判定して pois に書き戻す（既定 dry-run）';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $type = $this->option('type');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $force = (bool) $this->option('force');

        if (! $execute) {
            $this->info('[DRY RUN] データは更新されません。');
        }

        // 市区町村メタを code キーでメモリに載せる（1行ごとの再クエリを避ける）。
        $municipalities = DB::table('municipalities')
            ->select('code', 'prefecture', 'full_name')
            ->get()
            ->keyBy('code');
        $this->info('市区町村メタ: '.$municipalities->count().'件をメモリに読み込み');

        $query = Poi::query();
        if (! $force) {
            $query->whereNull('municipality_code');
        }
        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }

        $target = (clone $query)->count();
        if ($limit !== null) {
            $target = min($target, $limit);
        }
        $this->info(($execute ? '' : '[DRY RUN] ')."対象: {$target}件");

        $start = microtime(true);

        $evaluated = 0;
        $matched = 0;
        $unmatched = 0;
        $unmatchedSamples = [];

        $query->orderBy('id')->chunkById(500, function ($pois) use (
            $execute,
            $limit,
            $municipalities,
            $start,
            &$evaluated,
            &$matched,
            &$unmatched,
            &$unmatchedSamples,
        ): bool {
            foreach ($pois as $poi) {
                if ($limit !== null && $evaluated >= $limit) {
                    return false;
                }

                $evaluated++;

                // 「経度 緯度」の順（SRID 0・格納軸順に一致）。
                $point = 'POINT('.$poi->longitude.' '.$poi->latitude.')';

                $row = DB::selectOne(
                    'SELECT code FROM municipality_polygons WHERE ST_Contains(geom, ST_GeomFromText(?, 0)) LIMIT 1',
                    [$point],
                );

                $code = $row->code ?? null;
                $meta = $code !== null ? $municipalities->get($code) : null;

                if ($code === null || $meta === null) {
                    $unmatched++;
                    if (count($unmatchedSamples) < 20) {
                        $unmatchedSamples[] = [
                            'id' => $poi->id,
                            'type' => (string) $poi->type,
                            'name' => (string) $poi->name,
                            'lat' => $poi->latitude,
                            'lng' => $poi->longitude,
                        ];
                    }

                    continue;
                }

                $matched++;

                if ($execute) {
                    // 該当3カラムのみ更新。他カラムは触らない。
                    $poi->municipality_code = $code;
                    $poi->prefecture = $meta->prefecture;
                    $poi->city = $meta->full_name;
                    $poi->saveQuietly();
                }
            }

            if ($evaluated % 1000 === 0) {
                $elapsed = round(microtime(true) - $start, 1);
                $this->line("  ... {$evaluated}件処理 / 成功{$matched} / 未判定{$unmatched} / {$elapsed}秒");
            }

            // limit 到達時は以降のチャンクを打ち切る。
            return ! ($limit !== null && $evaluated >= $limit);
        });

        $elapsed = round(microtime(true) - $start, 1);

        if ($unmatchedSamples !== []) {
            $this->newLine();
            $this->line('未判定の例（最大20件・id / type / name / 緯度 / 経度）:');
            foreach ($unmatchedSamples as $s) {
                $this->line("  {$s['id']} / {$s['type']} / {$s['name']} / {$s['lat']} / {$s['lng']}");
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s対象件数: %d / 判定成功: %d / 未判定: %d / 所要: %s秒',
            $execute ? '' : '[DRY RUN] ',
            $evaluated,
            $matched,
            $unmatched,
            $elapsed,
        ));

        if (! $execute) {
            $this->newLine();
            $this->warn('DRY RUN のため実際の更新は行っていません。');
        }

        return self::SUCCESS;
    }
}
