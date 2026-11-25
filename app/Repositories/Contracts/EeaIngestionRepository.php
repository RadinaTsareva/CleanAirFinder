<?php

namespace App\Repositories\Contracts;

use App\DTOs\AirQuality\Eea\IngestionContext;
use App\DTOs\AirQuality\Eea\StoredSourceFile;

interface EeaIngestionRepository
{
    public function getE1aContext(): ?IngestionContext;

    public function startRun(int $sourceId, int $datasetId): int;

    public function finishRun(int $runId, string $status, int $filesSeen, array $details, ?string $errorSummary): void;

    public function upsertSamplingPoint(int $sourceId, int $cityId, string $externalId): void;

    public function findDownloadedFile(int $datasetId, string $filename): ?StoredSourceFile;

    public function hasFileHash(int $datasetId, string $sha256): bool;

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
    ): void;
}
