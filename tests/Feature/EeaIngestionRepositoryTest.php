<?php

namespace Tests\Feature;

use App\Repositories\Eloquent\EloquentEeaIngestionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EeaIngestionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_an_idempotent_ingestion_file_record_and_run_summary(): void
    {
        $sourceId = DB::table('air_quality_sources')->insertGetId([
            'code' => 'eea',
            'name' => 'European Environment Agency',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $datasetId = DB::table('air_quality_datasets')->insertGetId([
            'source_id' => $sourceId,
            'code' => 'e1a',
            'name' => 'Validated historical data',
            'external_dataset_id' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cityId = DB::table('cities')->insertGetId([
            'country_code' => 'BG',
            'name' => 'Varna',
            'slug' => 'varna',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('source_city_names')->insert([
            'source_id' => $sourceId,
            'city_id' => $cityId,
            'external_name' => 'Varna',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $repository = new EloquentEeaIngestionRepository;
        $context = $repository->getE1aContext();

        $this->assertNotNull($context);
        $this->assertSame($datasetId, $context->datasetId);
        $this->assertSame(2, $context->externalDatasetId);
        $this->assertSame('Varna', $context->cities[0]->externalName);

        $runId = $repository->startRun($sourceId, $datasetId);
        $repository->upsertSamplingPoint($sourceId, $cityId, 'SPO-BG0075A');
        $repository->upsertSamplingPoint($sourceId, $cityId, 'SPO-BG0075A');

        $filename = 'SPO-BG0075A_06001_100.parquet';
        $sha256 = hash('sha256', 'test-file');
        $repository->recordDownloadedFile(
            $runId,
            $datasetId,
            $filename,
            'https://example.blob.core.windows.net/'.$filename,
            $sha256,
            '"etag"',
            null,
            9,
            'eea/e1a/'.$sha256.'/'.$filename,
        );

        $repository->finishRun($runId, 'completed', 1, ['files_downloaded' => 1], null);

        $this->assertSame(1, DB::table('sampling_points')->count());
        $this->assertTrue($repository->hasFileHash($datasetId, $sha256));
        $this->assertSame($sha256, $repository->findDownloadedFile($datasetId, $filename)?->sha256);
        $this->assertSame('completed', DB::table('ingestion_runs')->where('id', $runId)->value('status'));
    }
}
