<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roadside_stations', function (Blueprint $table) {
            $table->id();
            $table->string('station_code', 5)->unique();
            $table->string('name');
            $table->string('nickname')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('prefecture', 10)->nullable();
            $table->string('city', 50)->nullable();
            $table->string('route')->nullable();
            $table->string('image_url')->nullable();
            $table->text('summary')->nullable();
            $table->string('website_url')->nullable();
            $table->string('wikipedia_url')->nullable();

            // 施設フラグ
            $table->boolean('has_atm')->default(false);
            $table->boolean('has_restaurant')->default(false);
            $table->boolean('has_onsen')->default(false);
            $table->boolean('has_ev_charging')->default(false);
            $table->boolean('has_wifi')->default(false);
            $table->boolean('has_shower')->default(false);
            $table->boolean('has_camp')->default(false);
            $table->boolean('has_gas_station')->default(false);
            $table->boolean('has_observatory')->default(false);
            $table->boolean('has_shop')->default(false);

            $table->unsignedSmallInteger('designated_year')->nullable();
            $table->timestamps();

            $table->index(['latitude', 'longitude']);
            $table->index('prefecture');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadside_stations');
    }
};
