<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dataset_pollutants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('air_quality_datasets')->cascadeOnDelete();
            $table->foreignId('pollutant_id')->constrained()->restrictOnDelete();
            $table->string('request_code')->nullable();
            $table->string('external_code')->nullable();
            $table->timestamps();

            $table->unique(['dataset_id', 'pollutant_id']);
            $table->index(['dataset_id', 'external_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_pollutants');
    }
};
