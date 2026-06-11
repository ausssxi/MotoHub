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

        if (DB::connection()->getDriverName() === 'mysql') {
            // MySQL: TEXT列にはプレフィックス長指定のユニークインデックスが必要
            DB::statement('CREATE UNIQUE INDEX bike_news_url_unique ON bike_news (url(191))');
        } else {
            // sqlite等: プレフィックス長は非対応。フルユニークを張る
            Schema::table('bike_news', function (Blueprint $table) {
                $table->unique('url', 'bike_news_url_unique');
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('DROP INDEX bike_news_url_unique ON bike_news');
        } else {
            Schema::table('bike_news', function (Blueprint $table) {
                $table->dropUnique('bike_news_url_unique');
            });
        }
        Schema::table('bike_news', function (Blueprint $table) {
            $table->string('url')->change();
            $table->unique('url');
        });
    }
};
