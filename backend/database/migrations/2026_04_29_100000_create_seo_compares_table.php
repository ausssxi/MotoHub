<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_compares', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 200)->unique();
            $table->foreignId('model1_id')->constrained('bike_models')->cascadeOnDelete();
            $table->foreignId('model2_id')->constrained('bike_models')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_compares');
    }
};
