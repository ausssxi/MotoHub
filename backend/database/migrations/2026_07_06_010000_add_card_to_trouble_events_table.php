<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * trouble_events.card: 表示中の原因カードID（battery/plug等）。
 * verdict_shown / cta_clicked / feedback で記録し、原因単位の解決率集計に使う。
 * 既存行は null のまま（バックフィル不要）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trouble_events', function (Blueprint $table) {
            $table->string('card', 50)->nullable()->after('step');
        });
    }

    public function down(): void
    {
        Schema::table('trouble_events', function (Blueprint $table) {
            $table->dropColumn('card');
        });
    }
};
