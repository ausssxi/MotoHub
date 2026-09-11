<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Poi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 各POIについて「最も近い同種別POI」を事前計算し、pois の nearest_* 列へ書き戻す。
 *
 * 目的（表示時に空間クエリを投げないための土台）:
 *   ① GS詳細「次のGSまで◯km」  ② コンビニの孤立判定  ③ サイトマップ選別（>=3km）
 *
 * 【性能の要】全件×全件は本番で数分かかる（コンビニ 31,050^2）。各POIごとに矩形を段階的に
 *   広げ（1km→5km→20km→100km）、見つかった段階で確定する。都市部はほぼ1kmで終わる。
 *   矩形は index (type, latitude, longitude) の範囲スキャンで粗く絞り、実距離は
 *   ST_Distance_Sphere（MySQL）で出して最小を採る。
 *
 * 【軸順・SRID（AssignPoiMunicipality と統一・厳守）】
 *   格納は「経度・緯度」順。POINT(longitude, latitude) で SRID 0。ST_Distance_Sphere は
 *   SRID 0 の点を経度・緯度として解釈しメートルを返す。
 *
 * resume: 既定は nearest_computed_at IS NULL の行のみ。途中で止めても続きから走る。
 */
final class ComputeNearestPoi extends Command
{
    protected $signature = 'poi:compute-nearest
        {--type= : gas_station | convenience_store | car_wash | michi_no_eki}
        {--limit=5000 : 1回の実行で処理する最大件数}
        {--force : 計算済み（nearest_computed_at が入っている）行も対象に含める}
        {--prefecture= : 都道府県で絞る（検証用）}';

    protected $description = '各POIの最も近い同種別POIを事前計算して pois.nearest_* に書き戻す（既定は未計算行のみ）';

    /** 矩形を広げる段階（メートル）。見つかった段階で確定する。 */
    private const STAGES_M = [1000, 5000, 20000, 100000];

    /** 孤立とみなす閾値（メートル）。サイトマップ選別と検証に使う。 */
    private const ISOLATED_M = 3000;

    private const ALLOWED_TYPES = ['gas_station', 'convenience_store', 'car_wash', 'michi_no_eki'];

    public function handle(): int
    {
        $type = $this->option('type');
        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');
        $prefecture = $this->option('prefecture');

        if ($type !== null && $type !== '' && ! in_array($type, self::ALLOWED_TYPES, true)) {
            $this->error('--type は '.implode(' / ', self::ALLOWED_TYPES).' のいずれかです（指定値: '.$type.'）');

            return self::FAILURE;
        }

        $query = Poi::query()->whereNotNull('latitude')->whereNotNull('longitude');
        if (! $force) {
            $query->whereNull('nearest_computed_at');
        }
        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }
        if ($prefecture !== null && $prefecture !== '') {
            $query->where('prefecture', $prefecture);
        }

        $target = min((clone $query)->count(), $limit);
        $this->info(sprintf(
            '対象: %d件（type=%s / prefecture=%s / %s）',
            $target,
            $type !== null && $type !== '' ? $type : 'all',
            $prefecture !== null && $prefecture !== '' ? $prefecture : 'all',
            $force ? '計算済みも再計算' : '未計算のみ',
        ));
        if ($target === 0) {
            $this->info('処理対象がありません。');

            return self::SUCCESS;
        }

        $start = microtime(true);
        $processed = 0;
        $withNeighbor = 0;
        $isolated = 0;   // 近傍はあるが >= 3km
        $noneFound = 0;  // 100km 以内に同種別が無い（離島など）

        $query->orderBy('id')->chunkById(500, function ($pois) use (
            $limit,
            $start,
            &$processed,
            &$withNeighbor,
            &$isolated,
            &$noneFound,
        ): bool {
            foreach ($pois as $poi) {
                if ($processed >= $limit) {
                    return false;
                }

                [$nearestId, $nearestM] = $this->findNearest($poi);

                DB::table('pois')->where('id', $poi->id)->update([
                    'nearest_same_type_id' => $nearestId,
                    'nearest_same_type_m' => $nearestM,
                    'nearest_computed_at' => now(),
                ]);

                $processed++;
                if ($nearestM === null) {
                    $noneFound++;
                } elseif ($nearestM >= self::ISOLATED_M) {
                    $withNeighbor++;
                    $isolated++;
                } else {
                    $withNeighbor++;
                }

                if ($processed % 1000 === 0) {
                    $elapsed = round(microtime(true) - $start, 1);
                    $this->line("  ... {$processed}件処理 / 近傍あり{$withNeighbor}（うち3km以上{$isolated}）/ 近傍なし{$noneFound} / {$elapsed}秒");
                }
            }

            return $processed < $limit;
        });

