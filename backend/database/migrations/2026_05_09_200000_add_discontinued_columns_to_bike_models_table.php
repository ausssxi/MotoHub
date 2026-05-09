<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bike_models', function (Blueprint $table) {
            $table->boolean('is_discontinued')->default(false)->after('display_name')->comment('絶版車フラグ');
            $table->unsignedSmallInteger('production_end_year')->nullable()->after('is_discontinued')->comment('生産終了年');
            $table->index('is_discontinued');
        });
    }

    public function down(): void
    {
        Schema::table('bike_models', function (Blueprint $table) {
            $table->dropIndex(['is_discontinued']);
            $table->dropColumn(['is_discontinued', 'production_end_year']);
        });
    }
};
