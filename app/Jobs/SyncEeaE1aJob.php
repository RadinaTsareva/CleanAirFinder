<?php

namespace App\Jobs;

use App\Services\AirQuality\Eea\SyncEeaE1aService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class SyncEeaE1aJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function handle(SyncEeaE1aService $service): void
    {
        $result = $service->sync();

        Log::info('EEA E1a ingestion completed.', [
            'run_id' => $result->runId,
            'cities_checked' => $result->citiesChecked,
            'files_discovered' => $result->filesDiscovered,
            'files_downloaded' => $result->filesDownloaded,
            'files_unchanged' => $result->filesUnchanged,
            'errors' => $result->errors,
        ]);
    }
}
