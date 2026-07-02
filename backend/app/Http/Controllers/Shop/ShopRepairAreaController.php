<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Shop\ShopAreaService;
use Illuminate\Contracts\View\View;

/**
 * バイク整備・修理ショップ（shop_type = repair_only）のエリアページ。
 * 「{地域} + バイク修理/整備」検索向けのプログラマティックSEO面。
 * 販売店向けの ShopAreaController と対になる構成。
 */
final class ShopRepairAreaController extends Controller
{
    public function __construct(
        private readonly ShopAreaService $areaService,
    ) {}

    /**
     * 整備ショップ・エリアインデックス（全都道府県一覧）
     */
    public function index(\Illuminate\Http\Request $request): View
    {
        // ?service=<タグ> は既知のサービスタグのみ許可（それ以外は無視）
        $service = $request->query('service');
        if (! in_array($service, \App\Models\ShopSubmission::SERVICE_TAG_OPTIONS, true)) {
            $service = null;
        }

        $data = $this->areaService->getRepairAreaIndex($service);

        return view('shops.repair-index', array_merge($data, [
            'serviceOptions' => \App\Models\ShopSubmission::SERVICE_TAG_OPTIONS,
        ]));
    }

    /**
     * 整備ショップ・都道府県ページ
     */
    public function prefecture(string $prefecture): View
    {
        $data = $this->areaService->getRepairPrefectureDetail($prefecture);

        if (! $data) {
            abort(404);
        }

        $crossLinks = [
            ['label' => $prefecture.'のバイクショップ', 'url' => route('shops.area.prefecture', $prefecture), 'icon' => 'store', 'description' => $prefecture.'の販売店一覧'],
            ['label' => 'ショップマップ', 'url' => route('shops.map'), 'icon' => 'map', 'description' => '全国のバイクショップを探す'],
            ['label' => '中古バイク検索', 'url' => route('bikes.search', ['prefecture' => mb_substr($prefecture, 0, -1)]), 'icon' => 'search', 'description' => $prefecture.'の在庫を検索'],
        ];

        return view('shops.repair-prefecture', array_merge($data, ['crossLinks' => $crossLinks]));
    }

    /**
     * 整備ショップ・市区町村ページ（主要SEOターゲット）
     */
    public function city(string $prefecture, string $city): View|\Illuminate\Http\RedirectResponse
    {
        // F: city 正規化（183件のスペース除去）に伴い、旧スペース入りURLは正規URLへ301。
        // 無限ループ防止のためスペースが実在する場合のみリダイレクト。
        $normalized = preg_replace('/[\s\x{3000}]+/u', '', $city) ?? $city;
        if ($normalized !== $city) {
            return redirect()->route('shops.repair.city', [$prefecture, $normalized], 301);
        }

        $data = $this->areaService->getRepairCityDetail($prefecture, $city);

        if (! $data) {
            abort(404);
        }

        $crossLinks = [
            ['label' => $city.'のバイクショップ', 'url' => route('shops.area.city', [$prefecture, $city]), 'icon' => 'store', 'description' => $city.'の販売店一覧'],
            ['label' => $prefecture.'の整備店', 'url' => route('shops.repair.prefecture', $prefecture), 'icon' => 'wrench', 'description' => $prefecture.'の整備・修理店一覧'],
            ['label' => 'ショップマップ', 'url' => route('shops.map'), 'icon' => 'map', 'description' => '全国のバイクショップを探す'],
        ];

        return view('shops.repair-city', array_merge($data, ['crossLinks' => $crossLinks]));
    }
}
