<?php

namespace App\DTOs\AirQuality\Eea;

final readonly class RemoteFileMetadata
{
    public function __construct(
        public ?string $etag = null,
        public ?string $lastModified = null,
        public ?int $contentLength = null,
    ) {}
}
