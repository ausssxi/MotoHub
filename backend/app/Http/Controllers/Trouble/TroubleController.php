<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trouble;

use App\Http\Controllers\Controller;
use App\Models\TroubleEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class TroubleController extends Controller
{
    /**
     * 症状自己診断ツール（トラブル診断）トップ。
     *
     * 決定木はクライアント側で辿る（AIなし・サーバー往復なし）。
     * データは config/diagnosis.php を view に渡すだけ。
     */
    public function index(): View
    {
        return view('trouble.index', [
            'symptoms' => config('diagnosis.symptoms', []),
            'nodes' => config('diagnosis.nodes', []),
            'cards' => config('diagnosis.cards', []),
            'verdicts' => config('diagnosis.verdicts', []),
        ]);
    }

    /**
     * ファネル計測エンドポイント（fire-and-forget）。
     *
     * 全フィールドをホワイトリスト検証し、不正値は保存せず常に 204 を返す
     * （攻撃者にバリデーション情報を渡さない・診断UXを一切ブロックしない）。
     * PIIは保存しない。
     */
    public function track(Request $request): Response
    {
        $noContent = response()->noContent(); // 常に 204

        $sessionId = (string) $request->input('session_id', '');
        $event = (string) $request->input('event', '');

        // session_id 必須（UUID形式）・event はホワイトリスト。外れれば握りつぶし。
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $sessionId)) {
            return $noContent;
        }
        if (! in_array($event, TroubleEvent::EVENTS, true)) {
            return $noContent;
        }

        $symptoms = array_keys(config('diagnosis.symptoms', []));
        $verdicts = array_keys(config('diagnosis.verdicts', []));
        $cards = array_keys(config('diagnosis.cards', []));
        // step はノードID（step_answered）またはカードID（verdict_shown）を取り得る
        $stepKeys = array_merge(array_keys(config('diagnosis.nodes', [])), $cards);

        // 各フィールドをホワイトリスト化（外れ値は null に落とす＝汚さない）
        $symptom = $this->whitelist($request->input('symptom'), $symptoms);
        $verdict = $this->whitelist($request->input('verdict'), $verdicts);
        $card = $this->whitelist($request->input('card'), $cards);
        $cta = $this->whitelist($request->input('cta'), TroubleEvent::CTAS);
        $source = $this->whitelist($request->input('source'), TroubleEvent::SOURCES);
        $step = $this->whitelist($request->input('step'), $stepKeys);
        // answer: feedback は yes|no のみ許可、それ以外のイベントは短トークン（英数記号50字）
        $answer = $event === 'feedback'
            ? $this->whitelist($request->input('answer'), TroubleEvent::FEEDBACK_ANSWERS)
            : $this->sanitizeAnswer($request->input('answer'));

        TroubleEvent::create([
            'session_id' => $sessionId,
            'event' => $event,
            'symptom' => $symptom,
            'step' => $step,
            'card' => $card,
            'answer' => $answer,
            'verdict' => $verdict,
            'cta' => $cta,
            'source' => $source,
            'created_at' => now(),
        ]);

        return $noContent;
    }

    /**
     * 値が許可リストに含まれれば返し、そうでなければ null。
     */
    private function whitelist(mixed $value, array $allowed): ?string
    {
        $value = is_string($value) ? $value : null;

        return ($value !== null && in_array($value, $allowed, true)) ? $value : null;
    }

    private function sanitizeAnswer(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        // 英数・ハイフン・アンダースコアのみ、50字まで
        $clean = preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?? '';

        return $clean === '' ? null : mb_substr($clean, 0, 50);
    }
}
