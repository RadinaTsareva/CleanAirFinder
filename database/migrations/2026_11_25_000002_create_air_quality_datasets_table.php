<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('air_quality_datasets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('air_quality_sources')->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedSmallInteger('external_dataset_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'code']);
            $table->index(['source_id', 'external_dataset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('air_quality_datasets');
    }
};
