<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 楽天イチバ商品検索APIのレート保護を1か所に集約する共有ゲート。
 *
 * 楽天のレート制限は「秒単位・アプリID単位の共有枠」であり、プロセスや経路をまたいで
 * 効かせないと意味がない。以前は ProductSearchService だけが間隔制御とブレーカーを
 * 内部に持っていたため、BikePartsService と PartsController の楽天呼び出しが
 * 無制限に枠を食い、間隔を守っている側だけが 429 を受けていた。
 *
 * このクラスの状態はすべて共有キャッシュ（本番は file ストア）に持つので、
 * php-fpm のワーカー間でも Artisan コマンドとの間でも同じ枠・同じ休止状態を共有する。
 * インスタンス自体はステートレス（フィールドを持たない）なので都度 app() で解決してよい。
 *
 * 提供するのは以下のプリミティブで、HTTP 組み立てとレスポンス整形は各経路に委ねる
 * （経路ごとにパラメータもパース方法も違うため）:
 *   - isPaused()           … 429後の休止中か（ブレーカー）
 *   - acquireSlot()        … 次の呼び出し枠を1つ取る（間隔制御）
 *   - pause()              … 429を受けたので休止させる（ブレーカーを立てる）
 *   - logErrorResponse()   … エラー応答を統一書式でログに残す
 */
final class RakutenRateGate
{
    /**
     * 楽天への最小リクエスト間隔（秒）。
     *
     * 本番の429本文は {"statusCode":429,"message":"Rate limit is exceeded. Try again in 1 seconds."}
     * ＝日次クォータではなく秒単位の制限。1.0ちょうどだと境界で弾かれるため少し余裕を持たせる。
     */
    private const MIN_INTERVAL = 1.1;

    /**
     * 順番待ちの上限（秒）。経路によって分ける。これを超えるなら枠を取らず false を返し、
     * 呼び出し側は楽天を叩かずに空を返す。判定は app()->runningInConsole() で行うので、
     * 呼び出し側は上限を意識しなくてよい。
     *
     * ■ Web = 0.5秒:
     *   車種ページ等のタイヤ/パーツ枠は render の中で同期的に呼ばれるため、待ち行列が伸びると
     *   ページの応答時間がそのまま伸びる。クローラーが集中すると php-fpm のワーカーが待ちで
     *   埋まりサイト全体が詰まる恐れがある。商品枠が消えるほうが、ページが詰まるよりましと判断し、
     *   3秒から0.5秒へ大幅に短くした。
     *
     * ■ CLI = 60秒（既定）:
     *   parts:refresh 等のバッチや fitment:probe はワーカーを占有せず、応答時間の制約も無い。
     *   取りこぼしを減らすため、待ってでも枠を取りに行けるよう長めにする。特に 429 後の 30 秒休止
     *   （BREAKER_TTL）が明けた直後、次の枠まで最大 MIN_INTERVAL 待つ必要があるが、上限が短いと
     *   その待ちで片っ端から中止され、1回のレート制限が大量の status 0 中止へ増幅する。60秒あれば待って再開できる。
     *
     * どちらも config('services.rakuten.gate_max_wait_web' / 'gate_max_wait_cli') で上書き可能（既定はこの定数）。
     */
    private const MAX_WAIT_WEB = 0.5;

    private const MAX_WAIT_CLI = 60.0;

    /** 「次に楽天を叩いてよい時刻」（UNIX秒・float）を置く共有キー。 */
    private const GATE_NEXT_KEY = 'parts:rakuten:next_call_at';

    /** 上のキーを read-modify-write するあいだだけ持つ短命ミューテックス。 */
    private const GATE_MUTEX_KEY = 'parts:rakuten:gate_mutex';

    /**
     * 429 を受けたあと楽天への呼び出しを止めるブレーカーの共有キー。
     * ProductSearchService が単独で使っていたキー名をそのまま引き継ぎ、3経路で共有する。
     */
    private const BREAKER_KEY = 'parts:breaker:rakuten';

    /**
     * 429 を受けたあと楽天を止める時間（秒）。
     *
     * 楽天の指示は「1秒後に再試行」だが、429が出ている時点で他ワーカーも詰まっているので、
     * 1秒では足りない。かといって長すぎると商品枠が消えたままになるため30秒とした。
     * 間隔制御が効いていれば本来ここには来ない。来たら「制御しきれていない」印。
     */
    private const BREAKER_TTL = 30;

    /**
     * この経路で許容する順番待ちの上限（秒）。Web/CLI で切り替わる。
     * エラーメッセージにも使うため public に露出する。
     */
    public function maxWaitSeconds(): float
    {
        return app()->runningInConsole()
            ? (float) config('services.rakuten.gate_max_wait_cli', self::MAX_WAIT_CLI)
            : (float) config('services.rakuten.gate_max_wait_web', self::MAX_WAIT_WEB);
    }

