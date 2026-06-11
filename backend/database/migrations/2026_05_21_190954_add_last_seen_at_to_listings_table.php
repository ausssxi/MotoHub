<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('is_sold_out');
        });

        // 既存のアクティブなリスティングを初期化
        DB::statement('UPDATE listings SET last_seen_at = updated_at WHERE is_sold_out = 0');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};
