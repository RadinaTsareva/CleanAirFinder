<?php

namespace Tests\Unit;

use App\Providers\AirQuality\Eea\EeaDownloadClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EeaDownloadClientTest extends TestCase
{
    public function test_it_extracts_parquet_urls_from_the_eea_response(): void
    {
        config(['services.eea.base_url' => 'https://eea.test']);
        Http::fake([
            'eea.test/ParquetFile/urls' => Http::response(
                "\u{FEFF}ParquetFileUrl\nhttps://airquality-p-e1a.blob.core.windows.net/files/SPO-BG0075A_06001_100.parquet\n",
            ),
        ]);

        $urls = app(EeaDownloadClient::class)->discoverParquetUrls('Varna', 2);

        $this->assertSame([
            'https://airquality-p-e1a.blob.core.windows.net/files/SPO-BG0075A_06001_100.parquet',
        ], $urls);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://eea.test/ParquetFile/urls'
            && $request['cities'] === ['Varna']
            && $request['dataset'] === 2);
    }
}
