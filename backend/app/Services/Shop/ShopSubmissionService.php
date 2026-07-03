<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\Shop;
use App\Models\ShopAcceptanceReport;
use App\Models\ShopSubmission;
use App\Services\GsiGeocodingService;
use Illuminate\Support\Collection;

/**
 * 店舗投稿の承認・統合・却下と重複候補判定。Filament から呼ぶが、
 * ロジックはここに集約してテスト可能にする（UIから独立）。
 */
final class ShopSubmissionService
{
    public function __construct(
        private readonly GsiGeocodingService $geocoder,
        private readonly ShopAreaService $areaService,
        private readonly ShopSubmissionImageService $imageService,
    ) {}

    /**
     * 承認: 新規 shop(source='user', repair_only) を作成し、投稿を紐付ける。
     */
    public function approveAsNew(ShopSubmission $submission): Shop
    {
        $shop = Shop::create([
            'name' => $submission->shop_name,
            'prefecture' => $submission->prefecture,
            'city' => $submission->city,
            'address' => $submission->address ?? '', // shops.address は NOT NULL
            'phone' => $submission->phone,
            // 公式サイトURLは official_site_url に統一（scraper用 website_url には入れない）
            'official_site_url' => $submission->website_url,
            'service_tags' => $submission->service_tags ?: null,
            // 投稿の店種を反映（null は unknown）。unknown店は shops:classify 対象外(source guard)なので
            // /shops/area には出るが /shops/repair には出ない。
            'shop_type' => $submission->shop_type ?: 'unknown',
            'source' => Shop::SOURCE_USER,
        ]);

        // ジオコーディングは住所がある場合のみ（市区町村中心への誤ピンを避ける）
        if (! empty($submission->address)) {
            $geo = $this->geocoder->geocode($submission->prefecture, $submission->city, $submission->address);
            if ($geo !== null) {
                $shop->latitude = $geo['lat'];
                $shop->longitude = $geo['lng'];
                $shop->save();
            }
        }

        // 投稿画像があれば公開ディスクへ昇格し、shops.local_image_path へ（既存の画像規約に合流）
        if ($submission->image_path) {
            $publicPath = $this->imageService->promoteToPublic($submission->image_path);
            if ($publicPath !== null) {
                $shop->local_image_path = $publicPath;
                $shop->save();
            }
        }

        $this->createAcceptanceReport($shop->id, $submission);

        $submission->update([
            'status' => ShopSubmission::STATUS_APPROVED,
            'linked_shop_id' => $shop->id,
            'processed_at' => now(),
        ]);

        $this->areaService->forgetAreaCaches($submission->prefecture, $submission->city);

        return $shop;
    }

    /**
     * 統合: 既存 shop に投稿内容を受け入れ情報として付与。
     * 既存カラムは原則不変。唯一の例外として、公式サイトURLが空のときだけ充填する
     * （既存値がある場合は保持＝上書きしない）。フラグ・コメントが無くURLのみの投稿でも
     * URL充填だけで統合が成立する。
     */
    public function mergeInto(ShopSubmission $submission, Shop $shop): void
    {
        $this->createAcceptanceReport($shop->id, $submission);

        // 例外: official_site_url が空のときだけ充填（scraper再分類の対象外カラム）
        if (! empty($submission->website_url) && empty($shop->official_site_url)) {
            $shop->official_site_url = $submission->website_url;
            $shop->save();
        }

        // 画像も fill-if-empty: 既存店に画像が無いときだけ充填。あれば投稿画像は破棄（孤児防止）。
        if ($submission->image_path) {
            $noImage = empty($shop->local_image_path) && empty($shop->image_url);
            if ($noImage && ($publicPath = $this->imageService->promoteToPublic($submission->image_path)) !== null) {
                $shop->local_image_path = $publicPath;
                $shop->save();
            } else {
                $this->imageService->deletePending($submission->image_path);
            }
        }

        $submission->update([
            'status' => ShopSubmission::STATUS_MERGED,
            'linked_shop_id' => $shop->id,
            'processed_at' => now(),
        ]);

        $this->areaService->forgetAreaCaches($submission->prefecture, $submission->city);
    }

