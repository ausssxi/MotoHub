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
 * サイトの固定ページを管理するコントローラー
 * 
 * 運営者情報、お問い合わせ、プライバシーポリシー、利用規約などの
 * 固定ページの表示とお問い合わせフォームの送信処理を担当します。
 */
final class PageController extends Controller
{
    /**
     * @param ListingSearchService $listingSearchService 出品情報の検索を担当するサービス
     */
    public function __construct(
        private readonly ListingSearchService $listingSearchService
    ) {}

    /**
     * 運営者情報の表示
     * 
     * サイト全体の有効掲載台数を取得して運営者情報ページを表示します。
     * 
     * @return View 運営者情報ページのビュー
     */
    public function about(): View
    {
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        return view('pages.about', compact('totalListingsCount'));
    }

    /**
     * お問い合わせページの表示
     * 
     * サイト全体の有効掲載台数を取得してお問い合わせページを表示します。
     * 
     * @return View お問い合わせページのビュー
     */
    public function contact(): View
    {
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        return view('pages.contact', compact('totalListingsCount'));
    }

    /**
     * お問い合わせの送信処理
     * 
     * ContactRequestによるバリデーションを通過したお問い合わせ内容を
     * 管理者宛にメール送信します。送信成功時は成功メッセージと共に
     * お問い合わせページにリダイレクトし、失敗時はエラーメッセージと
     * 入力内容を保持した状態で前のページに戻ります。
     * 
     * @param ContactRequest $request バリデーション済みのお問い合わせフォームのリクエスト
     * @return RedirectResponse お問い合わせページへのリダイレクトレスポンス（成功/エラーメッセージ付き）
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
     * 
     * サイト全体の有効掲載台数を取得してプライバシーポリシーページを表示します。
     * 
     * @return View プライバシーポリシーページのビュー
     */
    public function privacyPolicy(): View
    {
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        return view('pages.privacy-policy', compact('totalListingsCount'));
    }

    /**
     * 利用規約・免責事項の表示
     * 
     * サイト全体の有効掲載台数を取得して利用規約・免責事項ページを表示します。
     * 
     * @return View 利用規約・免責事項ページのビュー
     */
    public function terms(): View
    {
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        return view('pages.terms', compact('totalListingsCount'));
    }
}