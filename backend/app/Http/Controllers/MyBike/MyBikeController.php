<?php

declare(strict_types=1);

namespace App\Http\Controllers\MyBike;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\MyBike;
use App\Http\Requests\MyBike\StoreMyBikeRequest;
use App\Http\Requests\MyBike\StoreFuelLogRequest;
use App\Http\Requests\MyBike\StoreMaintenanceLogRequest;
use App\Services\MyBike\MyBikeService;

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
        $myBike = $this->service->getBikeDetail(Auth::user(), (int)$id);
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
        $myBike = $this->service->getBikeDetail(Auth::user(), (int)$id);
        $this->service->deleteBike($myBike);
        
        return redirect()->route('mybikes.index')->with('success', '愛車を削除しました。');
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
        if (!$keyword || mb_strlen($keyword) < 1) return response()->json([]);

        $models = $this->service->searchModels($keyword);
        return response()->json($models);
    }
}