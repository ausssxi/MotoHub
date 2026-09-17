<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 加瀬倉庫の物件が持つ区画種別（cntn / bike / bike-out / trnk）を保持する中間テーブル。
 *
 * ★ rental_garages.garage_type は触らない（他社データ = イナバボックス212件・ストレージ王109件が入る）。
 *   種別は「1物件が複数持つ」ため中間テーブルに分離する。表示名は config/rental_garage.php。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_garage_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_garage_id')->constrained('rental_garages')->cascadeOnDelete();
            $table->string('type_code', 20); // cntn / bike / bike-out / trnk（ID で判定。表示名は config）
            $table->timestamps();

            $table->unique(['rental_garage_id', 'type_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_garage_types');
    }
};
