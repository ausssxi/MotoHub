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
        Schema::create('shops', function (Blueprint $table) {
            $table->id()->comment('ID (auto_increment)');
            $table->string('name', 255)->comment('店舗名');
            $table->string('address', 500)->nullable()->comment('住所');
            $table->string('phone', 20)->nullable()->comment('電話番号');
            $table->string('email', 255)->nullable()->comment('メールアドレス');
            $table->string('website', 500)->nullable()->comment('ウェブサイトURL');
            $table->decimal('latitude', 10, 8)->nullable()->comment('緯度');
            $table->decimal('longitude', 11, 8)->nullable()->comment('経度');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};

