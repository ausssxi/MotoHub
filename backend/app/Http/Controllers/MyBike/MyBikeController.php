<?php

declare(strict_types=1);

namespace App\Http\Controllers\MyBike;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Services\MyBike\MyBikeService;
use App\Http\Requests\MyBike\StoreMyBikeRequest;
use App\Http\Requests\MyBike\StoreFuelLogRequest;
use App\Http\Requests\MyBike\StoreMaintenanceLogRequest;

/**
 * 愛車管理コントローラー
 * ロジックをServiceとRequestに分離して軽量化
 */
class MyBikeController extends Controller
{
    public function __construct(
        private readonly MyBikeService $service
    ) {}

    /**
     * 愛車一覧ページ
     */
    public function index()
    {
        $myBikes = $this->service->getUserBikes(Auth::id());
        return view('mybikes.index', compact('myBikes'));
    }

    /**
     * 愛車登録処理
     */
    public function store(StoreMyBikeRequest $request)
    {
        $this->service->registerBike(Auth::id(), $request->validated());
        return redirect()->route('mybikes.index')->with('success', '愛車を登録しました！');
    }

    /**
     * 愛車詳細（ログ一覧）ページ
     */
    public function show(int $id)
    {
        // Service内で権限チェック・データ整形済み
        $data = $this->service->getBikeDetail($id, Auth::id());
        return view('mybikes.show', $data);
    }

    /**
     * 給油記録の保存
     */
    public function storeFuel(StoreFuelLogRequest $request, int $id)
    {
        $this->service->recordFuel($id, Auth::id(), $request->validated());
        return back()->with('success', '給油記録を保存しました！');
    }

    /**
     * 整備記録の保存
     */
    public function storeMaintenance(StoreMaintenanceLogRequest $request, int $id)
    {
        $this->service->recordMaintenance($id, Auth::id(), $request->validated());
        return back()->with('success', '整備記録を保存しました！');
    }
}