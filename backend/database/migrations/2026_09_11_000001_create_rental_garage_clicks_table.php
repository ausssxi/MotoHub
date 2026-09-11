<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_garage_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_garage_id')->constrained('rental_garages')->cascadeOnDelete();
            $table->timestamp('clicked_at');
            // サイト内のどのページから飛んだか（内部パスのみ・ホスト無し）。外部/不明は null。
            $table->string('referrer_path', 255)->nullable();
            // User-Agent でクローラーと判定したもの。レコードは残し、集計時に除外する。
            $table->boolean('is_bot')->default(false);
            // ※ IPアドレスは意図的に保存しない（個人情報を持たない）。

            $table->index(['rental_garage_id', 'clicked_at']);
            $table->index(['is_bot', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_garage_clicks');
    }
};
