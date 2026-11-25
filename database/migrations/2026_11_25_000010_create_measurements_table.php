<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_file_id')->constrained()->restrictOnDelete();
            $table->foreignId('sampling_point_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pollutant_id')->nullable()->constrained()->nullOnDelete();

            // Keep source values and timestamps as received; parsed columns support queries.
            $table->string('source_sampling_point')->nullable();
            $table->string('source_pollutant_code')->nullable();
            $table->string('source_record_id')->nullable();
            $table->unsignedBigInteger('source_row_index')->nullable();
            $table->string('source_start')->nullable();
            $table->string('source_end')->nullable();
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->date('measurement_date')->nullable();
            $table->decimal('measurement_value', 16, 6)->nullable();
            $table->text('source_value')->nullable();
            $table->string('source_unit')->nullable();
            $table->string('aggregation_type')->nullable();
            $table->smallInteger('validity')->nullable();
            $table->smallInteger('verification')->nullable();
            $table->dateTime('result_time')->nullable();
            $table->decimal('data_capture', 8, 4)->nullable();
            $table->jsonb('raw_payload')->nullable();
            $table->timestamps();

            // No natural-key uniqueness constraint is added until EEA update semantics
            // and duplicate handling have been validated.
            $table->index(['sampling_point_id', 'start_at']);
            $table->index(['pollutant_id', 'measurement_date']);
            $table->index(['source_file_id', 'source_row_index']);
            $table->index(['validity', 'verification', 'measurement_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurements');
    }
};
