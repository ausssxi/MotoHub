<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 車種×作業（型番）適合表。人手でキュレーションしたCSVからのみ投入する。
 * verified_at が入った行を持つ車種×task のみ公開（ショップ3件ゲートと同思想）。
 *
 * ※ bike_models.slug は既存（2026_03_02 のマイグレーションで追加済み）のため
 *   本マイグレーションでは追加しない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_fitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bike_model_id')->constrained()->cascadeOnDelete();
            $table->string('task', 20);                       // 'battery'（将来 'plug' 等）
            $table->string('frame_code', 30)->default('');    // 'AF62'。型式区別なしは空文字。NULL不使用
            $table->string('year_range', 30)->default('');    // ソース表記のまま '04.2〜07' 等
            $table->string('oem_part_no', 30)->nullable();    // 新車搭載品番
            $table->string('recommended_part_no', 30);        // 市販推奨（現行品番）
            $table->json('compatible_part_nos')->nullable();  // [{"brand":"台湾ユアサ","part_no":"…"}]
            $table->json('spec')->nullable();                 // {"voltage":"12V","capacity":"4Ah","type":"VRLA"}
            $table->string('source_1_name', 100)->nullable();
            $table->string('source_1_url', 500)->nullable();
            $table->string('source_2_name', 100)->nullable();
            $table->string('source_2_url', 500)->nullable();
            $table->date('verified_at')->nullable();          // 入った行のみ公開対象
            $table->text('note')->nullable();
            $table->timestamps();

            // frame_code / year_range を空文字デフォルトのNOT NULLにするのは、
            // MySQLのUNIQUEがNULLを重複可能に扱うため（置換インポートを安定させる）。
            $table->unique(['bike_model_id', 'task', 'frame_code', 'year_range'], 'fitments_unique');
            $table->index(['task', 'verified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_fitments');
    }
};
