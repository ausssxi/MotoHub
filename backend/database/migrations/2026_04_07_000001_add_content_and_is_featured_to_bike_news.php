<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bike_news', function (Blueprint $table) {
            $table->text('content')->nullable()->after('source');
            $table->boolean('is_featured')->default(false)->after('picks_count');
        });
    }

    public function down(): void
    {
        Schema::table('bike_news', function (Blueprint $table) {
            $table->dropColumn(['content', 'is_featured']);
        });
    }
};
