<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // お気に入り登録されている総数を保持するカラムを追加
            $table->unsignedInteger('favorite_count')->default(0)->after('view_count_today');
            $table->index('favorite_count');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('favorite_count');
        });
    }
};