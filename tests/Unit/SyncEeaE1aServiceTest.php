<?php

namespace Tests\Unit;

use App\DTOs\AirQuality\Eea\CityAlias;
use App\DTOs\AirQuality\Eea\IngestionContext;
use App\DTOs\AirQuality\Eea\RemoteFileMetadata;
use App\Providers\AirQuality\Contracts\EeaAirQualityProvider;
use App\Repositories\Contracts\EeaIngestionRepository;
use App\Services\AirQuality\Eea\EeaFileValidator;
use App\Services\AirQuality\Eea\SyncEeaE1aService;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class SyncEeaE1aServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.eea.download_host_suffix' => 'blob.core.windows.net']);
        Storage::fake('local');
    }

    public function test_it_archives_and_records_a_valid_new_parquet_file(): void
    {
        $url = 'https://airquality-p-e1a.blob.core.windows.net/files/SPO-BG0075A_06001_100.parquet';
        $repository = Mockery::mock(EeaIngestionRepository::class);
        $provider = Mockery::mock(EeaAirQualityProvider::class);

        $repository->shouldReceive('getE1aContext')->once()->andReturn(new IngestionContext(
            1,
            1,
            2,
            [new CityAlias(17, 'Varna')],
        ));
        $repository->shouldReceive('startRun')->once()->with(1, 1)->andReturn(42);
        $repository->shouldReceive('upsertSamplingPoint')->once()->with(1, 17, 'SPO-BG0075A');
        $repository->shouldReceive('findDownloadedFile')->once()->andReturn(null);
        $repository->shouldReceive('hasFileHash')->once()->andReturn(false);
        $repository->shouldReceive('recordDownloadedFile')->once()->withArgs(
            fn ($runId, $datasetId, $filename, $sourceUrl, $hash, $etag, $modified, $size, $archiveUri): bool => $runId === 42
                && $datasetId === 1
                && $filename === 'SPO-BG0075A_06001_100.parquet'
                && $sourceUrl === $url
                && $hash === hash('sha256', 'PAR1dataPAR1')
                && $etag === '"v1"'
                && $size === 12
                && $archiveUri === 'eea/e1a/'.hash('sha256', 'PAR1dataPAR1').'/SPO-BG0075A_06001_100.parquet',
        );
        $repository->shouldReceive('finishRun')->once()->withArgs(
            fn ($id, $status, $seen, $details, $summary): bool => $id === 42
                && $status === 'completed'
                && $seen === 1
                && $summary === null,
        );

        $provider->shouldReceive('discoverParquetUrls')->once()->with('Varna', 2)->andReturn([$url]);
        $provider->shouldReceive('remoteMetadata')->once()->with($url)->andReturn(new RemoteFileMetadata('"v1"', null, 12));
        $provider->shouldReceive('downloadTo')->once()->with($url, Mockery::type('string'))
            ->andReturnUsing(fn (string $url, string $path) => file_put_contents($path, 'PAR1dataPAR1'));

        $service = new SyncEeaE1aService($provider, $repository, new EeaFileValidator);
        $result = $service->sync();

        $this->assertTrue($result->succeeded());
        $this->assertSame(1, $result->filesDownloaded);
        $this->assertSame(0, $result->filesUnchanged);
        $this->assertSame(1, $result->filesDiscovered);
        $this->assertCount(1, Storage::disk('local')->allFiles('eea/e1a'));
    }

    public function test_it_rejects_a_download_that_is_not_a_parquet_file(): void
    {
        $url = 'https://airquality-p-e1a.blob.core.windows.net/files/SPO-BG0075A_06001_100.parquet';
        $repository = Mockery::mock(EeaIngestionRepository::class);
        $provider = Mockery::mock(EeaAirQualityProvider::class);

        $repository->shouldReceive('getE1aContext')->once()->andReturn(new IngestionContext(
            1,
            1,
            2,
            [new CityAlias(17, 'Varna')],
        ));
        $repository->shouldReceive('startRun')->once()->andReturn(43);
        $repository->shouldReceive('upsertSamplingPoint')->once();
        $repository->shouldReceive('findDownloadedFile')->once()->andReturn(null);
        $repository->shouldReceive('finishRun')->once()->withArgs(
            fn ($id, $status, $seen, $details, $summary): bool => $status === 'completed_with_errors'
                && $summary !== null,
        );
        $repository->shouldNotReceive('hasFileHash');
        $repository->shouldNotReceive('recordDownloadedFile');

        $provider->shouldReceive('discoverParquetUrls')->once()->andReturn([$url]);
        $provider->shouldReceive('remoteMetadata')->once()->andReturn(new RemoteFileMetadata);
        $provider->shouldReceive('downloadTo')->once()->andReturnUsing(
            fn (string $url, string $path) => file_put_contents($path, 'not parquet'),
        );

        $result = (new SyncEeaE1aService($provider, $repository, new EeaFileValidator))->sync();

        $this->assertFalse($result->succeeded());
        $this->assertSame(0, $result->filesDownloaded);
        $this->assertCount(1, $result->errors);
        $this->assertSame([], Storage::disk('local')->allFiles('eea/e1a'));
    }
}
