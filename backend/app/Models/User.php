<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
// Filament用のクラスをインポート
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'review_display_name', // レビュー公開ハンドル（name=本名とは別・公開表示はこれのみ）
        'email',
        'password',
        'is_admin',
        'role',
        'notify_price_drop',
        'profile_show_parking_reviews',
        'google_id',
        'avatar',
        'line_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'notify_price_drop' => 'boolean',
            'profile_show_parking_reviews' => 'boolean',
        ];
    }

    public function isGoogleUser(): bool
    {
        return ! is_null($this->google_id);
    }

    /**
     * お気に入り車両とのリレーション
     * （★値下げアラート機能でここを使用します！）
     */
    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'favorites', 'user_id', 'listing_id')
            ->withTimestamps();
    }

    /**
     * 閲覧履歴 (更新日時順)
     */
    public function browsingHistories(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'browsing_histories', 'user_id', 'listing_id')
            ->withTimestamps()
            ->orderByPivot('updated_at', 'desc');
    }

    /**
     * 検索条件の保存
     */
    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class)->orderBy('created_at', 'desc');
    }

    /**
     * 愛車ガレージとのリレーション
     */
    public function myBikes(): HasMany
    {
        return $this->hasMany(MyBike::class)->orderBy('created_at', 'desc');
    }

    /**
     * 公開プロフィールURLキー（/riders/{token}）を保証する。未設定なら一意なランダムトークンを発番して保存。
     * ガレージ公開時に呼ぶ＝公開面を持つユーザーだけがトークンを持つ（連番idは露出しない）。
     */
    public function ensurePublicToken(): string
    {
        if ($this->public_token) {
            return $this->public_token;
        }

        do {
            $token = \Illuminate\Support\Str::random(16);
        } while (self::where('public_token', $token)->exists());

        $this->public_token = $token;
        $this->save();

        return $token;
    }

    /**
     * Filament管理画面へのアクセス権限判定
     * これがないと本番環境で403エラーになります
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // is_admin カラムが true のユーザーのみ許可
        return $this->is_admin;
    }

    /**
     * LINE連携済みか判定
     */
    public function hasLineLinked(): bool
    {
        return ! is_null($this->line_id);
    }

    /**
     * ブログ記事とのリレーション
     */
    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'author_id');
    }
}
