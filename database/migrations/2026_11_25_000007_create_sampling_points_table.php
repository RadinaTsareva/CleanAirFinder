<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sampling_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('air_quality_sources')->restrictOnDelete();
            $table->foreignId('station_id')->nullable()->constrained('air_quality_stations')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id');
            $table->string('source_sampling_point')->nullable();
            $table->string('measurement_type')->nullable();
            $table->string('measurement_method')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'external_id']);
            $table->index(['city_id', 'station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sampling_points');
    }
};
