<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\ModelFitment;
use Illuminate\Contracts\View\View;

/**
 * 車種×作業（型番）ページ。verified_at が入った行を持つ車種×task のみ公開。
 * 適合の「事実」は全て DB（CSV由来）から。ハードコードしない。
 */
final class FitmentPageController extends Controller
{
    public function show(BikeModel $bikeModel, string $task): View
    {
        $fitments = ModelFitment::query()
            ->where('bike_model_id', $bikeModel->id)
            ->where('task', $task)
            ->verified()
            ->orderBy('frame_code')
            ->orderBy('year_range')
            ->get();

        // 公開ゲート: slugが存在してもデータ未検証なら404
        abort_if($fitments->isEmpty(), 404);

        // 在庫件数は既存の active() スコープ（is_sold_out=false）を再利用
        $stockCount = Listing::active()->where('bike_model_id', $bikeModel->id)->count();

        $taskConfig = config("fitments.tasks.{$task}", []);

        return view('fitments.show', [
            'bikeModel' => $bikeModel->loadMissing('manufacturer'),
            'task' => $task,
            'taskConfig' => $taskConfig,
            'fitments' => $fitments,
            'stockCount' => $stockCount,
        ]);
    }
}
