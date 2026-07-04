<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 症状診断ファネルの計測イベント。
 * PIIなし: 生IP・ユーザーIDは保存しない。session_id はクライアント生成の
 * 使い捨てランダムUUID（sessionStorage）。180日で prune。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trouble_events', function (Blueprint $table) {
            $table->id();
            $table->char('session_id', 36);            // クライアント生成UUID
            $table->string('event', 30);               // symptom_selected / step_answered / verdict_shown / cta_clicked
            $table->string('symptom', 50)->nullable();
            $table->string('step', 50)->nullable();    // カード/ノードID
            $table->string('answer', 50)->nullable();
            $table->string('verdict', 30)->nullable(); // 5判定のいずれか
            $table->string('cta', 30)->nullable();     // article / shop / parts / register / retry / submit_shop
            $table->string('source', 20)->nullable();  // deeplink 等
            $table->timestamp('created_at');
            $table->index(['event', 'created_at']);
            $table->index(['symptom', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trouble_events');
    }
};
