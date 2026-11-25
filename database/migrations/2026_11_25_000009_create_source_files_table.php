<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingestion_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('dataset_id')->constrained('air_quality_datasets')->restrictOnDelete();
            $table->string('filename');
            $table->text('source_url')->nullable();
            $table->char('sha256', 64)->nullable()->index();
            $table->text('archive_uri')->nullable();
            $table->string('status')->index();
            $table->unsignedBigInteger('rows_seen')->default(0);
            $table->unsignedBigInteger('rows_stored')->default(0);
            $table->unsignedBigInteger('rows_rejected')->default(0);
            $table->timestamp('downloaded_at')->nullable();
            $table->text('error_summary')->nullable();
            $table->timestamps();

            $table->index(['dataset_id', 'filename']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_files');
    }
};
