<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ListingSearchService;
use App\Mail\ContactMail;
use App\Http\Requests\ContactRequest; // 追加
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * サイトの固定ページ（運営者情報、お問い合わせ、法的ページ等）の表示を管理
 */
final class PageController extends Controller
{
    /**
     * @param ListingSearchService $listingSearchService
     */
    public function __construct(
        private readonly ListingSearchService $listingSearchService
    ) {}

    /**
     * 運営者情報の表示
     */
    public function about(): View
    {
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        return view('pages.about', compact('totalListingsCount'));
    }

    /**
     * お問い合わせページの表示
     */
    public function contact(): View
    {
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        return view('pages.contact', compact('totalListingsCount'));
    }

    /**
     * お問い合わせの送信処理
     * 引数を Request から ContactRequest に変更することで自動的にバリデーションが実行されます
     */
    public function send(ContactRequest $request): RedirectResponse
    {
        // ここに来る時点でバリデーションは通過済みです
        $validated = $request->validated();

        try {
            // メール送信 (管理者宛)
            $adminEmail = config('mail.from.address'); 
            Mail::to($adminEmail)->send(new ContactMail($validated));

            return redirect()->route('pages.contact')
                ->with('success', 'お問い合わせを送信しました。内容を確認次第、担当者よりご連絡いたします。');

        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'メールの送信中にエラーが発生しました。時間を置いて再度お試しください。');
        }
    }

    /**
     * プライバシーポリシーの表示
     */
    public function privacyPolicy(): View
    {
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        return view('pages.privacy-policy', compact('totalListingsCount'));
    }

    /**
     * 利用規約・免責事項の表示
     */
    public function terms(): View
    {
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        return view('pages.terms', compact('totalListingsCount'));
    }
}