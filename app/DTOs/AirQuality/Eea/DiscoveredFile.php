<?php

namespace App\DTOs\AirQuality\Eea;

final readonly class DiscoveredFile
{
    public function __construct(
        public string $url,
        public string $filename,
        public string $samplingPointId,
    ) {}
}