    /**
     * 429後の休止中なら true。$respectBreaker=false のとき（fitment:probe の明示的な再試行だけ）は
     * 呼び出し側で本メソッドを飛ばして迂回する。
     */
    public function isPaused(): bool
    {
        return (bool) Cache::get(self::BREAKER_KEY, false);
    }

    /** 休止中である旨のメッセージ（呼び出し側の lastErrors / ログ用）。 */
    public function pausedReason(): string
    {
        return '休止中（429を受けたため'.self::BREAKER_TTL.'秒間の休止）';
    }

    /** 順番待ちが上限を超えて中止した旨のメッセージ（呼び出し側の lastErrors / ログ用）。 */
    public function waitExceededReason(): string
    {
        return '間隔制御の順番待ちが'.$this->maxWaitSeconds().'秒を超えたため中止';
    }

    /**
     * 楽天を叩く「枠」を1つ取る。取れたら true、待ち時間の上限を超えたら false。
     *
     * ■ 方式: 共有キャッシュに「次に叩いてよい時刻」を持ち、Cache::add() の原子性で更新を直列化する。
     *
     * ■ なぜ Cache::lock() ではなくこの方式か:
     *   Cache::lock() の TTL は整数秒しか指定できず、必要な 1.1 秒を表現できない。
     *   TTL=2 に丸めるとスループットが半分（0.5 req/s）になり、TTL=1 だと楽天の秒境界に張り付いて
     *   429 が残る。時刻を持てば秒未満の間隔をそのまま表現できる。
     *
     * ■ なぜ Cache::lock() ではなく Cache::add() をミューテックスに使うか:
     *   Cache::lock() は LockProvider を実装していないストアで BadMethodCallException を投げる。
     *   Repository::add() は全ストアで使え、file ストアでは LockableFile の flock で排他されるため
     *   （FileStore::add）、php-fpm ワーカー間で原子的に効く。本番は CACHE_STORE=file。
     *   ミューテックスは TTL 1秒。プロセスが握ったまま死んでも1秒で自動的に解放される。
     */
    public function acquireSlot(): bool
    {
        $deadline = microtime(true) + $this->maxWaitSeconds();

        while (true) {
            $waitUntil = null;

            if (Cache::add(self::GATE_MUTEX_KEY, 1, 1)) {
                try {
                    $now = microtime(true);
                    $nextAt = (float) Cache::get(self::GATE_NEXT_KEY, 0.0);

                    if ($nextAt <= $now) {
                        // いまの枠は空いている。取ったうえで次の枠を予約しておく。
                        Cache::put(self::GATE_NEXT_KEY, $now + self::MIN_INTERVAL, 60);

                        return true;
                    }

                    $waitUntil = $nextAt;
                } finally {
                    Cache::forget(self::GATE_MUTEX_KEY);
                }
            }

            // ミューテックスを取れなかった＝他ワーカーが更新中。少しだけ空けて取り直す。
            $waitUntil ??= microtime(true) + 0.02;

            if ($waitUntil >= $deadline) {
                return false;
            }

            $sleepMicros = (int) (($waitUntil - microtime(true)) * 1_000_000);
            if ($sleepMicros > 0) {
                usleep($sleepMicros);
            }
        }
    }

    /**
     * 429 を受けたので楽天を BREAKER_TTL 秒だけ休止させる。
     * 立てるときだけログに残す（休止中のスキップまでログすると、抑えたはずの行数が戻ってしまう）。
     * $context は呼び出し元の識別子（'ProductSearchService' 等）で、どの経路が枠を焦がしたか分かるようにする。
     */
    public function pause(string $keyword, string $context): void
    {
        Cache::put(self::BREAKER_KEY, true, self::BREAKER_TTL);
        Log::warning($context.' 楽天を'.self::BREAKER_TTL.'秒休止します（レート制限）', [
            'keyword' => $keyword,
        ]);
    }

    /**
     * 楽天のエラー応答を統一書式でログに残す。3経路すべてがこれを使うことで、
     * status・keyword・本文先頭200文字が必ず同じ形で残る（障害時の切り分けに必須）。
     * 本文には 400 の原因（不正な検索語・パラメータ等）が出るため残すが、200文字で頭打ちにする。
     */
    public function logErrorResponse(string $context, int $status, string $keyword, string $body): void
    {
        Log::warning($context.' rakuten がエラー応答', [
            'status' => $status,
            'keyword' => $keyword,
            'body' => mb_substr($body, 0, 200),
        ]);
    }
}
