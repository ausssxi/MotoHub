<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class GarageLike extends Model
{
    protected $fillable = [
        'user_id',
        'my_bike_id',
    ];

    /**
     * 指定ユーザーが「いいね済み」の my_bike_id を一括取得する（一覧の N+1 防止）。
     *
     * @param  array<int,int>  $bikeIds
     * @return array<int,int> いいね済みの my_bike_id
     */
    public static function likedIdsFor(?int $userId, array $bikeIds): array
    {
        if ($userId === null || $bikeIds === []) {
            return [];
        }

        return self::where('user_id', $userId)
            ->whereIn('my_bike_id', $bikeIds)
            ->pluck('my_bike_id')
            ->all();
    }
}
