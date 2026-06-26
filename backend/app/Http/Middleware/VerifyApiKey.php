<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 外部データAPIのAPIキー認証（第1段階・限定提供）。
 * - X-API-Key ヘッダ（無ければ ?api_key= クエリ）でキーを受ける。
 * - 平文を SHA-256 でハッシュ化し、有効な api_keys 行と照合。
 * - 無効/不在 → 401(JSON)。スタックトレースや 5xx は出さない。
 * - 成功時は api_key_id を request 属性へ（throttle のキー単位制限で使う）。
 */
final class VerifyApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) ($request->header('X-API-Key') ?: $request->query('api_key', ''));

        if ($key === '') {
            return response()->json([
                'error' => 'api_key_required',
                'message' => 'APIキーが必要です。X-API-Key ヘッダにキーを指定してください。',
            ], 401);
        }

        $apiKey = ApiKey::where('key_hash', ApiKey::hashKey($key))
            ->where('is_active', true)
            ->first();

        if ($apiKey === null) {
            return response()->json([
                'error' => 'invalid_api_key',
                'message' => 'APIキーが無効です。',
            ], 401);
        }

        // 最終利用日時は書き込み過多を避け、1分以上経過時のみ更新（タイムスタンプ自動更新は抑止）。
        if ($apiKey->last_used_at === null || $apiKey->last_used_at->lt(now()->subMinute())) {
            $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('api_key_id', $apiKey->id);

        return $next($request);
    }
}
