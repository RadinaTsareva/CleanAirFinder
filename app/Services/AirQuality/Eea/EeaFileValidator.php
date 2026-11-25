<?php

namespace App\Services\AirQuality\Eea;

use App\DTOs\AirQuality\Eea\DiscoveredFile;
use RuntimeException;

final class EeaFileValidator
{
    public function fromUrl(string $url): DiscoveredFile
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $hostSuffix = '.'.ltrim(strtolower((string) config('services.eea.download_host_suffix')), '.');
        $filename = basename($parts['path'] ?? '');

        if (($parts['scheme'] ?? '') !== 'https'
            || ! str_ends_with($host, $hostSuffix)
            || ! preg_match('/^(SPO-[A-Za-z0-9]+)_\d+_\d+\.parquet$/i', $filename, $matches)) {
            throw new RuntimeException('The EEA API returned a URL outside the expected Parquet download format.');
        }

        return new DiscoveredFile($url, $filename, $matches[1]);
    }

    public function isParquetFile(string $path): bool
    {
        $size = filesize($path);

        if ($size === false || $size < 8) {
            return false;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            $header = fread($handle, 4);

            return fseek($handle, -4, SEEK_END) === 0
                && $header === 'PAR1'
                && fread($handle, 4) === 'PAR1';
        } finally {
            fclose($handle);
        }
    }
}
