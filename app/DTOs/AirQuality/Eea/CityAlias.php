<?php

namespace App\DTOs\AirQuality\Eea;

final readonly class CityAlias
{
    public function __construct(
        public int $cityId,
        public string $externalName,
    ) {}
}
