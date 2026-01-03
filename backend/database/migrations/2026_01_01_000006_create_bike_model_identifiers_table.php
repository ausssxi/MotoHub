<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bike_model_identifiers', function (Blueprint $table) {
            $table->id();
            // bike_modelsテーブルへの外部キー
            $table->foreignId('bike_model_id')->constrained('bike_models')->onDelete('cascade')->comment('車種マスタID');
            // sitesテーブルへの外部キー
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade')->comment('サイトID');
            // サイト固有の識別番号 (例: GooBikeの '1010001' など)
            $table->string('identifier', 100)->comment('サイト固有の車種識別番号');
            $table->timestamp('created_at')->useCurrent()->comment('作成日時');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('更新日時');
            // 同じサイト内で同じ識別番号が重複しないようにユニーク制約
            $table->unique(['site_id', 'identifier']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bike_model_identifiers');
    }
};