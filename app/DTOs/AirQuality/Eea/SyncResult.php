<?php

namespace App\DTOs\AirQuality\Eea;

final readonly class SyncResult
{
    /** @param list<string> $errors */
    public function __construct(
        public int $runId,
        public int $citiesChecked,
        public int $filesDiscovered,
        public int $filesDownloaded,
        public int $filesUnchanged,
        public array $errors,
    ) {}

    public function succeeded(): bool
    {
        return $this->errors === [];
    }
}
