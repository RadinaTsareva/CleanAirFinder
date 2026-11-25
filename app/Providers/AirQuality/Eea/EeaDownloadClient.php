<?php

namespace App\Providers\AirQuality\Eea;

use App\DTOs\AirQuality\Eea\RemoteFileMetadata;
use App\Providers\AirQuality\Contracts\EeaAirQualityProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EeaDownloadClient implements EeaAirQualityProvider
{
    public function discoverParquetUrls(string $city, int $datasetId): array
    {
        $response = $this->request()
            ->accept('text/plain')
            ->post($this->endpoint('ParquetFile/urls'), [
                'countries' => [config('services.eea.country_code')],
                'cities' => [$city],
                'pollutants' => [config('services.eea.pollutant')],
                'dataset' => $datasetId,
                'source' => 'API',
                'method' => null,
                'email' => null,
                'compress' => true,
            ])
            ->throw();

        preg_match_all('~https?://[^\s,"\']+~', $response->body(), $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $url) => rtrim($url, ';)'))
            ->unique()
            ->values()
            ->all();
    }

    public function remoteMetadata(string $url): RemoteFileMetadata
    {
        $response = $this->request()->head($url);

        if (! $response->successful()) {
            return new RemoteFileMetadata;
        }

        $contentLength = $response->header('Content-Length');

        return new RemoteFileMetadata(
            $response->header('ETag'),
            $response->header('Last-Modified'),
            is_numeric($contentLength) ? (int) $contentLength : null,
        );
    }

    public function downloadTo(string $url, string $temporaryPath): void
    {
        $response = $this->request(180)
            ->withOptions(['sink' => $temporaryPath])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('EEA file download failed with HTTP status '.$response->status().'.');
        }
    }

    private function endpoint(string $path): string
    {
        return rtrim(config('services.eea.base_url'), '/').'/'.ltrim($path, '/');
    }

    private function request(int $timeout = 30): PendingRequest
    {
        return Http::timeout($timeout)
            ->connectTimeout(10)
            ->retry(3, 500)
            ->withUserAgent('CleanAirFinder/0.1 (EEA data ingestion)');
    }
}
