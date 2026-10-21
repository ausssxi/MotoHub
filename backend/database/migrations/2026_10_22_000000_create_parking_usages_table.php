<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parking_id')->constrained('bike_parkings')->cascadeOnDelete();
            // sha256(ip|user-agent|app.key)。生IPは保存しない（個人情報を持たない・連投の単位のみ）。
            $table->string('visitor_hash', 64);
            $table->timestamp('created_at')->nullable();

            // 同じ訪問者は1駐車場につき1回だけ記録する。
            $table->unique(['parking_id', 'visitor_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_usages');
    }
};
