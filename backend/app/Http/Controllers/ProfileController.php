<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\UpdateAvatarRequest;
use App\Services\Profile\AvatarImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use RuntimeException;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * プロフィール表示設定の更新（公開プロフィールへの集約オプトアウト）。
     * チェックボックスは未チェック時に送信されないため boolean() で false 既定にする。
     */
    public function updateVisibility(Request $request): RedirectResponse
    {
        $request->user()->update([
            'profile_show_parking_reviews' => $request->boolean('profile_show_parking_reviews'),
        ]);

        return Redirect::route('profile.edit')->with('status', 'visibility-updated');
    }

    /**
     * アバター画像のアップロード。実MIME検証(UpdateAvatarRequest)→正方形クロップ→EXIF/GPS除去→
     * public 保存→users.avatar_path 更新。差し替え時は AvatarImageService が旧ファイルを削除する。
     */
    public function updateAvatar(UpdateAvatarRequest $request, AvatarImageService $avatars): RedirectResponse
    {
        try {
            $avatars->update($request->user(), $request->file('avatar'));
        } catch (RuntimeException $e) {
            // ImageReader が読めない（拡張子は通ったが実体が壊れ画像/非画像）ケースの保険。
            return Redirect::route('profile.edit')
                ->withErrors(['avatar' => '画像を読み込めませんでした。別の画像でお試しください。']);
        }

        return Redirect::route('profile.edit')->with('status', 'avatar-updated');
    }

    /**
     * アバターを外す（既定のイニシャル/汎用アイコン表示に戻す）。実ファイルも削除する。
     */
    public function destroyAvatar(Request $request, AvatarImageService $avatars): RedirectResponse
    {
        $avatars->remove($request->user());

        return Redirect::route('profile.edit')->with('status', 'avatar-removed');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Googleのみのユーザーはパスワード確認をスキップ
        if (! ($user->isGoogleUser() && is_null($user->password))) {
            $request->validateWithBag('userDeletion', [
                'password' => ['required', 'current_password'],
            ]);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
