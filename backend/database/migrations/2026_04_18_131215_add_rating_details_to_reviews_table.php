<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating_design')->nullable()->after('rating');
            $table->unsignedTinyInteger('rating_engine')->nullable()->after('rating_design');
            $table->unsignedTinyInteger('rating_handling')->nullable()->after('rating_engine');
            $table->unsignedTinyInteger('rating_fuel_economy')->nullable()->after('rating_handling');
            $table->unsignedTinyInteger('rating_cost_performance')->nullable()->after('rating_fuel_economy');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn([
                'rating_design',
                'rating_engine',
                'rating_handling',
                'rating_fuel_economy',
                'rating_cost_performance',
            ]);
        });
    }
};
