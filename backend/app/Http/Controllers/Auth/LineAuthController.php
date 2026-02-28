<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class LineAuthController extends Controller
{
    /**
     * LINEの認証画面へリダイレクト
     */
    public function redirect()
    {
        return Socialite::driver('line')->redirect();
    }

    /**
     * LINEからのコールバック処理
     */
    public function callback()
    {
        try {
            $lineUser = Socialite::driver('line')->user();
        } catch (\Exception $e) {
            return redirect()->route('login')
                ->with('status', 'LINEログインに失敗しました。もう一度お試しください。');
        }

        $lineId = $lineUser->getId();

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // パターン1: ログイン済みユーザーの「LINE連携」
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        if (Auth::check()) {
            $currentUser = Auth::user();

            // 他のユーザーが既にこのLINE IDを使っていないか確認
            $existingUser = User::where('line_id', $lineId)
                ->where('id', '!=', $currentUser->id)
                ->first();

            if ($existingUser) {
                return redirect()->route('dashboard')
                    ->with('status', 'このLINEアカウントは既に別のユーザーに連携されています。');
            }

            // 現在のユーザーにLINE IDを紐付け
            $currentUser->update([
                'line_id' => $lineId,
            ]);

            return redirect()->route('dashboard')
                ->with('status', 'LINEアカウントを連携しました！値下げ通知をLINEで受け取れます。');
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // パターン2: 未ログイン → LINE IDで既存ユーザーを検索
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        $user = User::where('line_id', $lineId)->first();

        if ($user) {
            Auth::login($user, remember: true);
            return redirect()->intended('/');
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // パターン3: 同じメールアドレスのユーザーが存在 → LINE連携
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        $email = $lineUser->getEmail(); // LINEはメール取得にユーザーの許可が必要

        if ($email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->update(['line_id' => $lineId]);
                Auth::login($user, remember: true);
                return redirect()->intended('/');
            }
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // パターン4: 完全新規ユーザー → 新規作成
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        $user = User::create([
            'name'              => $lineUser->getName() ?? 'LINEユーザー',
            'email'             => $email ?? 'line_' . $lineId . '@motohub.local', // メール未取得時の仮アドレス
            'line_id'           => $lineId,
            'avatar'            => $lineUser->getAvatar(),
            'email_verified_at' => $email ? now() : null, // メールがあれば認証済み扱い
        ]);

        Auth::login($user, remember: true);
        return redirect()->intended('/');
    }

    /**
     * LINE連携の解除
     */
    public function unlink()
    {
        $user = Auth::user();

        // Googleログインもパスワードもない場合は解除不可（ログインできなくなる）
        if (!$user->google_id && !$user->password) {
            return redirect()->route('dashboard')
                ->with('status', 'LINE以外のログイン方法がないため、連携を解除できません。先にパスワードを設定してください。');
        }

        $user->update(['line_id' => null]);

        return redirect()->route('dashboard')
            ->with('status', 'LINEアカウントの連携を解除しました。');
    }
}