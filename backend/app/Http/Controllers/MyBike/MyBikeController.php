<?php

declare(strict_types=1);

namespace App\Http\Controllers\MyBike;

use App\Http\Controllers\Controller;
use App\Http\Requests\MyBike\StoreFuelLogRequest;
use App\Http\Requests\MyBike\StoreMaintenanceLogRequest;
use App\Http\Requests\MyBike\StoreMyBikeImageRequest;
use App\Http\Requests\MyBike\StoreMyBikeRequest;
use App\Models\MyBike;
use App\Services\MyBike\MyBikeImageService;
use App\Services\MyBike\MyBikeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MyBikeController extends Controller
{
    public function __construct(
        private readonly MyBikeService $service,
        private readonly MyBikeImageService $imageService,
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
        // cross-user leak 防止：必ず所有者スコープで解決する。
        // (1) auth user の bike({id}) を所有スコープ取得（$user->myBikes()）→ 非所有者は 404。
        // (2) その bike の images() から image({image}) を取得 → 他人/別bikeの image id は 404。
        $myBike = $this->service->getBikeDetail(Auth::user(), (int) $id);
        $image = $myBike->images()->findOrFail((int) $imageId);

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
