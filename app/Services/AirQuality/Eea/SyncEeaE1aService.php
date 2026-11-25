<?php

namespace App\Services\AirQuality\Eea;

use App\DTOs\AirQuality\Eea\CityAlias;
use App\DTOs\AirQuality\Eea\SyncResult;
use App\Providers\AirQuality\Contracts\EeaAirQualityProvider;
use App\Repositories\Contracts\EeaIngestionRepository;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class SyncEeaE1aService
{
    public function __construct(
        private readonly EeaAirQualityProvider $provider,
        private readonly EeaIngestionRepository $repository,
        private readonly EeaFileValidator $fileValidator,
    ) {}

    public function sync(): SyncResult
    {
        $context = $this->repository->getE1aContext();

        if (! $context) {
            throw new RuntimeException('EEA E1a reference data is missing. Run php artisan db:seed first.');
        }

        $runId = $this->repository->startRun($context->sourceId, $context->datasetId);
        $stats = [
            'cities_checked' => 0,
            'files_discovered' => 0,
            'files_downloaded' => 0,
            'files_unchanged' => 0,
            'errors' => [],
            'city_results' => [],
        ];

        try {
            foreach ($context->cities as $city) {
                $stats['cities_checked']++;

                try {
                    $urls = $this->provider->discoverParquetUrls($city->externalName, $context->externalDatasetId);
                    $stats['files_discovered'] += count($urls);
                    $stats['city_results'][$city->externalName] = ['files' => count($urls)];

                    foreach ($urls as $url) {
                        try {
                            $this->syncFile($url, $city, $context->sourceId, $context->datasetId, $runId, $stats);
                        } catch (Throwable $exception) {
                            $stats['errors'][] = $city->externalName.': '.$exception->getMessage();
                        }
                    }
                } catch (Throwable $exception) {
                    $stats['errors'][] = $city->externalName.': '.$exception->getMessage();
                }

                usleep(300_000);
            }
        } catch (Throwable $exception) {
            $stats['errors'][] = 'Sync failed: '.$exception->getMessage();
            $this->finishRun($runId, $stats, 'failed');

            throw $exception;
        }

        $this->finishRun($runId, $stats, $stats['errors'] === [] ? 'completed' : 'completed_with_errors');

        return new SyncResult(
            $runId,
            $stats['cities_checked'],
            $stats['files_discovered'],
            $stats['files_downloaded'],
            $stats['files_unchanged'],
            $stats['errors'],
        );
    }

    private function syncFile(
        string $url,
        CityAlias $city,
        int $sourceId,
        int $datasetId,
        int $runId,
        array &$stats,
    ): void {
        $file = $this->fileValidator->fromUrl($url);
        $this->repository->upsertSamplingPoint($sourceId, $city->cityId, $file->samplingPointId);

        $remote = $this->provider->remoteMetadata($url);
        $existing = $this->repository->findDownloadedFile($datasetId, $file->filename);

        if ($remote->etag !== null && $existing?->etag === $remote->etag) {
            $stats['files_unchanged']++;

            return;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'eea-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Could not create a temporary download file.');
        }

        try {
            $this->provider->downloadTo($url, $temporaryPath);

            if (! $this->fileValidator->isParquetFile($temporaryPath)) {
                throw new RuntimeException('Downloaded response is empty or is not a valid Parquet file.');
            }

            $sha256 = hash_file('sha256', $temporaryPath);

            if ($sha256 === false) {
                throw new RuntimeException('Could not calculate the downloaded file checksum.');
            }

            if ($this->repository->hasFileHash($datasetId, $sha256)) {
                $stats['files_unchanged']++;

                return;
            }

            $archiveUri = sprintf('eea/e1a/%s/%s', $sha256, $file->filename);
            $stream = fopen($temporaryPath, 'rb');

            if ($stream === false) {
                throw new RuntimeException('Could not open the Parquet file for archiving.');
            }

            try {
                $stored = Storage::disk('local')->put($archiveUri, $stream);
            } finally {
                fclose($stream);
            }

            if (! $stored || ! $this->verifyArchive($archiveUri, $sha256, filesize($temporaryPath))) {
                throw new RuntimeException('The archived Parquet file failed checksum or size verification.');
            }

            $this->repository->recordDownloadedFile(
                $runId,
                $datasetId,
                $file->filename,
                $url,
                $sha256,
                $remote->etag,
                $remote->lastModified,
                filesize($temporaryPath),
                $archiveUri,
            );

            $stats['files_downloaded']++;
            $stats['city_results'][$city->externalName]['downloaded'][] = $file->filename;
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function verifyArchive(string $archiveUri, string $expectedHash, int|false $expectedSize): bool
    {
        if ($expectedSize === false || Storage::disk('local')->size($archiveUri) !== $expectedSize) {
            return false;
        }

        $stream = Storage::disk('local')->readStream($archiveUri);

        if (! is_resource($stream)) {
            return false;
        }

        $context = hash_init('sha256');

        try {
            hash_update_stream($context, $stream);
        } finally {
            fclose($stream);
        }

        return hash_final($context) === $expectedHash;
    }

    private function finishRun(int $runId, array $stats, string $status): void
    {
        $errors = $stats['errors'];
        $this->repository->finishRun(
            $runId,
            $status,
            $stats['files_discovered'],
            $stats,
            $errors === [] ? null : json_encode(array_slice($errors, 0, 20), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }
}
