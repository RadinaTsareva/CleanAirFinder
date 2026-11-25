<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_files', function (Blueprint $table) {
            $table->string('remote_etag')->nullable();
            $table->string('remote_last_modified')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unique(['dataset_id', 'sha256'], 'source_files_dataset_sha256_unique');
        });
    }

    public function down(): void
    {
        Schema::table('source_files', function (Blueprint $table) {
            $table->dropUnique('source_files_dataset_sha256_unique');
            $table->dropColumn(['remote_etag', 'remote_last_modified', 'file_size_bytes']);
        });
    }
};
