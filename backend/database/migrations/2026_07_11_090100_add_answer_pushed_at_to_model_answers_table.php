<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 回答→質問者への通知の送信済みフラグ。null=未送信・非null=送信キュー投入済み。
 * 同じ回答で複数回送らないための冪等キー（キル→復活しても再送しない）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_answers', function (Blueprint $table) {
            $table->timestamp('answer_pushed_at')->nullable()->after('helpful_count');
        });
    }

    public function down(): void
    {
        Schema::table('model_answers', function (Blueprint $table) {
            $table->dropColumn('answer_pushed_at');
        });
    }
};
