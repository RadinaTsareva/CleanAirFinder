# CleanAirFinder

Laravel application for comparing historical PM2.5 measurements across Bulgarian cities. The initial data source is the EEA E1a dataset.

## EEA source ingestion

The EEA API client discovers Parquet file URLs, downloads new files, validates the Parquet file signature, verifies the archived checksum, and records source provenance. Files are stored privately under `storage/app/private/eea/e1a/`. This initial ingestion milestone archives source files; it does not yet parse measurement rows into the `measurements` table or calculate city rankings.

Run a manual sync:

```sh
php artisan air-quality:sync-eea-e1a
```

The scheduler dispatches the sync job monthly on the first day at 03:00 (application timezone). In a local environment, run both the scheduler and queue worker:

```sh
php artisan schedule:work
php artisan queue:work
```

Configure `EEA_BASE_URL` and `EEA_DOWNLOAD_HOST_SUFFIX` in `.env` when needed. The E1a external dataset identifier is stored in the seeded dataset reference data.
