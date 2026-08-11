<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Poi;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class FetchPois extends Command
{
    protected $signature = 'poi:fetch
        {--type= : gas_station, convenience_store, or car_wash}
        {--region= : 特定の地方のみ実行（例: 中部）。未指定は全地方}
        {--regions= : 複数の地方をカンマ区切りで指定（例: 関東,中部,近畿）。未指定は全地方}
        {--retries=4 : 1地方あたりのリトライ回数（待機は 5/15/45/120秒。上限120秒）}';

    protected $description = 'Overpass APIからPOIデータ（ガソリンスタンド・コンビニ・道の駅・洗車場）を取得';

    // Overpass ミラー（第1候補が安定・失敗時に第2候補へローテーション）。
    private const OVERPASS_ENDPOINTS = [
        'https://overpass.kumi.systems/api/interpreter',
        'https://overpass-api.de/api/interpreter',
    ];

    // 地方リクエストの既定リトライ回数と指数バックオフ（秒）。リトライ1/2/3/4回目の待機に対応。
    // 相手は公共の共有APIなので、間隔を短縮する方向へは変更しないこと。
    // 5回目以降を指定された場合も待機は MAX_BACKOFF_SECONDS で頭打ちにする。
    private const DEFAULT_RETRIES = 4;
    private const RETRY_BACKOFF = [5, 15, 45, 120];
    private const MAX_BACKOFF_SECONDS = 120;

    // 実行時に --retries から決まるリトライ回数。
    private int $maxRetries = self::DEFAULT_RETRIES;

    // 直近の fetchRegion が null を返した理由（ログ・サマリ用）。
    private ?string $lastError = null;

    // リトライ対象の HTTP ステータス（429 レート制限 / 5xx サーバー過負荷）。
    private const RETRYABLE_STATUSES = [429, 502, 503, 504];

    // 連続リクエストによるレート制限回避のため、地方と地方の間に挟むスリープ（秒）。
    private const REGION_WAIT_SECONDS = 3;

    // エンドポイントのローテーション位置（リトライ・地方をまたいで進める）。
    private int $endpointCursor = 0;

    // OSM の node / way は id 名前空間が別のため、同一 type 内で node と way が同一 id だと
    // (osm_id, type) unique 制約で衝突する。car_wash の way/relation はこの値でオフセットして分離する。
    // node id < ~1.3e10 / way id < ~2e9 に対し 1e13 は十分離れており、bigInteger の範囲内。
    private const WAY_ID_OFFSET = 10_000_000_000_000;

    // 畜産の防疫設備（バイクの洗車場ではない）。名称に含む要素は取り込まない。
    private const CAR_WASH_EXCLUDE = '車両消毒槽';

    // 取得対象の矩形（Overpass の bbox）。書式は [南緯, 西経, 北緯, 東経]。
    //
    // ※ 各区画の値は「推測ではなく」国土数値情報N03の行政区域ポリゴン（1,905市区町村・
    //   125,130ポリゴン）を本番DBで実測して設計したもの。全市区町村が最低1つの矩形に入ることを
    //   SQLで検証済み（外れるのは小笠原村の無人島＝沖ノ鳥島・南鳥島と、東京都所属未定地のみ）。
    //   旧8矩形は継ぎ目に穴があり54市区町村（三陸海岸・隠岐・佐渡・伊豆諸島・小笠原ほか）を
    //   取りこぼしていた。値を安易に動かすと日本の一部が再び取得対象から外れるので不可。
    //
    // ※ 韓国・済州島（33.2〜33.6N, 126.1〜126.9E）の混入（旧「九州沖縄」矩形が済州島のGS164件を
    //   拾っていた）を避けるため、九州の西端 128.1 と南西諸島の北端 31.0 で緯度・経度の両方から排除する。
    // ※ 東北の東端 142.1 は三陸海岸（岩手県の東端 142.07）を収容するための値。
    private const REGIONS = [
        '北海道'     => [41.3, 139.3, 45.6, 148.9],
        '東北'       => [36.8, 139.3, 41.6, 142.1],
        '関東'       => [34.8, 138.4, 37.2, 140.9],
        '伊豆諸島'   => [32.3, 138.7, 34.9, 140.0],
        '小笠原'     => [26.5, 141.9, 27.9, 142.4],
        '中部'       => [34.5, 135.4, 38.6, 139.7],
        '近畿'       => [33.4, 134.3, 35.8, 137.0],
        '中国'       => [33.7, 130.7, 37.3, 134.5],
        '四国'       => [32.6, 132.0, 34.6, 134.9],
        // 五島列島(西端128.09)と対馬(北端34.71)を1矩形で満たすと北西角が韓国南岸(巨済島・統営,
        // 34.6〜34.8N/128.2〜128.7E)を含み、実際に韓国のGS3件を取得した。そこで経度129.0で分割。
        // 五島側は北端33.6に抑える(128.1〜129.0で33.6N以北の日本の陸地は無い。対馬は129.16E以東、
        // 壱岐は129.6E以東)。韓国側は128.75E以東に陸地が無く、釜山は35.05N以北で九州側にも入らない。
        '五島列島'   => [30.9, 128.1, 33.6, 129.0],
        '九州'       => [30.9, 129.0, 34.8, 132.2],
        '南西諸島'   => [24.0, 122.9, 31.0, 131.4],
    ];

    private const TYPE_QUERIES = [
        'gas_station' => '[out:json][timeout:120];(node["amenity"="fuel"]({bbox});way["amenity"="fuel"]({bbox}););out center;',
        'convenience_store' => '[out:json][timeout:120];(node["shop"="convenience"]["name"~"セブン|ローソン|ファミリーマート|ミニストップ|デイリーヤマザキ|セイコーマート|ポプラ|NewDays"]({bbox}););out center;',
        // 道の駅は roadside_stations（国交省公式一覧）へ一本化したため、OSM由来の取得は廃止。
        // 洗車場（amenity=car_wash）。way が全体の半数超のため node/way 両方を取得し、
        // way は座標を持たないので「out center tags;」で center 座標とタグを得る。
        'car_wash' => '[out:json][timeout:120];(node["amenity"="car_wash"]({bbox});way["amenity"="car_wash"]({bbox}););out center tags;',
    ];

    public function handle(): int
    {
        $typeOption = $this->option('type');
        $types = $typeOption
            ? [$typeOption]
            : array_keys(self::TYPE_QUERIES);

        foreach ($types as $type) {
            if (! isset(self::TYPE_QUERIES[$type])) {
                $this->error("不明なタイプ: {$type}");
                return self::FAILURE;
            }
        }

        $this->maxRetries = max(0, (int) $this->option('retries'));

        // --region（単一） / --regions（カンマ区切り）。どちらも REGIONS のキーと完全一致で照合。
        // 未指定なら全地方。不正値は有効な地方名を提示して終了。
        $regionsToProcess = $this->resolveRegions();
        if ($regionsToProcess === null) {
            return self::FAILURE;
        }

        $totalInserted = 0;
        $skippedDisinfection = 0; // 車両消毒槽としてスキップした件数（car_wash）
        $succeeded = [];          // ['type'=>, 'region'=>]
        $failed = [];             // ['type'=>, 'region'=>]

        foreach ($types as $type) {
            $this->info("=== {$type} の取得開始 ===");

            $regionNames = array_keys($regionsToProcess);
            foreach ($regionNames as $i => $regionName) {
                $bbox = $regionsToProcess[$regionName];
                $this->info("  [{$regionName}] リクエスト中...");

                $query = str_replace(
                    '{bbox}',
                    implode(',', $bbox),
                    self::TYPE_QUERIES[$type]
                );

                // 生成した Overpass クエリは -v で確認できる（実際に叩かずに文字列を検証したい場合用）。
                if ($this->getOutput()->isVerbose()) {
                    $this->line("  Overpass query: {$query}");
                }

                $elements = $this->fetchRegion($query, $regionName);

                if ($elements === null) {
                    // リトライ尽き or リトライ不可エラー（詳細は fetchRegion 内で出力済み）。
                    $error = $this->lastError ?? '不明なエラー';
                    $failed[] = ['type' => $type, 'region' => $regionName, 'error' => $error];

                    // 標準出力は捨てられることがあるので、失敗は必ずログにも残す。
                    Log::warning('poi:fetch 地方の取得に失敗', [
                        'region' => $regionName,
                        'type' => $type,
                        'error' => $error,
                        'retries' => $this->maxRetries,
                    ]);
                } else {
                    $count = 0;
                    foreach ($elements as $element) {
                        $lat = $element['lat'] ?? $element['center']['lat'] ?? null;
                        $lon = $element['lon'] ?? $element['center']['lon'] ?? null;

                        if (! $lat || ! $lon) {
                            continue;
                        }

                        $tags = $element['tags'] ?? [];

                        // 畜産の防疫設備（車両消毒槽）はバイクの洗車場ではないため除外。
                        if (isset($tags['name']) && mb_strpos($tags['name'], self::CAR_WASH_EXCLUDE) !== false) {
                            $skippedDisinfection++;
                            continue;
                        }

                        Poi::updateOrCreate(
                            ['osm_id' => $this->resolveOsmId($element, $type), 'type' => $type],
                            [
                                'name' => $tags['name'] ?? $tags['brand'] ?? null,
                                'latitude' => $lat,
                                'longitude' => $lon,
                                'address' => $this->buildAddress($tags),
                                'brand' => $tags['brand'] ?? $tags['operator'] ?? null,
                                'opening_hours' => $tags['opening_hours'] ?? null,
                                'self_service' => $tags['self_service'] ?? null,
                                'automated' => $tags['automated'] ?? null,
                            ]
                        );
                        $count++;
                    }

                    $totalInserted += $count;
                    $this->info("  [{$regionName}] {$count}件 登録/更新");
                    $succeeded[] = ['type' => $type, 'region' => $regionName];
                }

                // 地方間ウェイト（最後の地方の後はスリープ不要）。
                if ($i < count($regionNames) - 1) {
                    sleep(self::REGION_WAIT_SECONDS);
                }
            }
        }

        if ($skippedDisinfection > 0) {
            $this->info("除外（車両消毒槽）: {$skippedDisinfection} 件スキップ");
        }

        $this->printSummary($succeeded, $failed, $totalInserted);

        // Overpass は公共の共有APIで、一部地方の 504 は通常運転の範囲。
        // 1つでも成功していれば正常終了とし、全滅したときだけ異常終了する
        // （毎回 exit 1 だと「本当に全滅した回」と区別がつかない）。
        if (empty($succeeded)) {
            $this->error('全地方で取得に失敗しました。');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * --region / --regions を解決する。未指定なら全地方。
     * 不正な地方名が含まれる場合は有効な名前一覧を表示して null を返す。
     *
     * @return array<string, array<int, float|int>>|null
     */
    private function resolveRegions(): ?array
    {
        /** @var list<string> $names */
        $names = [];

        $single = (string) ($this->option('region') ?? '');
        if ($single !== '') {
            $names[] = $single;
        }

        $multiple = (string) ($this->option('regions') ?? '');
        if ($multiple !== '') {
            foreach (explode(',', $multiple) as $name) {
                $name = trim($name);
                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        if ($names === []) {
            return self::REGIONS;
        }

        // REGIONS のキーと完全一致で照合する（部分一致・表記ゆれは受け付けない）。
        $unknown = array_values(array_filter(
            array_unique($names),
            static fn (string $name): bool => ! isset(self::REGIONS[$name])
        ));

        if ($unknown !== []) {
            $this->error('不明な地方: ' . implode(' / ', $unknown));
            $this->line('有効な地方名: ' . implode(' / ', array_keys(self::REGIONS)));

            return null;
        }

        // 指定順ではなく REGIONS の定義順で処理する（従来の全地方実行と同じ順序に揃える）。
        $selected = [];
        foreach (self::REGIONS as $name => $bbox) {
            if (in_array($name, $names, true)) {
                $selected[$name] = $bbox;
            }
        }

        return $selected;
    }

    /**
     * Overpass へリクエストし、成功時は elements 配列を返す。
     * 429 / 5xx / 接続タイムアウトは最大 $this->maxRetries 回、指数バックオフ＋ミラー切替でリトライ。
     * 429 以外の 4xx（クエリ構文エラー等）は即失敗（null）。失敗時は null。
     * 失敗理由は $this->lastError に残し、呼び出し側のログ・サマリで使う。
     */
    private function fetchRegion(string $query, string $regionName): ?array
    {
        $this->lastError = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            $endpoint = $this->nextEndpoint();

            try {
                $response = Http::timeout(120)
                    ->withHeaders([
                        'User-Agent' => 'MotoHub/1.0 (https://motohub.jp)',
                    ])
                    ->withBody('data=' . urlencode($query), 'application/x-www-form-urlencoded')
                    ->post($endpoint);

                if ($response->successful()) {
                    return $response->json()['elements'] ?? [];
                }

                $status = $response->status();

                // 429 以外の 4xx はリトライしない（クエリ構文エラー等はリトライしても無駄）。
                if ($status >= 400 && $status < 500 && $status !== 429) {
                    $this->lastError = "HTTP {$status}（リトライ不可）";
                    $this->error("  [{$regionName}] HTTP {$status}（リトライ不可・即失敗）");
                    return null;
                }

                // リトライ対象外のステータス（想定外の 5xx 等）も失敗扱い。
                if (! in_array($status, self::RETRYABLE_STATUSES, true)) {
                    $this->lastError = "HTTP {$status}（想定外のステータス）";
                    $this->error("  [{$regionName}] HTTP {$status}（想定外・失敗）");
                    return null;
                }

                if ($attempt >= $this->maxRetries) {
                    $this->lastError = "HTTP {$status}（{$this->maxRetries}回リトライ後も失敗）";
                    $this->error("  [{$regionName}] HTTP {$status}（{$this->maxRetries}回リトライ後も失敗）");
                    return null;
                }

                $wait = $this->backoffSeconds($attempt);
                $retryNo = $attempt + 1;
                $this->warn("  [{$regionName}] {$status} のため{$wait}秒後に再試行（{$retryNo}/{$this->maxRetries}）");
                sleep($wait);
            } catch (ConnectionException $e) {
                // 接続タイムアウト等 → リトライ対象。
                if ($attempt >= $this->maxRetries) {
                    $this->lastError = "接続失敗（{$this->maxRetries}回リトライ後も失敗）: {$e->getMessage()}";
                    $this->error("  [{$regionName}] 接続失敗（{$this->maxRetries}回リトライ後も失敗）: {$e->getMessage()}");
                    return null;
                }

                $wait = $this->backoffSeconds($attempt);
                $retryNo = $attempt + 1;
                $this->warn("  [{$regionName}] 接続タイムアウトのため{$wait}秒後に再試行（{$retryNo}/{$this->maxRetries}）");
                sleep($wait);
            }
        }

        $this->lastError ??= 'リトライ上限に到達';

        return null;
    }

    /**
     * $attempt 回目（0始まり）の失敗後に待つ秒数。5 → 15 → 45 → 120 秒。
     * 表を超える回数を --retries で指定された場合も 120 秒で頭打ちにする
     * （公共APIなので、これ以上短くも長くもしない）。
     */
    private function backoffSeconds(int $attempt): int
    {
        return self::RETRY_BACKOFF[$attempt] ?? self::MAX_BACKOFF_SECONDS;
    }

    /**
     * 使用するエンドポイントを返し、カーソルを次の候補へ進める。
     * リトライのたびに次ミラーへ切り替わり、地方をまたいでも負荷が分散する。
     */
    private function nextEndpoint(): string
    {
        $endpoints = self::OVERPASS_ENDPOINTS;
        $url = $endpoints[$this->endpointCursor % count($endpoints)];
        $this->endpointCursor++;

        return $url;
    }

    /**
     * 実行サマリ（成功／失敗の地方一覧・登録更新件数）を出力。失敗があれば再実行コマンド例も提示。
     * 件数は「種別×地方」の組で数える（1種別だけ失敗した地方も見落とさないため）。
     *
     * @param  array<int, array{type: string, region: string}>  $succeeded
     * @param  array<int, array{type: string, region: string, error: string}>  $failed
     */
    private function printSummary(array $succeeded, array $failed, int $totalInserted): void
    {
        $this->newLine();
        $this->info('===== 実行サマリ =====');
        $this->info(sprintf(
            '成功: %d / 失敗: %d（種別×地方） / 登録更新: %d 件',
            count($succeeded),
            count($failed),
            $totalInserted
        ));

        foreach ($succeeded as $s) {
            $this->line("  ✓ [{$s['type']}] {$s['region']}");
        }

        if (empty($failed)) {
            return;
        }

        $this->newLine();
        $this->warn('失敗した地方: ' . implode(' / ', array_values(array_unique(
            array_column($failed, 'region')
        ))));

        foreach ($failed as $f) {
            $this->line("  ✗ [{$f['type']}] {$f['region']}: {$f['error']}");
        }

        // 種別ごとに失敗地方をまとめて --regions で再実行できる形にする。
        $byType = [];
        foreach ($failed as $f) {
            $byType[$f['type']][] = $f['region'];
        }

        $this->newLine();
        $this->warn('失敗分の再実行コマンド例:');
        foreach ($byType as $type => $regions) {
            $list = implode(',', array_values(array_unique($regions)));
            $this->line("  php artisan poi:fetch --type={$type} --regions={$list}");
        }
    }

    /**
     * 保存する osm_id を決める。
     *
     * OSM の node と way は id 名前空間が別で、同一 type 内で衝突し得る。
     * 既存タイプ（gas_station / convenience_store / michi_no_eki）の保存済み osm_id を
     * 壊さないよう、オフセット適用は car_wash に限定する。node はそのまま、way/relation は
     * WAY_ID_OFFSET を加えて分離する。
     */
    private function resolveOsmId(array $element, string $type): int
    {
        $id = (int) $element['id'];

        if ($type === 'car_wash' && ($element['type'] ?? 'node') !== 'node') {
            return self::WAY_ID_OFFSET + $id;
        }

        return $id;
    }

    private function buildAddress(array $tags): ?string
    {
        $parts = array_filter([
            $tags['addr:province'] ?? $tags['addr:state'] ?? null,
            $tags['addr:city'] ?? null,
            $tags['addr:district'] ?? null,
            $tags['addr:quarter'] ?? null,
            $tags['addr:neighbourhood'] ?? null,
            $tags['addr:street'] ?? null,
            $tags['addr:housenumber'] ?? null,
        ]);

        return $parts ? implode('', $parts) : null;
    }
}
