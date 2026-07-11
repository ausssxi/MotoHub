<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bike\StoreModelAnswerRequest;
use App\Http\Requests\Bike\StoreModelQuestionRequest;
use App\Models\BikeModel;
use App\Models\ModelAnswer;
use App\Models\ModelQuestion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 車種Q&A（UGC 5面目）。ログイン不要・即反映・安全弁は既存の型を流用。
 */
final class ModelQaController extends Controller
{
    /** 車種の質問一覧（車種ページの「すべて見る」導線先・全公開質問を新着順）。 */
    public function listQuestions(string $mfrSlug, string $modelSlug): View
    {
        $model = BikeModel::with('manufacturer')
            ->where('slug', $modelSlug)
            ->whereHas('manufacturer', fn ($q) => $q->where('slug', $mfrSlug))
            ->firstOrFail();

        $questions = ModelQuestion::approved()
            ->where('bike_model_id', $model->id)
            ->withCount('approvedAnswers')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('bikes.questions_index', [
            'model' => $model,
            'questions' => $questions,
        ]);
    }

    /** 質問詳細ページ（ロングテールSEOの受け皿・固有URL）。 */
    public function showQuestion(string $mfrSlug, string $modelSlug, int $id): View
    {
        $question = ModelQuestion::approved()
            ->with(['bikeModel.manufacturer', 'user'])
            ->findOrFail($id);

        $answers = $question->answers()->approved()->with('user')->orderByDesc('created_at')->get();

        return view('bikes.question', [
            'question' => $question,
            'model' => $question->bikeModel,
            'answers' => $answers,
        ]);
    }

    /** 質問投稿（車種ページから）。 */
    public function storeQuestion(StoreModelQuestionRequest $request, int $modelId): RedirectResponse
    {
        $model = BikeModel::findOrFail($modelId);

        ModelQuestion::create([
            'bike_model_id' => $model->id,
            'user_id' => $request->user()?->id,
            'nickname' => $this->guestNickname($request),
            'title' => $request->input('title'),
            'body' => $request->input('body') ?: null,
            'is_approved' => true, // 即反映・通報でキルスイッチ
            'submitter_ip_hash' => hash('sha256', $request->ip().'|'.config('app.key')),
        ]);

        return redirect($model->seo_url.'#questions')->with('qa_success', 'question');
    }

    /** 回答投稿（質問詳細ページから）。 */
    public function storeAnswer(StoreModelAnswerRequest $request, int $questionId): RedirectResponse
    {
        $question = ModelQuestion::approved()->with('bikeModel.manufacturer')->findOrFail($questionId);

        ModelAnswer::create([
            'model_question_id' => $question->id,
            'user_id' => $request->user()?->id,
            'nickname' => $this->guestNickname($request),
            'body' => $request->input('body'),
            'is_approved' => true,
            'submitter_ip_hash' => hash('sha256', $request->ip().'|'.config('app.key')),
        ]);

        return redirect($this->questionUrl($question).'#answers')->with('qa_success', 'answer');
    }

    /** 「参考になった」（回答へのワンタップ・軽い重複防止はクライアント localStorage＋throttle）。 */
    public function markHelpful(int $answerId): JsonResponse
    {
        $answer = ModelAnswer::approved()->findOrFail($answerId);
        $answer->increment('helpful_count');

        return response()->json(['helpful_count' => $answer->fresh()->helpful_count]);
    }

    /** ゲスト表示名（ログイン時は null＝display_name がハンドルを使う。本名は使わない）。 */
    private function guestNickname(Request $request): ?string
    {
        if ($request->user()) {
            return null;
        }

        return trim(strip_tags((string) $request->input('nickname'))) ?: '名無しライダー';
    }

    private function questionUrl(ModelQuestion $question): string
    {
        $model = $question->bikeModel;

        return route('bikes.model_question', [
            'mfrSlug' => $model->manufacturer->slug ?? $model->manufacturer_id,
            'modelSlug' => $model->slug ?? $model->id,
            'id' => $question->id,
        ]);
    }
}
