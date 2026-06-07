<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleLoginController extends Controller
{
    /**
     * Googleの認証画面へリダイレクト
     */
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Googleからのコールバック処理
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->route('login')
                ->with('status', 'Googleログインに失敗しました。もう一度お試しください。');
        }

        // 1. google_id で既存ユーザーを検索
        $user = User::where('google_id', $googleUser->getId())->first();

        if ($user) {
            // 既にGoogleで登録済み → そのままログイン
            Auth::login($user, remember: true);
            return redirect()->intended('/');
        }

        // 2. 同じメールアドレスのユーザーが通常登録で存在する場合 → Google連携
        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            $user->update([
                'google_id' => $googleUser->getId(),
                'avatar'    => $googleUser->getAvatar(),
            ]);
            Auth::login($user, remember: true);
            return redirect()->intended('/');
        }

        // 3. 完全に新規ユーザー → 新規作成
        $user = User::create([
            'name'      => $googleUser->getName(),
            'email'     => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'avatar'    => $googleUser->getAvatar(),
            'email_verified_at' => now(),  // Googleで認証済みなのでメール確認不要
        ]);

        // GA4 sign_up 計測用（新規作成時のみ・既存ユーザーのログイン/連携では撃たない）
        session()->flash('signup_method', 'google');

        Auth::login($user, remember: true);
        return redirect()->intended('/');
    }
}