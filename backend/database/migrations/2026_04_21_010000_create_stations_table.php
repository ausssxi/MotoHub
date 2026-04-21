<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 150)->unique();
            $table->string('kana', 200)->nullable();
            $table->string('prefecture', 10);
            $table->string('city', 50)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('line_names', 500)->nullable();
            $table->string('company_names', 500)->nullable();
            $table->unsignedInteger('passengers_per_day')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_major')->default(false);
            $table->timestamps();

            $table->index('prefecture');
            $table->index('is_major');
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stations');
    }
};
