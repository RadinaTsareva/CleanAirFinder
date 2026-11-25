<?php

namespace App\Repositories\Eloquent;

use App\DTOs\AirQuality\Eea\CityAlias;
use App\DTOs\AirQuality\Eea\IngestionContext;
use App\DTOs\AirQuality\Eea\StoredSourceFile;
use App\Repositories\Contracts\EeaIngestionRepository;
use Illuminate\Support\Facades\DB;

final class EloquentEeaIngestionRepository implements EeaIngestionRepository
{
    public function getE1aContext(): ?IngestionContext
    {
        $source = DB::table('air_quality_sources')->where('code', 'eea')->first();

        if (! $source) {
            return null;
        }

        $dataset = DB::table('air_quality_datasets')
            ->where('source_id', $source->id)
            ->where('code', 'e1a')
            ->first();

        if (! $dataset || ! $dataset->external_dataset_id) {
            return null;
        }

        $cities = DB::table('source_city_names')
            ->where('source_id', $source->id)
            ->orderBy('external_name')
            ->get(['city_id', 'external_name'])
            ->map(fn (object $city) => new CityAlias((int) $city->city_id, $city->external_name))
            ->all();

        if ($cities === []) {
            return null;
        }

        return new IngestionContext(
            (int) $source->id,
            (int) $dataset->id,
            (int) $dataset->external_dataset_id,
            $cities,
        );
    }

    public function startRun(int $sourceId, int $datasetId): int
    {
        return (int) DB::table('ingestion_runs')->insertGetId([
            'source_id' => $sourceId,
            'dataset_id' => $datasetId,
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function finishRun(int $runId, string $status, int $filesSeen, array $details, ?string $errorSummary): void
    {
        DB::table('ingestion_runs')->where('id', $runId)->update([
            'status' => $status,
            'files_seen' => $filesSeen,
            'finished_at' => now(),
            'error_summary' => $errorSummary,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);
    }

    public function upsertSamplingPoint(int $sourceId, int $cityId, string $externalId): void
    {
        DB::table('sampling_points')->upsert(
            [[
                'source_id' => $sourceId,
                'city_id' => $cityId,
                'external_id' => $externalId,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['source_id', 'external_id'],
            ['city_id', 'updated_at'],
        );

    }

    public function findDownloadedFile(int $datasetId, string $filename): ?StoredSourceFile
    {
        $file = DB::table('source_files')
            ->where('dataset_id', $datasetId)
            ->where('filename', $filename)
            ->where('status', 'downloaded')
            ->orderByDesc('id')
            ->first(['sha256', 'remote_etag']);

        return $file ? new StoredSourceFile($file->sha256, $file->remote_etag) : null;
    }

    public function hasFileHash(int $datasetId, string $sha256): bool
    {
        return DB::table('source_files')
            ->where('dataset_id', $datasetId)
            ->where('sha256', $sha256)
            ->exists();
    }

    public function recordDownloadedFile(
        int $runId,
        int $datasetId,
        string $filename,
        string $url,
        string $sha256,
        ?string $etag,
        ?string $lastModified,
        int $size,
        string $archiveUri,
    ): void {
        DB::table('source_files')->insert([
            'ingestion_run_id' => $runId,
            'dataset_id' => $datasetId,
            'filename' => $filename,
            'source_url' => $url,
            'sha256' => $sha256,
            'remote_etag' => $etag,
            'remote_last_modified' => $lastModified,
            'file_size_bytes' => $size,
            'archive_uri' => $archiveUri,
            'status' => 'downloaded',
            'downloaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
