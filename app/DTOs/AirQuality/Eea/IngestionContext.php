<?php

namespace App\DTOs\AirQuality\Eea;

final readonly class IngestionContext
{
    /** @param list<CityAlias> $cities */
    public function __construct(
        public int $sourceId,
        public int $datasetId,
        public int $externalDatasetId,
        public array $cities,
    ) {}
}
