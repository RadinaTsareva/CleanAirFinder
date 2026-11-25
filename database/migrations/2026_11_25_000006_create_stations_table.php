<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('air_quality_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('air_quality_sources')->restrictOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id')->nullable();
            $table->string('name')->nullable();
            $table->string('eoi_code')->nullable();
            $table->string('national_code')->nullable();
            $table->string('station_type')->nullable();
            $table->string('station_area')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('altitude_m', 8, 2)->nullable();
            $table->string('source_timezone')->nullable();
            $table->string('metadata_source')->nullable();
            $table->timestamp('metadata_retrieved_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'external_id']);
            $table->index(['city_id', 'station_area']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('air_quality_stations');
    }
};
