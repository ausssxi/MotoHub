<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_garages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('operator', 100)->nullable(); // ブランド名（イナバボックス等）
            // indoor=屋内ガレージ/シャッター付, container=屋外コンテナ, open=青空月極, other=不明
            $table->enum('garage_type', ['indoor', 'container', 'open', 'other'])->default('other');
            $table->string('postal_code', 8)->nullable();
            $table->string('prefecture', 10);
            $table->string('city', 50)->nullable();
            $table->string('address', 255);
            // スクレイピング直後は座標未確定のため nullable。
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('monthly_fee_min')->nullable();
            $table->unsignedInteger('monthly_fee_max')->nullable();
            $table->string('size_text', 100)->nullable(); // 「1.5帖」「W142×D220×H228cm」など原文
            // 不明を null で許容する（false と区別する）。
            $table->boolean('is_24h')->nullable();
            $table->boolean('has_power')->nullable();
            $table->boolean('has_security')->nullable();
            $table->boolean('has_shutter')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('website_url', 255)->nullable();
            $table->text('description')->nullable();
            $table->enum('source', ['official', 'user'])->default('official');
            // 事業者の物件詳細ページURL。string(255) 厳守（500 だとキー長で詰まる可能性）。
            $table->string('source_url', 255)->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->enum('geocode_status', ['pending', 'ok', 'failed', 'out_of_range'])->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('source_url'); // スクレイパーの updateOrCreate キー（bike_parkings と同流儀）
            $table->index(['latitude', 'longitude']);
            $table->index('prefecture');
            $table->index('garage_type');
            $table->index(['is_active', 'garage_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_garages');
    }
};
