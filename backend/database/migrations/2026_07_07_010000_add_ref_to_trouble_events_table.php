<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * trouble_events.ref: 入口別計測。/trouble?ref={source} の内部識別子（PIIなし）。
 * どのリンクにどの ref を振るかは運用で決める（実装は「あれば記録」まで）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trouble_events', function (Blueprint $table) {
            $table->string('ref', 50)->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('trouble_events', function (Blueprint $table) {
            $table->dropColumn('ref');
        });
    }
};
