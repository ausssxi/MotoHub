<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Services\Bike\BikePartsService;
use App\Support\RakutenRateGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class RefreshBikeParts extends Command
{
    protected $signature = 'parts:refresh
        {--limit=800 : 1回の実行で取得する最大モデル数}
        {--all : キャッシュ有無を問わず取得する（デフォルトは未保持＝失効分のみ）}';

    protected $description = '在庫あり車種の楽天パーツをキャッシュへ事前取得（render pathから分離・日次ローテーション）';

    /**
     * ブレーカー（429休止）明けを待つ1回あたりの上限秒。RakutenRateGate::BREAKER_TTL=30秒 より
     * 少し長く取り、他プロセスが立て直していなければ通常はこの範囲で必ず明ける。
     */
    private const BREAKER_WAIT_SECONDS = 45;

    /** ブレーカー明けを待つあいだのポーリング間隔（秒）。 */
    private const BREAKER_POLL_SECONDS = 2;

    /**
     * 連続で休止に阻まれた回数の上限。これを超えたら「待っても回復しない」と判断し、
     * 黙って走り抜けて空を量産せず、件数を報告して中断する。取得成功でリセットする。
     */
    private const MAX_BREAKER_WAITS = 5;

    public function handle(BikePartsService $parts, RakutenRateGate $gate): int
    {
        // 認証情報が無ければ全モデルが未取得になるだけなので、churnせず即中断する。
        if (! config('services.rakuten.app_id') || ! config('services.rakuten.access_key')) {
            $this->error('楽天APIの認証情報が未設定です（app_id/access_key）。中断します。');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $all = (bool) $this->option('all');
        $startTime = microtime(true);

        // 在庫あり車種を人気順（active掲載数desc）に。失効分を優先的に埋める。
        $models = BikeModel::query()
            ->whereHas('listings', fn ($q) => $q->active())
            ->withCount(['listings' => fn ($q) => $q->active()])
            ->orderByDesc('listings_count')
            ->get(['id', 'name'])
            ->all();

        $this->info('在庫車種: '.count($models)." 件 / 今回上限: {$limit}".($all ? '（--all 全件再取得）' : '（失効分のみ）'));

        $done = 0;         // 取得を最後まで試みた件数（商品あり＋確定的に空）
        $emptyOk = 0;      // うち、確定的に商品なし（正常に空をキャッシュ）
        $skipped = 0;      // キャッシュ有効でスキップ
        $unfetched = 0;    // 未取得で見送った件数（このバッチで取得できず先へ送った実モデル数）
        $breakerWaitsTotal = 0; // 休止明けを待った延べ回数（レート制限の摩擦を可視化・サマリ用）
        $breakerWaitsConsec = 0; // 連続で休止に阻まれた回数（成功でリセット・中断判定用）
        $aborted = false;

        $i = 0;
        $n = count($models);

        // 同じモデルを取り直せるよう foreach ではなくインデックス制御にする。
        // 「未取得（休止）」のときは $i を進めず、休止明けを待ってから取り直す。
        while ($done < $limit && $i < $n) {
            $model = $models[$i];

            if (! $all && Cache::has(BikePartsService::cacheKey($model))) {
                $skipped++;
                $i++;

                continue;
            }

            $outcome = $parts->refreshForModel($model);
            $status = $outcome['status'];

            if ($status === BikePartsService::RESULT_UNFETCHED) {
                // 休止（ブレーカー）が原因なら、次のモデルへ走り抜けずに休止明けを待って取り直す。
                // ★取り直して成功すればそのモデルは「見送り」に数えない（$unfetched を増やさない）。
                //   代わりに休止待機の延べ回数を記録し、レート制限の摩擦をサマリに残す。
                if ($gate->isPaused()) {
                    $breakerWaitsTotal++;
                    if (! $this->recoverFromPause($gate, $breakerWaitsConsec)) {
                        // 回復せず中断。取りかけていた当該モデルは今回見送り扱い。
                        $unfetched++;
                        $aborted = true;
                        break;
                    }

                    continue; // $i を進めない＝同じモデルを取り直す
                }

                // 休止ではない一時要因（枠取得失敗など）。このモデルは今回見送り、次モデルへ。
                $unfetched++;
                $this->warn("[未取得] {$model->name} (#{$model->id}) 枠取得に失敗（キャッシュせず次バッチで再試行）");
                $i++;

                continue;
            }

            // 取得成功（商品あり or 確定的に空）＝休止は明けている。連続待機カウントをリセット。
            $breakerWaitsConsec = 0;
            $done++;
            $i++;

            if ($status === BikePartsService::RESULT_EMPTY) {
                $emptyOk++;
                $this->info("[{$done}/{$limit}] {$model->name} (#{$model->id}) 商品なし（確定・キャッシュ）");
            } else {
                $this->info("[{$done}/{$limit}] {$model->name} (#{$model->id})");
            }
        }

        $elapsed = (int) (microtime(true) - $startTime);
        $this->newLine();

        $summary = "refresh={$done}（うち商品なし={$emptyOk}） 未取得(見送り)={$unfetched} 休止明け待機={$breakerWaitsTotal}回 skip(cache有)={$skipped} 所要={$elapsed}秒";

        if ($aborted) {
            // 走り抜けて空を量産していないことを明示するため、中断でも件数を必ず出す。
            $this->error("中断: ブレーカーが休止から回復せず。ここまでで {$summary}");

            return self::FAILURE;
        }

        $this->info("完了: {$summary}");

        return self::SUCCESS;
    }

    /**
     * ブレーカー（429休止）が明けるのを待つ。true=回復（取り直してよい）、false=中断。
     *
     * ・休止TTL（RakutenRateGate::BREAKER_TTL=30秒）より少し長く待てば通常は明ける。
     * ・連続で MAX_BREAKER_WAITS を超えて阻まれる、または所定時間内に明けない場合は
     *   「回復しない」と判断して false を返し、呼び出し側に中断させる（空の量産を防ぐ）。
     */
    private function recoverFromPause(RakutenRateGate $gate, int &$breakerWaits): bool
    {
        $breakerWaits++;

        if ($breakerWaits > self::MAX_BREAKER_WAITS) {
            $this->warn("ブレーカーが{$breakerWaits}回連続で休止したまま回復しません。走り抜けを避けて中断します。");

            return false;
        }

        $this->warn('ブレーカー作動中（レート制限）。休止明けを待機します…('.$breakerWaits.'/'.self::MAX_BREAKER_WAITS.')');

        $deadline = microtime(true) + self::BREAKER_WAIT_SECONDS;
        while (microtime(true) < $deadline) {
            if (! $gate->isPaused()) {
                $this->info('休止が明けました。再開します。');

                return true;
            }

            sleep(self::BREAKER_POLL_SECONDS);
        }

        // 所定時間待っても明けない（他プロセスが立て直し続ける等）。中断して件数を報告する。
        $this->warn('休止が所定時間内に明けませんでした。中断します。');

        return false;
    }
}
