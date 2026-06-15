<?php

declare(strict_types=1);

namespace App\Http\Controllers\MyBike;

use App\Http\Controllers\Controller;
use App\Http\Requests\MyBike\StoreFuelLogRequest;
use App\Http\Requests\MyBike\StoreMaintenanceLogRequest;
use App\Http\Requests\MyBike\StoreMyBikeImageRequest;
use App\Http\Requests\MyBike\StoreMyBikeRequest;
use App\Models\MyBike;
use App\Services\MyBike\FuelOcrService;
use App\Services\MyBike\MyBikeImageService;
use App\Services\MyBike\MyBikeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MyBikeController extends Controller
{
    public function __construct(
        private readonly MyBikeService $service,
        private readonly MyBikeImageService $imageService,
        private readonly FuelOcrService $ocrService,
    ) {}

    /**
     * ガレージ（愛車一覧）
     */
    public function index(Request $request)
    {
        $myBikes = $this->service->getGarageData(Auth::user());

        // レビュー投稿後の導線などからの prefill（?bike_model_id=）。該当モデルを選択済みで登録モーダルを開く。
        $prefillModel = null;
        if ($request->filled('bike_model_id')) {
            $model = \App\Models\BikeModel::with('manufacturer')->find($request->integer('bike_model_id'));
            if ($model) {
                $prefillModel = [
                    'id' => $model->id,
                    'name' => trim(($model->manufacturer?->name ? $model->manufacturer->name.' ' : '').$model->name),
                ];
            }
        }

        return view('mybikes.index', compact('myBikes', 'prefillModel'));
    }

    /**
     * 愛車詳細ページ
     */
    public function show($id)
    {
        $myBike = $this->service->getBikeDetail(Auth::user(), (int) $id);
        $dashboard = $this->service->buildDashboard($myBike);

        return view('mybikes.show', compact('myBike', 'dashboard'));
    }

    /**
     * 整備＋給油ログのCSV出力（本人のみ＝所有者チェック・private）。記録の持ち出し。
     */
    public function exportCsv($id)
    {
        $myBike = $this->service->getBikeDetail(Auth::user(), (int) $id); // 非所有者は 404

        $filename = 'garage_'.$myBike->id.'_logs.csv';

        return response()->streamDownload(function () use ($myBike) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM（Excel文字化け防止）
            fputcsv($out, ['種別', '日付', '走行距離(km)', '内容', '費用(円)', '給油量(L)', '燃費(km/L)']);

            foreach ($myBike->maintenanceLogs as $m) {
                fputcsv($out, ['整備', optional($m->maintained_at)->format('Y-m-d'), $m->odometer, trim($m->title.' '.($m->note ?? '')), $m->cost, '', '']);
            }
            foreach ($myBike->fuelLogs as $f) {
                fputcsv($out, ['給油', optional($f->filled_at)->format('Y-m-d'), $f->odometer, $f->memo, $f->cost, $f->quantity, $f->efficiency]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * 愛車登録処理
     */
    public function store(StoreMyBikeRequest $request)
    {
        $this->service->registerBike(Auth::user(), $request->validated());

        return redirect()->route('mybikes.index')->with('success', '愛車をガレージに登録しました！');
    }

    /**
     * 愛車削除処理
     */
    public function destroy($id)
    {
        $myBike = $this->service->getBikeDetail(Auth::user(), (int) $id);
        $this->service->deleteBike($myBike);

        return redirect()->route('mybikes.index')->with('success', '愛車を削除しました。');
    }

    /**
     * 公開/非公開トグル（台ごと・consent + identity）。
     * 公開にする時はハンドル(review_display_name)必須。本名(User->name)は使わない。
     */
    public function updateVisibility(Request $request, $id)
    {
        $user = Auth::user();
        // 所有者チェック（他人の愛車は 404）
        $myBike = $this->service->getBikeDetail($user, (int) $id);

        if ($request->boolean('is_public')) {
            // 初回公開: 公開ハンドル未設定なら設定を必須に（以降固定・タグ除去）
            if (empty($user->review_display_name)) {
                $handle = trim(strip_tags((string) $request->input('review_handle')));
                if ($handle === '' || mb_strlen($handle) > 30) {
                    return back()->withErrors(['review_handle' => '公開表示名を1〜30文字で入力してください。'])->withInput();
                }
                $user->review_display_name = $handle;
                $user->save();
            }
            $myBike->is_public = true;
            $myBike->save();

            return back()->with('success', 'このガレージを公開しました。表示名「'.$user->review_display_name.'」で公開されます（本名は表示されません）。');
        }

        $myBike->is_public = false;
        $myBike->save();

        return back()->with('success', 'このガレージを非公開（自分のみ）に戻しました。');
    }

    /**
     * 給油フォームの OCR 入力補完（owner-only・throttle）。
     * レシート/メーター画像から {走行距離,給油量,金額,日付} を抽出して JSON で返す。
     * ★抽出値は保存しない。フロントで給油フォームに充填し、ユーザーが確認・修正してから保存する。
     */
    public function extractFuelOcr(Request $request, $id)
    {
        if (! config('garage.ocr_enabled')) {
            return response()->json(['error' => 'この機能は現在利用できません。'], 404);
        }

        // 所有者チェック（非所有者は 404）。OCR自体はbike非依存だが auth+owner ゲートに使う。
        $myBike = $this->service->getBikeDetail(Auth::user(), (int) $id);

        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.(int) config('garage.max_upload_kb')],
            'type' => ['required', 'in:'.FuelOcrService::TYPE_RECEIPT.','.FuelOcrService::TYPE_ODOMETER],
        ]);

        try {
            $result = $this->ocrService->extract($request->file('image'), $validated['type']);
        } catch (\Throwable $e) {
            Log::error('FuelOcr 抽出失敗', ['error' => $e->getMessage()]);

            return response()->json(['error' => '画像の解析に失敗しました。手入力で記録できます。'], 502);
        }

        return response()->json($this->withOdometerGuard($myBike, $result));
    }

    /**
     * 給油フォームの音声入力補完（owner-only・throttle）。engine A: Web Speech→Haiku。
     * フロントの音声認識で得たテキストを Haiku でパースし {走行距離,給油量,金額} を JSON で返す。
     * ★抽出値は保存しない。フロントで給油フォームに充填し、ユーザーが確認・修正してから保存する。
     */
    public function parseFuelVoice(Request $request, $id)
    {
        if (! config('garage.voice_enabled')) {
            return response()->json(['error' => 'この機能は現在利用できません。'], 404);
        }

        // 所有者チェック（非所有者は 404）
        $myBike = $this->service->getBikeDetail(Auth::user(), (int) $id);

        $validated = $request->validate([
            'transcript' => ['required', 'string', 'max:500'],
        ]);

        try {
            $result = $this->ocrService->parseText($validated['transcript']);
        } catch (\Throwable $e) {
            Log::error('FuelVoice パース失敗', ['error' => $e->getMessage()]);

            return response()->json(['error' => '音声の解析に失敗しました。手入力で記録できます。'], 502);
        }

        return response()->json($this->withOdometerGuard($myBike, $result));
    }

    /**
     * 抽出結果に走行距離の前回比ガード情報を付与（既存キーは不変）。
     * last_odometer(int|null) と odometer_warning(string|null) を追加する。
     *
     * @param  array{values: array<string, mixed>, confidence: string}  $result
     * @return array<string, mixed>
     */
    private function withOdometerGuard(MyBike $myBike, array $result): array
    {
        $newOdometer = $result['values']['odometer'] ?? null;
        $result['last_odometer'] = $myBike->current_odometer > 0 ? (int) $myBike->current_odometer : null;
        $result['odometer_warning'] = $myBike->odometerPlausibilityWarning($newOdometer);

        return $result;
    }

    /**
     * 給油記録の保存
     */
    public function storeFuel(StoreFuelLogRequest $request, MyBike $myBike)
    {
        $this->service->recordFuel($myBike, $request->validated());

        return back()->with('success', '給油記録を保存しました！');
    }

    /**
     * 整備記録の保存
     */
    public function storeMaintenance(StoreMaintenanceLogRequest $request, MyBike $myBike)
    {
        $this->service->recordMaintenance($myBike, $request->validated());

        return back()->with('success', '整備記録を保存しました！');
    }

    /**
     * ギャラリー画像のアップロード（owner-only・private）。最適化して非公開ディスクへ保存。
     */
    public function storeImage(StoreMyBikeImageRequest $request, $id)
    {
        $myBike = $this->service->getBikeDetail(Auth::user(), (int) $id); // 非所有者は 404

        if ($this->imageService->atLimit($myBike)) {
            return back()->withErrors([
                'image' => '画像は1台あたり最大'.(int) config('garage.max_images').'枚までです。',
            ]);
        }

        $this->imageService->add($myBike, $request->file('image'), $request->input('caption'));

        return back()->with('success', '写真を追加しました。');
    }

    /**
     * ギャラリー画像のキャプション更新（owner-only）。
     */
    public function updateImageCaption(Request $request, $id, $imageId)
    {
        $myBike = $this->service->getBikeDetail(Auth::user(), (int) $id); // 非所有者は 404
        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:100'],
        ]);

        $this->imageService->updateCaption($myBike, (int) $imageId, $validated['caption'] ?? null);

        return back()->with('success', 'キャプションを更新しました。');
    }

    /**
     * ギャラリー画像の削除（owner-only・行＋実ファイル）。
     */
    public function destroyImage($id, $imageId)
    {
        $myBike = $this->service->getBikeDetail(Auth::user(), (int) $id); // 非所有者は 404
        $this->imageService->delete($myBike, (int) $imageId);

        return back()->with('success', '写真を削除しました。');
    }

    /**
     * ギャラリー画像の配信（owner-only）。非公開ディスクからストリーム配信。
     * URL直叩きでも非所有者は 404（getBikeDetail）／他愛車の画像IDも 404。
     */
    public function showImage($id, $imageId)
    {
        // bike と image を取得（image の my_bike_id 整合も担保＝別bikeのimage idは404）。
        $myBike = MyBike::findOrFail((int) $id);
        $image = $myBike->images()->findOrFail((int) $imageId);

        $user = Auth::user();
        $isOwner = $user !== null && (int) $myBike->user_id === (int) $user->id;

        // 許可ルール（leak防止の肝）:
        //  - 所有者 → 全画像200。
        //  - 非所有者 → 「is_public ガレージのカバー(=1枚目)」のみ200。それ以外は404。
        //    （非公開ガレージの画像、is_publicでも非カバー写真は404。存在も漏らさない）
        if (! $isOwner) {
            $cover = $myBike->images()->first(); // sort_order asc, id asc 先頭＝カバー
            abort_unless($myBike->is_public && $cover !== null && (int) $cover->id === (int) $image->id, 404);
        }

        $disk = Storage::disk(config('garage.image_disk'));
        abort_unless($disk->exists($image->path), 404);

        return $disk->response(
            $image->path,
            null,
            ['Cache-Control' => 'private, max-age=86400'],
        );
    }

    /**
     * 車種検索API
     */
    public function searchModels(Request $request)
    {
        $keyword = $request->query('q');
        if (! $keyword || mb_strlen($keyword) < 1) {
            return response()->json([]);
        }

        $models = $this->service->searchModels($keyword);

        return response()->json($models);
    }
}
