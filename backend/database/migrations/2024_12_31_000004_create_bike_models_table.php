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
        Schema::create('bike_models', function (Blueprint $table) {
            $table->id()->comment('ID (auto_increment)');
            $table->foreignId('manufacturer_id')->constrained('manufacturers')->comment('メーカーID');
            $table->string('name', 255)->comment('モデル名');
            $table->unsignedSmallInteger('displacement')->nullable()->comment('排気量 (cc)');
            $table->string('category', 50)->nullable()->comment('カテゴリ');
            $table->string('engine_type', 50)->nullable()->comment('エンジン種類');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bike_models');
    }
};

