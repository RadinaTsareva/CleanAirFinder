<?php

namespace App\Console\Commands;

use App\Services\AirQuality\Eea\SyncEeaE1aService;
use Illuminate\Console\Command;
use Throwable;

class SyncEeaE1a extends Command
{
    protected $signature = 'air-quality:sync-eea-e1a';

    protected $description = 'Discover and archive new EEA E1a PM2.5 Parquet files for Bulgaria';

    public function handle(SyncEeaE1aService $service): int
    {
        try {
            $result = $service->sync();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'EEA E1a sync %d: checked %d cities, found %d files, downloaded %d new files, unchanged %d, errors %d.',
            $result->runId,
            $result->citiesChecked,
            $result->filesDiscovered,
            $result->filesDownloaded,
            $result->filesUnchanged,
            count($result->errors),
        ));

        foreach ($result->errors as $error) {
            $this->warn($error);
        }

        return $result->succeeded() ? self::SUCCESS : self::FAILURE;
    }
}
