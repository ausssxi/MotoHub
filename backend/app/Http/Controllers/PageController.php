<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * サイトの固定ページ（運営者情報、お問い合わせ、法的ページ等）の表示を管理
 */
final class PageController extends Controller
{
    /**
     * 運営者情報の表示
     */
    public function about(): View
    {
        return view('pages.about');
    }

    /**
     * お問い合わせページの表示
     */
    public function contact(): View
    {
        return view('pages.contact');
    }

    /**
     * プライバシーポリシーの表示 (将来用)
     */
    public function privacyPolicy(): View
    {
        return view('pages.privacy-policy');
    }

    /**
     * 利用規約・免責事項の表示 (将来用)
     */
    public function terms(): View
    {
        return view('pages.terms');
    }
}