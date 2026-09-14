<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_bike_shops', function (Blueprint $table) {
            $table->id();

            $table->string('company', 50);         // 事業者名（表示用）
            $table->string('company_slug', 50);    // rental819 / hondago / bikecenter / motobase など
            $table->string('external_id', 100)->nullable(); // 各社サイト上のID（あれば）

            $table->string('name');                // 店舗名
            $table->string('postal_code', 10)->nullable();
            $table->string('address');
            $table->string('prefecture', 10)->nullable();
            $table->string('city', 50)->nullable();
            $table->string('tel', 30)->nullable();
            $table->string('opening_hours')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('geocode_failed_at')->nullable();

            $table->string('official_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('fetched_at')->nullable();

            // 一意キー: (company_slug, external_id) があればそれ、無ければ (company_slug, name, address)。
            // MySQL の索引長制限を避けるため、上記を1本の文字列に落とした dedup_key で担保する。
            // ★画像用カラムは作らない（ウェビックの掲載停止の経緯。今後も画像は扱わない）。
            $table->string('dedup_key', 191)->unique();

            $table->timestamps();

            $table->index('company_slug');
            $table->index(['prefecture', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_bike_shops');
    }
};
