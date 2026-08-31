<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Yahoo!ショッピング商品検索APIのレート保護（ブレーカーのみ）を1か所に集約する共有ゲート。
 *
 * 楽天は {@see RakutenRateGate} に切り出され全経路で共有されているが、Yahoo は同じ役割の
 * コードが ProductSearchService の中に閉じており、PartsController::compare() から使えず
 * 迂回になっていた（本番で ProductSearchService が Yahoo を休止させたのと同じ秒に
 * compare() が Yahoo を叩いて429を受けた）。ここへ移して両経路で同じブレーカーを共有する。
 *
 * ★間隔制御（acquireSlot）は Yahoo には入れない。現状存在せず、追加すると全ページの表示が
 *   遅くなりうるため別途判断する。本クラスはブレーカー（休止フラグ）だけを担う。
 *   ＝間隔制御を持つのは楽天（RakutenRateGate）のみ。ただし「Yahoo は429が出ない」わけではない
 *   （実測で Yahoo も429を返す）。だからこそブレーカーは必要で、それを共有化するのが本クラス。
 *
 * 状態は共有キャッシュ（本番は file ストア）に持つので、php-fpm ワーカー間でも
 * Artisan コマンドとの間でも同じ休止状態を共有する。インスタンスはステートレス。
 *
 * 提供するプリミティブ（RakutenRateGate と同形。acquireSlot は持たない）:
 *   - isPaused()         … 429後の休止中か（ブレーカー）
 *   - pausedReason()     … 休止中である旨のメッセージ
 *   - pause()            … 429を受けたので休止させる（ブレーカーを立てる）
 *   - logErrorResponse() … エラー応答を統一書式でログに残す
 */
final class YahooRateGate
{
    /**
     * 429 を受けたあと Yahoo への呼び出しを止めるブレーカーの共有キー。
     * ★ProductSearchService が使っていたキー名（'parts:breaker:yahoo'）をそのまま引き継ぐ。
     *   変えると本番で作動中のブレーカー状態が失われるため不可侵。
     */
    private const BREAKER_KEY = 'parts:breaker:yahoo';

    /**
     * 429 を受けたあと Yahoo を止める時間（秒）。楽天側（RakutenRateGate::BREAKER_TTL）と揃える。
     */
    private const BREAKER_TTL = 30;

    /** 429後の休止中なら true。 */
    public function isPaused(): bool
    {
        return (bool) Cache::get(self::BREAKER_KEY, false);
    }

    /** 休止中である旨のメッセージ（呼び出し側の lastErrors / ログ用）。 */
    public function pausedReason(): string
    {
        return '休止中（429を受けたため'.self::BREAKER_TTL.'秒間の休止）';
    }

    /**
     * 429 を受けたので Yahoo を BREAKER_TTL 秒だけ休止させる。
     * 立てるときだけログに残す（休止中のスキップまでログすると、抑えたはずの行数が戻ってしまう）。
     * $context は呼び出し元の識別子（'ProductSearchService' / 'PartsController' 等）。
     *
     * ★ログ文言は ProductSearchService::tripBreaker() が出していた
     *   「{context} yahoo を30秒休止します（レート制限）」を一字一句そのまま維持する。
     */
    public function pause(string $keyword, string $context): void
    {
        Cache::put(self::BREAKER_KEY, true, self::BREAKER_TTL);
        Log::warning($context.' yahoo を'.self::BREAKER_TTL.'秒休止します（レート制限）', [
            'keyword' => $keyword,
        ]);
    }

    /**
     * Yahoo のエラー応答を統一書式でログに残す（RakutenRateGate::logErrorResponse と同形）。
     * status・keyword・本文先頭200文字を必ず同じ形で残す（障害時の切り分けに必須）。
     */
    public function logErrorResponse(string $context, int $status, string $keyword, string $body): void
    {
        Log::warning($context.' yahoo がエラー応答', [
            'status' => $status,
            'keyword' => $keyword,
            'body' => mb_substr($body, 0, 200),
        ]);
    }
}
