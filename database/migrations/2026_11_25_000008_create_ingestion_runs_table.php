<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingestion_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('air_quality_sources')->restrictOnDelete();
            $table->foreignId('dataset_id')->nullable()->constrained('air_quality_datasets')->nullOnDelete();
            $table->string('status')->index();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('files_seen')->default(0);
            $table->unsignedBigInteger('rows_seen')->default(0);
            $table->unsignedBigInteger('rows_stored')->default(0);
            $table->unsignedBigInteger('rows_rejected')->default(0);
            $table->text('error_summary')->nullable();
            $table->jsonb('details')->nullable();
            $table->timestamps();

            $table->index(['source_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingestion_runs');
    }
};