        $elapsed = round(microtime(true) - $start, 1);
        $this->newLine();
        $this->info(sprintf(
            '完了: %d件処理 / 近傍あり %d（うち3km以上 %d）/ 近傍なし %d / 所要 %s秒',
            $processed,
            $withNeighbor,
            $isolated,
            $noneFound,
            $elapsed,
        ));

        // 検証補助: 現スコープの計算済み全体で 3km 以上が何件か（全国コンビニで約841件が期待値）。
        $this->reportIsolatedTotal($type, $prefecture);

        return self::SUCCESS;
    }

    /**
     * 段階的に矩形を広げ、最も近い同種別POIの [id, メートル] を返す。無ければ [null, null]。
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function findNearest(Poi $poi): array
    {
        foreach (self::STAGES_M as $radiusM) {
            $hit = $this->nearestInBox($poi, $radiusM);
            if ($hit !== null) {
                return $hit;
            }
        }

        return [null, null];
    }

    /**
     * 半径 $radiusM の矩形（緯度経度のΔ）で候補を粗く絞り、実距離の最小を返す。無ければ null。
     * MySQL は ST_Distance_Sphere、それ以外（SQLite テスト）は PHP の Haversine で同義に算出する。
     *
     * @return array{0: int, 1: int}|null
     */
    private function nearestInBox(Poi $poi, int $radiusM): ?array
    {
        $lat = (float) $poi->latitude;
        $lng = (float) $poi->longitude;
        $km = $radiusM / 1000.0;

        // 緯度1度≒111.32km、経度は緯度で縮む。極付近の0除算を避けるため cos に下限を設ける。
        $latDelta = $km / 111.32;
        $lngDelta = $km / (111.32 * max(cos(deg2rad($lat)), 0.01));

        $minLat = $lat - $latDelta;
        $maxLat = $lat + $latDelta;
        $minLng = $lng - $lngDelta;
        $maxLng = $lng + $lngDelta;

        if (DB::connection()->getDriverName() === 'mysql') {
            $row = DB::selectOne(
                'SELECT id, ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) AS dist
                 FROM pois
                 WHERE type = ? AND id <> ?
                   AND latitude BETWEEN ? AND ? AND longitude BETWEEN ? AND ?
                 ORDER BY dist ASC
                 LIMIT 1',
                [$lng, $lat, $poi->type, $poi->id, $minLat, $maxLat, $minLng, $maxLng],
            );

            if ($row === null) {
                return null;
            }

            return [(int) $row->id, (int) round((float) $row->dist)];
        }

        // MySQL 以外（テストの SQLite など）: 矩形候補を取得し PHP で最小距離を求める。
        $candidates = DB::table('pois')
            ->select('id', 'latitude', 'longitude')
            ->where('type', $poi->type)
            ->where('id', '<>', $poi->id)
            ->whereBetween('latitude', [$minLat, $maxLat])
            ->whereBetween('longitude', [$minLng, $maxLng])
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $bestId = null;
        $bestM = null;
        foreach ($candidates as $c) {
            $d = $this->haversineMeters($lat, $lng, (float) $c->latitude, (float) $c->longitude);
            if ($bestM === null || $d < $bestM) {
                $bestM = $d;
                $bestId = (int) $c->id;
            }
        }

        return [$bestId, (int) round((float) $bestM)];
    }

    /** 2点間の大圏距離（メートル）。ST_Distance_Sphere と実質同値。 */
    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * 現スコープの計算済み行のうち nearest_same_type_m >= 3km の件数を報告する（検証用）。
     */
    private function reportIsolatedTotal(?string $type, ?string $prefecture): void
    {
        $q = Poi::query()
            ->whereNotNull('nearest_computed_at')
            ->where('nearest_same_type_m', '>=', self::ISOLATED_M);
        if ($type !== null && $type !== '') {
            $q->where('type', $type);
        }
        if ($prefecture !== null && $prefecture !== '') {
            $q->where('prefecture', $prefecture);
        }

        $this->line(sprintf(
            '検証: 計算済み全体で nearest_same_type_m >= %dm は %d件（type=%s / prefecture=%s）',
            self::ISOLATED_M,
            $q->count(),
            $type !== null && $type !== '' ? $type : 'all',
            $prefecture !== null && $prefecture !== '' ? $prefecture : 'all',
        ));
    }
}
