<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Follow extends Model
{
    protected $fillable = [
        'follower_id',
        'followee_id',
    ];
}
