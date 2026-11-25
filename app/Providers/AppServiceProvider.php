<?php

namespace App\Providers;

use App\Providers\AirQuality\Contracts\EeaAirQualityProvider;
use App\Providers\AirQuality\Eea\EeaDownloadClient;
use App\Repositories\Contracts\EeaIngestionRepository;
use App\Repositories\Eloquent\EloquentEeaIngestionRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EeaAirQualityProvider::class, EeaDownloadClient::class);
        $this->app->bind(EeaIngestionRepository::class, EloquentEeaIngestionRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
