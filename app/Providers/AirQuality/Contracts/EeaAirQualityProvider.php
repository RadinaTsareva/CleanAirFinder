<?php

namespace App\Providers\AirQuality\Contracts;

use App\DTOs\AirQuality\Eea\RemoteFileMetadata;

interface EeaAirQualityProvider
{
    /** @return list<string> */
    public function discoverParquetUrls(string $city, int $datasetId): array;

    public function remoteMetadata(string $url): RemoteFileMetadata;

    public function downloadTo(string $url, string $temporaryPath): void;
}
