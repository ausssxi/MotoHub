<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * users.public_token: ライダー公開プロフィール(/riders/{token})の安定した非連番URLキー。
 * - 公開ハンドル(review_display_name)は非一意・可変なのでURLキーに使えない。
 * - 連番 user_id の露出を避けるためランダムトークンを採用（推測・列挙されにくい）。
 * - 公開ガレージを持つ既存ユーザーにはバックフィルで付与する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('public_token', 24)->nullable()->unique()->after('review_display_name')
                ->comment('公開プロフィールURLキー（非連番・推測困難）');
        });

        // 既に公開ガレージを持つユーザーへバックフィル
        $userIds = DB::table('my_bikes')->where('is_public', true)->distinct()->pluck('user_id');
        foreach ($userIds as $userId) {
            do {
                $token = Str::random(16);
            } while (DB::table('users')->where('public_token', $token)->exists());
            DB::table('users')->where('id', $userId)->whereNull('public_token')->update(['public_token' => $token]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });
    }
};
