<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_city_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('air_quality_sources')->restrictOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('external_name');
            $table->timestamps();

            $table->unique(['source_id', 'external_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_city_names');
    }
};
