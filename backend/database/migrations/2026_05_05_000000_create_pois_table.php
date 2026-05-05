<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pois', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('osm_id');
            $table->enum('type', ['gas_station', 'convenience_store', 'michi_no_eki']);
            $table->string('name');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('address')->nullable();
            $table->string('brand')->nullable();
            $table->string('opening_hours')->nullable();
            $table->timestamps();

            $table->unique(['osm_id', 'type']);
            $table->index(['type', 'latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pois');
    }
};
