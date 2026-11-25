<?php

namespace App\DTOs\AirQuality\Eea;

final readonly class StoredSourceFile
{
    public function __construct(
        public string $sha256,
        public ?string $etag,
    ) {}
}
