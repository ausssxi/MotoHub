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
            'fitmentModels' => $this->publishedFitmentModels(),
            'userFitmentBikes' => $this->userFitmentBikes(),
            // 出張バッテリー救援CTA（url 未設定なら blade 側で非表示）。fitment_task='battery' カードのみ表示。
            'batteryRescue' => config('diagnosis.battery_rescue', []),
            // カード直リンク着地（?card=）のみ noindex で薄ページのインデックスを防ぐ。
            // トップ /trouble・?symptom= 単独は index 維持。
            'noindex' => request()->filled('card'),
        ]);
    }

    /**
     * task別の公開車種 [{slug,name,maker_name}]（公開ゲート＝verified行あり）。6時間キャッシュ。
     *
     * @return array<string,array<int,array{slug:string,name:string,maker_name:string}>>
     */
    private function publishedFitmentModels(): array
    {
        $out = [];
        foreach (array_keys(config('fitments.tasks', [])) as $task) {
            $out[$task] = \Illuminate\Support\Facades\Cache::remember(
                "fitments:published:{$task}",
                21600,
                fn () => \App\Models\ModelFitment::query()
                    ->verified()
                    ->where('model_fitments.task', $task)
                    ->join('bike_models', 'bike_models.id', '=', 'model_fitments.bike_model_id')
                    ->join('manufacturers', 'manufacturers.id', '=', 'bike_models.manufacturer_id')
                    ->whereNotNull('bike_models.slug')->where('bike_models.slug', '!=', '')
                    ->distinct()
                    ->orderBy('manufacturers.name')->orderBy('bike_models.name')
                    ->get(['bike_models.slug as slug', 'bike_models.name as name', 'manufacturers.name as maker_name'])
                    ->map(fn ($r) => ['slug' => $r->slug, 'name' => $r->name, 'maker_name' => $r->maker_name])
                    ->all()
            );
        }

        return $out;
    }

    /**
     * 認証時のみ: マイバイクのうち公開車種に一致するもの [{display_name,slug}]（task別）。
     *
     * @return array<string,array<int,array{display_name:string,slug:string}>>
     */
    private function userFitmentBikes(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        $models = $this->publishedFitmentModels();
        $bikes = $user->myBikes()->whereNotNull('bike_model_id')->with('bikeModel:id,slug,name')->get();

        $out = [];
        foreach ($models as $task => $list) {
            $publishedSlugs = collect($list)->pluck('slug')->flip();
            $matched = [];
            foreach ($bikes as $bike) {
                $slug = $bike->bikeModel?->slug;
                if ($slug && $publishedSlugs->has($slug)) {
                    $matched[] = ['display_name' => $bike->display_name, 'slug' => $slug];
                }
            }
            $out[$task] = $matched;
        }

        return $out;
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

        // ref は入口別計測の内部識別子。値の体系は運用側。長さ上限だけ緩く制限。
        $ref = $this->sanitizeRef($request->input('ref'));

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
            'ref' => $ref,
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

    /**
     * ref（入口識別子）: 英数・ハイフン・アンダースコア・ドット・コロンのみ、50字まで。
     * PIIを含まない内部識別子想定のため緩め。
     */
    private function sanitizeRef(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        $clean = preg_replace('/[^A-Za-z0-9_.:-]/', '', $value) ?? '';

        return $clean === '' ? null : mb_substr($clean, 0, 50);
    }
}
