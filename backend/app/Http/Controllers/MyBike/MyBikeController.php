<?php

declare(strict_types=1);

namespace App\Http\Controllers\MyBike;

use App\Http\Controllers\Controller;
use App\Http\Requests\MyBike\StoreFuelLogRequest;
use App\Http\Requests\MyBike\StoreMaintenanceLogRequest;
use App\Http\Requests\MyBike\StoreMyBikeRequest;
use App\Models\MyBike;
use App\Services\MyBike\MyBikeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MyBikeController extends Controller
{
    public function __construct(
        private readonly MyBikeService $service
    ) {}

    /**
     * ガレージ（愛車一覧）
     */
    public function index()
    {
        $myBikes = $this->service->getGarageData(Auth::user());

        return view('mybikes.index', compact('myBikes'));
    }

    /**
     * 愛車詳細ページ
     */
    public function show($id)
    {
        $myBike = $this->service->getBikeDetail(Auth::user(), (int) $id);

        return view('mybikes.show', compact('myBike'));
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