    /**
     * 公式URL提案（target_shop_id 付き）の承認: 対象店の official_site_url が空なら充填。
     * 既存値は保持し、新規店は作らない。
     */
    public function approveUrlSuggestion(ShopSubmission $submission): void
    {
        $shop = $submission->targetShop;

        if ($shop && ! empty($submission->website_url) && empty($shop->official_site_url)) {
            $shop->official_site_url = $submission->website_url;
            $shop->save();
        }

        $submission->update([
            'status' => ShopSubmission::STATUS_MERGED,
            'linked_shop_id' => $shop?->id,
            'processed_at' => now(),
        ]);
    }

    public function reject(ShopSubmission $submission): void
    {
        // 却下したら pending 画像は削除（孤児防止）
        $this->imageService->deletePending($submission->image_path);

        $submission->update([
            'status' => ShopSubmission::STATUS_REJECTED,
            'processed_at' => now(),
        ]);
    }

    /**
     * 重複候補: 同一 prefecture+city の shops から、正規化店名の一致/包含、
     * または電話番号（数字のみ）一致のものを返す。各Shopに transient な
     * matched_by（'name'|'phone'）を付与する。投稿フォームの送信前チェックと
     * Filament承認画面の両方で共用する。
     *
     * @return Collection<int, Shop>
     */
    public function findDuplicates(string $shopName, string $prefecture, string $city, ?string $phone = null): Collection
    {
        $normSub = $this->normalizeName($shopName);
        $phoneSub = $phone ? preg_replace('/\D/', '', $phone) : null;

        return Shop::where('prefecture', $prefecture)
            ->where('city', $city)
            ->get()
            ->map(function (Shop $shop) use ($normSub, $phoneSub) {
                $normShop = $this->normalizeName($shop->name);

                $nameMatch = $normSub !== '' && (
                    $normShop === $normSub
                    || (mb_strlen($normSub) >= 2 && str_contains($normShop, $normSub))
                    || (mb_strlen($normShop) >= 2 && str_contains($normSub, $normShop))
                );

                $phoneMatch = $phoneSub && $shop->phone
                    && preg_replace('/\D/', '', $shop->phone) === $phoneSub;

                if (! $nameMatch && ! $phoneMatch) {
                    return null;
                }

                $shop->matched_by = $nameMatch ? 'name' : 'phone';

                return $shop;
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, Shop>
     */
    public function duplicateCandidates(ShopSubmission $submission): Collection
    {
        return $this->findDuplicates(
            $submission->shop_name,
            $submission->prefecture,
            $submission->city,
            $submission->phone,
        );
    }

    /**
     * 店名正規化: 全半角統一・スペース除去・小文字化・会社形態表記除去。
     */
    public function normalizeName(string $name): string
    {
        // 全角英数→半角(a)、全角スペース→半角(s)、半角カナ→全角(K)
        $n = mb_convert_kana($name, 'asK');
        $n = str_replace(['株式会社', '（株）', '(株)', '有限会社', '（有）', '(有)'], '', $n);
        $n = preg_replace('/\s+/u', '', $n) ?? '';

        return mb_strtolower($n);
    }

    /**
     * acceptance_flags / comment があれば、承認済みの受け入れ情報を作成。
     */
    private function createAcceptanceReport(int $shopId, ShopSubmission $submission): void
    {
        $flags = (array) ($submission->acceptance_flags ?? []);
        $hasComment = ! empty($submission->comment);

        if (count($flags) === 0 && ! $hasComment) {
            return;
        }

        ShopAcceptanceReport::create([
            'shop_id' => $shopId,
            'accepts_other_store' => in_array('accepts_other_store', $flags, true),
            'accepts_bring_in' => in_array('accepts_bring_in', $flags, true),
            'pickup_service' => in_array('pickup_service', $flags, true),
            'walk_in_ok' => in_array('walk_in_ok', $flags, true),
            'comment' => $submission->comment,
            'submitter_name' => $submission->submitter_name,
            'user_id' => $submission->user_id,
            'is_approved' => true, // Observer が approved_at 記録＋受け入れ集計キャッシュ失効
        ]);
    }
}
