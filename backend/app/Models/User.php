<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
// Filament用のクラスをインポート
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

// ★修正: implements FilamentUser を追加
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
        'email',
        'password',
        'is_admin',
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
        ];
    }

    /**
     * お気に入り車両とのリレーション
     */
    public function favorites(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'favorites', 'user_id', 'listing_id')
                    ->withTimestamps();
    }

    /**
     * 閲覧履歴 (更新日時順)
     */
    public function browsingHistories(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'browsing_histories', 'user_id', 'listing_id')
                    ->withTimestamps()
                    ->orderByPivot('updated_at', 'desc');
    }

    /**
     * 検索条件の保存
     */
    public function savedSearches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SavedSearch::class)->orderBy('created_at', 'desc');
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
}