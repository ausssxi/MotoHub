<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\StoreShopSubmissionRequest;
use App\Http\Requests\Shop\StoreShopUrlSubmissionRequest;
use App\Mail\ShopSubmissionSubmitted;
use App\Models\Shop;
use App\Models\ShopAcceptanceReport;
use App\Models\ShopSubmission;
use App\Services\Shop\ShopSubmissionImageService;
use App\Services\Shop\ShopSubmissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * 未掲載の整備・修理店をユーザーが投稿する公開フォーム（承認制）。
 */
final class ShopSubmissionController extends Controller
{
    public function create(Request $request): View
    {
        $prefectures = collect(config('parking.regions', []))
            ->flatMap(fn ($prefs) => array_keys($prefs))
            ->values();

        // ?type= プリセレクト（既知の店種のみ）
        $prefillType = $request->query('type');
        if (! array_key_exists($prefillType, ShopSubmission::SHOP_TYPE_OPTIONS)) {
            $prefillType = '';
        }

        return view('shops.submit', [
            'prefectures' => $prefectures,
            'serviceTagOptions' => ShopSubmission::SERVICE_TAG_OPTIONS,
            'acceptanceFlags' => ShopAcceptanceReport::FLAGS,
            'shopTypeOptions' => ShopSubmission::SHOP_TYPE_OPTIONS,
            // エリア/修理/検索ページからの遷移でプリフィル
            'prefillName' => (string) $request->query('name', ''),
            'prefillPref' => (string) $request->query('pref', ''),
            'prefillCity' => (string) $request->query('city', ''),
            'prefillType' => (string) $prefillType,
        ]);
    }

    /**
     * 都道府県内の既存 city 一覧（datalistサジェスト用・キャッシュ）。
     */
    public function cities(Request $request): JsonResponse
    {
        $pref = trim((string) $request->query('pref', ''));
        if ($pref === '') {
            return response()->json(['cities' => []]);
        }

        $cities = \Illuminate\Support\Facades\Cache::remember('shop_cities_'.md5($pref), 86400, fn () => Shop::where('prefecture', $pref)
            ->whereNotNull('city')->where('city', '!=', '')
            ->distinct()->orderBy('city')->pluck('city')->all());

        return response()->json(['cities' => $cities]);
    }

    /**
     * 送信前の重複チェック（ソフト警告用）。既存店候補を最大5件返す。
     */
    public function check(Request $request, ShopSubmissionService $service): JsonResponse
    {
        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:100'],
            'prefecture' => ['required', 'string', 'max:10'],
            'city' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $candidates = $service->findDuplicates(
            $validated['shop_name'],
            $validated['prefecture'],
            $validated['city'],
            $validated['phone'] ?? null,
        )->take(5)->map(fn ($shop) => [
            'id' => $shop->id,
            'name' => $shop->name,
            'address' => $shop->address,
            'matched_by' => $shop->matched_by ?? 'name',
            'detail_url' => route('shops.show', $shop->id),
        ])->values();

        return response()->json(['candidates' => $candidates]);
    }

    /**
     * 店詳細ページからの公式サイトURL提案（既存店対象・承認制）。
     * shop_name/prefecture/city は対象店からサーバ側で埋める（改ざん防止）。
     */
    public function suggestUrl(Shop $shop, StoreShopUrlSubmissionRequest $request): RedirectResponse
    {
        // 既に公式URLがある店は受け付けない（多重防御）
        if (! empty($shop->official_site_url)) {
            return redirect()->route('shops.show', $shop);
        }

        $user = $request->user();

        $submission = ShopSubmission::create([
            'shop_name' => $shop->name,                  // 対象店から（フォーム値は使わない）
            'prefecture' => $shop->prefecture ?? '',
            'city' => $shop->city ?? '',
            'website_url' => $request->input('website_url'),
            'submitter_name' => $user?->review_display_name,
            'user_id' => $user?->id,
            'ip_hash' => hash('sha256', $request->ip().'|'.config('app.key')),
            'status' => ShopSubmission::STATUS_PENDING,
            'target_shop_id' => $shop->id,
        ]);

        try {
            Mail::to(config('app.contact_admin_email'))->send(new ShopSubmissionSubmitted($submission));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('shops.show', $shop)->with('suggest_url_success', '1');
    }

    public function store(StoreShopSubmissionRequest $request, ShopSubmissionImageService $imageService): RedirectResponse
    {
        // 画像は非公開ディスクへ再エンコード保存（EXIF除去）。読込不可はエラーで差し戻し。
        $imagePath = null;
        if ($request->hasFile('image')) {
            try {
                $imagePath = $imageService->storePending($request->file('image'));
            } catch (\RuntimeException $e) {
                return back()->withErrors(['image' => '画像を読み込めませんでした（対応形式: JPEG / PNG / WebP）。'])->withInput();
            }
        }

        // === 投稿者名の解決（Reviewパターン・公開ハンドルのみ・User->name は使わない）===
        $user = $request->user();
        $userId = $user?->id;

        if ($user) {
            $handle = $user->review_display_name;
            if (empty($handle)) {
                $handle = trim(strip_tags((string) $request->input('submitter_name')));
                if ($handle === '' || mb_strlen($handle) > 30) {
                    return back()
                        ->withErrors(['submitter_name' => '公開表示名を1〜30文字で入力してください。'])
                        ->withInput();
                }
                $user->review_display_name = $handle;
                $user->save();
            }
            $submitterName = $handle;
        } else {
            $submitterName = trim(strip_tags((string) $request->input('submitter_name'))) ?: '名無しライダー';
        }

        $submission = ShopSubmission::create([
            'shop_name' => $request->input('shop_name'),
            'prefecture' => $request->input('prefecture'),
            'city' => $request->input('city'),
            'shop_type' => $request->input('shop_type') ?: null, // null は承認時 unknown 扱い
            'address' => trim((string) $request->input('address')) ?: null,
            'phone' => trim((string) $request->input('phone')) ?: null,
            'website_url' => trim((string) $request->input('website_url')) ?: null,
            'image_path' => $imagePath,
            'service_tags' => array_values((array) $request->input('service_tags', [])) ?: null,
            'acceptance_flags' => array_values((array) $request->input('acceptance_flags', [])) ?: null,
            'comment' => trim((string) $request->input('comment')) ?: null,
            'submitter_name' => $submitterName,
            'user_id' => $userId,
            'ip_hash' => hash('sha256', $request->ip().'|'.config('app.key')),
            'status' => ShopSubmission::STATUS_PENDING,
        ]);

        // 管理者へ承認待ち通知（失敗しても投稿は成功扱い）
        try {
            Mail::to(config('app.contact_admin_email'))->send(new ShopSubmissionSubmitted($submission));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('shops.submit.create')
            ->with('submission_success', '1');
    }
}
