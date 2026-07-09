<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ユーザーからの「不適切な投稿の通報」。polymorphic で当面は shop_acceptance_reports
 * （ショップ口コミ）を対象にし、将来 reviews 等へ横展開できる形にする。
 * プロバイダ責任制限法の免責は「運営が認知でき、対応できる導線がある」ことが実体。
 * 生IPは保存せず sha256(ip|app.key) のみ（既存の投稿系と同一方式）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->morphs('reportable');                       // reportable_type / reportable_id
            $table->string('reason')->nullable();               // 通報理由キー（fact/defame/spam/other 等）
            $table->string('reporter_ip_hash', 64)->nullable(); // sha256(ip|app.key)・生IP非保存
            $table->string('status')->default('open');          // open / reviewed / dismissed
            $table->timestamps();

            $table->index('status'); // 未対応キュー用（morphs が reportable の複合indexを張る）
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
