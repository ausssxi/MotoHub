<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 愛車ガレージの論理削除（誤削除の復元）。
 * my_bikes / fuel_logs / maintenance_logs / my_bike_images に deleted_at を追加。
 * - 子の FK は cascadeOnDelete（物理削除時のみ作用）。論理削除では効かないので、
 *   親の deleting/restoring フックで子を手動でソフト削除/復元する（モデル側）。
 * - garage_likes には付けない（バイクと共に隠す/復活）。backfill 不要。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('my_bikes', fn (Blueprint $t) => $t->softDeletes());
        Schema::table('fuel_logs', fn (Blueprint $t) => $t->softDeletes());
        Schema::table('maintenance_logs', fn (Blueprint $t) => $t->softDeletes());
        Schema::table('my_bike_images', fn (Blueprint $t) => $t->softDeletes());
    }

    public function down(): void
    {
        Schema::table('my_bikes', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('fuel_logs', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('maintenance_logs', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('my_bike_images', fn (Blueprint $t) => $t->dropSoftDeletes());
    }
};
