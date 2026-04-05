<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bike_news', function (Blueprint $table) {
            $table->dropUnique(['url']);
        });

        Schema::table('bike_news', function (Blueprint $table) {
            $table->text('url')->change();
        });

        // TEXT列にはプレフィックス長指定のユニークインデックスが必要
        DB::statement('CREATE UNIQUE INDEX bike_news_url_unique ON bike_news (url(191))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX bike_news_url_unique ON bike_news');

        Schema::table('bike_news', function (Blueprint $table) {
            $table->string('url')->change();
            $table->unique('url');
        });
    }
};
