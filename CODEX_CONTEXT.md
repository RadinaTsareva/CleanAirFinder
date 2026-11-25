# Codex Project Context — Clean Air Finder

> Use this file as the project handoff/context when working on Clean Air Finder.
> Treat the facts below as the current investigation record. Do not silently invent missing data or treat provisional assumptions as official standards.

## 1. Project goal

Build **Clean Air Finder**, a web application where a user selects a historical date range and receives the Bulgarian city with the cleanest air for that period, based on PM2.5 measurements.

The result should include:
- the average PM2.5 concentration;
- data coverage/completeness for the selected period;
- the date range and source context;
- an explanation of limitations or insufficient data.

The application must not imply nationwide coverage if the selected data source does not cover all Bulgarian cities.

## 2. Agreed technology direction

The current preferred stack is:

- **Backend:** Laravel / PHP
- **Frontend:** Laravel Blade (not React)
- **Database:** PostgreSQL
- **Queue/cache:** Redis
- **Local development/deployment:** Docker / Docker Compose
- **Ingestion:** Laravel scheduled jobs and queue workers
- **Data analysis during discovery:** Python + pandas + pyarrow (exploratory only)

Prefer a modular monolith. Do not introduce a separate Python microservice unless there is a demonstrated need. Python scripts were used to inspect Parquet files and generate discovery reports; the production ingestion pipeline should be implemented in the Laravel application unless a clear technical reason suggests otherwise.

## 3. Assignment constraints and product requirements

The assignment expects:
- a user-selectable historical from/to date range;
- a result identifying the Bulgarian city with the cleanest air;
- average air-quality information and context/trust indicators;
- real public data with terms that allow the intended use;
- local storage rather than proxying every user request to a third-party API;
- at least the most recent 12 months of queryable data;
- archival of data older than 36 months;
- fast queries;
- resilience to slow, unavailable, or malformed third-party data;
- one-command deployment;
- observability;
- README documentation of architecture, sources, assumptions, trade-offs, and future work;
- a deployed application.

The 36-month archive workflow and final station-selection/city-aggregation methodology have not yet been finalized.

## 4. EEA investigation — selected source

The primary source selected for the first historical version is the **European Environment Agency (EEA)**.

Useful endpoints:
- Download portal: https://eeadmz1-downloads-webapp.azurewebsites.net/
- API Swagger: https://eeadmz1-downloads-api-appservice.azurewebsites.net/swagger/index.html
- Swagger JSON: https://eeadmz1-downloads-api-appservice.azurewebsites.net/swagger/v1/swagger.json
- Metadata TSV downloaded from: https://ereporting.blob.core.windows.net/downloadservice/metadata.csv

The EEA API includes `/City`, `/ParquetFile/urls`, `/ParquetFile`, `/DownloadSummary`, `/Country`, `/Pollutant`, and other endpoints. There is no `/Dataset` endpoint in the inspected Swagger definition. `dataset` is an integer field in the Parquet download DTO; the Swagger schema does not expose a formal enum.

Dataset IDs established through investigation and testing:
- `1` = E2a
- `2` = E1a
- `3` = AirBase
- `4` = CAMS downscaled
- `5` = gap-filled E2a

Treat this mapping as empirically identified during this project, not as a formal enum documented by the Swagger schema.

For PM2.5:
- API request notation must be `"PM2.5"` (not `"6001"`).
- Parquet `Pollutant` value is `6001`.
- Unit in inspected files is `ug.m-3` (µg/m³).

### Why E1a was selected

E1a is the primary historical source because it provides validated historical station measurements from 2013 onward and exposes station-level Parquet files suitable for repeatable local ingestion and historical queries.

Other options:
- **E2a:** retained as a possible current-data extension, but it has narrower Bulgarian city coverage in the discovery run and hourly records that require careful quality and timezone handling.
- **OpenAQ:** not selected as primary because sufficiently complete and consistent historical PM2.5 coverage for the target Bulgarian cities was not established during initial evaluation. It could be reconsidered as a supplementary source after a separate coverage, terms, attribution, and deduplication review.
- **Google Maps Air Quality API:** not selected for the historical core because the historical window considered during evaluation was too short for the long-range historical requirement. Review current product documentation and storage terms before making any future claim or reuse decision.
- **AirBase:** covers the earlier 2002–2012 period and is not needed for the initial 2013-onward scope. Could be considered later to extend the history.
- **Gap-filled E2a:** excluded from the primary ranking because it contains imputed values. Do not present imputed observations as direct measurements.
- **CAMS downscaled:** excluded from the primary ranking because it is modelled data, methodologically different from station measurements. Could be considered as a separately labelled model-based fallback for cities without monitoring data, not blended silently into the E1a ranking.

These are project-specific engineering decisions, not claims that the alternatives are generally unsuitable. Recheck current API capabilities, terms, and coverage before future use.

## 5. Bulgarian city coverage discovered

The EEA `/City` endpoint returned 17 Bulgarian cities:
Varna, Pleven, Veliko Tarnovo, Sofia, Burgas, Ruse, Pazardzhik, Plovdiv, Stara Zagora, Blagoevgrad, Pernik, Vidin, Shumen, Vratsa, Haskovo, Dobrich, Sliven.

### E2a inventory

The `/ParquetFile/urls` discovery for PM2.5, dataset 1, returned four files across three cities:

| City | E2a sampling points |
|---|---:|
| Varna | 1 |
| Sofia | 2 |
| Ruse | 1 |
| Total | 4 files / 3 cities |

No E2a PM2.5 files were returned for the other 14 cities in that discovery run.

### E1a inventory

The `/ParquetFile/urls` discovery for PM2.5, dataset 2, returned eight files across seven cities:

| City | Sampling point ID |
|---|---|
| Varna | `SPO-BG0075A_06001_100` |
| Veliko Tarnovo | `SPO-BG0020A_06001_100` |
| Sofia | `SPO-BG0050A_06001_100` |
| Sofia | `SPO-BG0070A_06001_100` |
| Ruse | `SPO-BG0045A_06001_100` |
| Plovdiv | `SPO-BG0051A_06001_100` |
| Stara Zagora | `SPO-BG0039A_06001_100` |
| Pernik | `SPO-BG0046A_06001_100` |

No E1a PM2.5 files were returned for Pleven, Burgas, Pazardzhik, Blagoevgrad, Vidin, Shumen, Vratsa, Haskovo, Dobrich, or Sliven in that discovery run.

This is only an inventory for the selected dataset, pollutant, API query, and discovery date. It does not prove that those cities have no air-quality data of any kind. The app must not claim that all 17 cities are covered.

## 6. Downloaded local discovery files

The user downloaded these eight E1a Parquet files:

```text
eea_data/e1a/
  SPO-BG0075A_06001_100.parquet
  SPO-BG0020A_06001_100.parquet
  SPO-BG0050A_06001_100.parquet
  SPO-BG0070A_06001_100.parquet
  SPO-BG0045A_06001_100.parquet
  SPO-BG0051A_06001_100.parquet
  SPO-BG0039A_06001_100.parquet
  SPO-BG0046A_06001_100.parquet
```

Inventory/report files created:
```text
eea_inventory/
  eea_e1a_pm25_inventory.csv
  eea_e2a_pm25_inventory.csv
  metadata.csv
  station_metadata_bg_pm25.csv

eea_reports/
  sampling_points_summary.csv
  yearly_coverage.csv
```

Python scripts created during discovery include:
- `discover_eea_urls.py`
- `download_eea_data.py`
- `analyze_eea_data.py`
- `extract_station_metadata.py`
- `inspect_varna_metadata.py`
- `check_varna_point.py`

These are exploratory local artifacts, not necessarily production code.

## 7. E1a Parquet schema and observed data quality

The inspected Parquet schema contains:
`Samplingpoint`, `Pollutant`, `Start`, `End`, `Value`, `Unit`, `AggType`, `Validity`, `Verification`, `ResultTime`, `DataCapture`, `FkObservationLog`.

For all eight inspected E1a files:
- `AggType = day`
- `Unit = ug.m-3`
- `Pollutant = 6001`
- all inspected rows had `Validity = 1`
- all inspected rows had `Verification = 1`
- no duplicate daily date key was found in the initial individual-file inspection
- measurements span 2013-01-01 (or shortly after for some points) through 2025-12-31

Do not assume all future EEA files or E2a records have the same flag distribution.

### Coverage calculation

Historical period used: 2013-01-01 through 2025-12-31 inclusive = 4,748 calendar days.

Coverage formula:
`distinct calendar days with qualifying records / all calendar days in requested period * 100`

Always include calendar days with zero records in the denominator. A prior naive approach that grouped only dates with observations would overstate coverage; the current analysis correctly compares against the full calendar.

### 2013–2025 coverage

| City | Sampling point | Available days | Expected | Coverage |
|---|---|---:|---:|---:|
| Varna | BG0075A | 4,233 | 4,748 | 89.15% |
| Veliko Tarnovo | BG0020A | 4,232 | 4,748 | 89.13% |
| Sofia | BG0050A | 3,986 | 4,748 | 83.95% |
| Sofia | BG0070A | 3,602 | 4,748 | 75.86% |
| Ruse | BG0045A | 4,424 | 4,748 | 93.18% |
| Plovdiv | BG0051A | 4,304 | 4,748 | 90.65% |
| Stara Zagora | BG0039A | 4,712 | 4,748 | 99.24% |
| Pernik | BG0046A | 3,472 | 4,748 | 73.13% |

### 2025 coverage and means

| City | Sampling point | Available days | Expected | Coverage | Mean PM2.5 µg/m³ |
|---|---|---:|---:|---:|---:|
| Varna | BG0075A | 364 | 365 | 99.73% | 11.19 |
| Veliko Tarnovo | BG0020A | 342 | 365 | 93.70% | 16.26 |
| Sofia | BG0050A | 320 | 365 | 87.67% | 15.22 |
| Sofia | BG0070A | 349 | 365 | 95.62% | 10.39 |
| Ruse | BG0045A | 359 | 365 | 98.36% | 15.07 |
| Plovdiv | BG0051A | 357 | 365 | 97.81% | 15.25 |
| Stara Zagora | BG0039A | 360 | 365 | 98.63% | 19.26 |
| Pernik | BG0046A | 337 | 365 | 92.33% | 16.51 |

These are sampling-point means, not a final city ranking. Sofia has two stations with materially different 2025 means; do not simply select the lower one or average them without a defensible method.

## 8. Station metadata findings

The metadata file downloaded from EEA is tab-separated (TSV) despite its `.csv` extension. It includes fields such as:
`Countrycode`, `Timezone`, `AirQualityNetwork`, `AirQualityStation`, `AirQualityStationEoICode`, `AirQualityStationNatCode`, `SamplingPoint`, `AirPollutant`, `Longitude`, `Latitude`, `Altitude`, `MeasurementType`, `MeasurementMethod`, `AirQualityStationType`, `AirQualityStationArea`, and equipment/height/distance fields.

Filtering by `Countrycode = BG`, `AirPollutant = PM2.5`, and the eight sampling point IDs returned seven records:

| City | Station EoI code | Type | Area | Altitude m |
|---|---|---|---|---:|
| Veliko Tarnovo | BG0020A | background | suburban | 214 |
| Stara Zagora | BG0039A | background | urban | 223 |
| Ruse | BG0045A | background | urban | 41 |
| Pernik | BG0046A | background | suburban | 770 |
| Sofia | BG0050A | background | urban | 581 |
| Plovdiv | BG0051A | background | urban | 166 |
| Sofia | BG0070A | background | rural-nearcity | 1321 |
| Varna | BG0075A | not found | not found | not found |

All seven matched metadata rows have `Longitude = NaN` and `Latitude = NaN`. Their timezone field references `UTC+01`.

The Varna Parquet file was inspected directly:
- Parquet `Samplingpoint` value: `BG/SPO-BG0075A_06001_100`
- Pollutant: `6001`
- 4,233 rows
- dates 2013-01-01 through 2025-12-31
- daily PM2.5 data

A broad search for `0075` in the metadata TSV found no Bulgarian `BG0075A` record; matches were for other countries. Therefore, the Parquet measurements exist but the corresponding station metadata was not found in this export.

An attempt to access the EEA ArcGIS spatial service timed out. Do not invent coordinates or station type. Keep those fields nullable and record metadata provenance.

### Implication for Sofia

- `BG0050A` is classified `background / urban`.
- `BG0070A` is classified `background / rural-nearcity`.

The points may not represent the same urban environment. The 2025 mean difference (15.22 vs 10.39 µg/m³) reinforces the need to investigate station representativeness before city aggregation.

## 9. Timezone caution

EEA portal documentation inspected during discovery stated that hourly/variable date-times are converted to UTC+1, while daily values are in the country-reported timezone. Bulgaria observes daylight-saving changes, so do not assume that a fixed UTC+1 timestamp is equivalent to Bulgarian local civil time year-round.

Before converting E2a hourly records to daily records or combining E1a and E2a:
- verify the exact EEA timestamp convention;
- test daylight-saving transition dates;
- preserve source timestamps and timezone metadata;
- document the conversion rule.

Do not change the already reported daily E1a dates without evidence that a conversion is required.

## 10. Confirmed calculation and source decisions

The following decisions were confirmed for the first version:

- Use verified EEA E1a PM2.5 daily measurements as the ranking source.
- Include a measurement in calculations only when both `Validity = 1` and
  `Verification = 1`. Preserve both flags on every stored raw record.
- Require at least 75% coverage of the inclusive calendar-day period for a
  city result to qualify. This is a project rule, not an EEA standard. The
  precise application of this threshold to station-level versus city-level
  coverage depends on the final aggregation method below.
- Calculate coverage against every calendar day in the requested interval,
  including days with no qualifying measurements.
- Use the E1a daily date as provided by the source; do not apply a timezone
  conversion without evidence and a documented rule.
- Keep Varna in the calculations where its E1a measurements qualify. Its
  station metadata remains unknown; do not invent a station type or
  coordinates. Show that limitation with the result.
- Do not include Google Search results or claims from other websites as an
  air-quality data source or ranking input. The first version uses verified
  EEA measurements only.
- Retain source records and provenance even when a record does not qualify
  for a calculation, so results can be audited and recomputed.

The following remain open and must be settled before implementing a single
definitive city ranking:

1. How urban, suburban, rural-nearcity, and unclassified stations are treated
   in the primary comparison.
2. Whether to present parallel sensitivity views (for example urban-only,
   urban/suburban/unclassified, and all available points). These are proposed
   comparison scenarios, not yet an approved final ranking method.
3. How multiple stations in one city are combined, including station/day
   weighting and how city-level coverage is calculated.
4. How a city with no eligible station or less than 75% coverage is shown as
   unavailable.
5. Whether E2a is added for current dates and, if so, how its hourly records,
   timezone, and overlap with E1a are handled.
6. How to handle cities without E1a PM2.5 data without silently mixing
   measured and modelled data.

## 11. Proposed database direction (not yet implemented)

A possible initial schema:

- `cities`
- `air_quality_stations`
- `sampling_points`
- `pollutants`
- `data_sources`
- `measurements`
- `daily_station_aggregates`
- `daily_city_aggregates`
- `ingestion_runs` (and optionally ingestion error/event records)

Keep raw measurement provenance:
- source/dataset identifier;
- station and sampling-point identifiers;
- pollutant;
- start/end timestamps;
- value and unit;
- aggregation type;
- validity and verification flags;
- result time / source record identifier where useful;
- ingestion batch/run reference.

Do not discard raw records after computing aggregates. Aggregates should be reproducible from retained raw data and the documented calculation rules.

Potential uniqueness key for daily station data must be validated against the actual source semantics before a migration is written; do not assume a key without checking duplicate behaviour across updates/datasets.

## 12. Architecture direction

Preferred direction:
- Laravel modular monolith;
- Blade UI;
- PostgreSQL for raw measurements and precomputed aggregates;
- Redis for queues/cache;
- Laravel scheduler dispatches ingestion jobs;
- queued ingestion downloads source files, validates, normalizes, deduplicates, and stores records;
- aggregate jobs update station/day and city/day summaries;
- API/provider abstraction (e.g. `AirQualityProvider`) isolates EEA-specific retrieval from domain logic;
- archive raw data older than 36 months to S3-compatible object storage, while keeping the required recent queryable history readily available;
- fast user queries read precomputed aggregates where possible.

Do not build a second microservice unless justified. Do not fetch third-party data on every user query.

## 13. Immediate next steps

Recommended sequence:

1. Confirm this context against the actual repository structure and assignment document.
2. Add the source-analysis document to the repository as `DATA_SOURCE_ANALYSIS.md`.
3. Resolve or explicitly record Varna's missing station metadata and the E2a timezone questions.
4. Agree the station-selection scenarios and city aggregation method. The E1a quality flags and 75% period-coverage threshold are already confirmed.
5. Design and review PostgreSQL migrations.
6. Implement EEA ingestion with retry/backoff, timeouts, validation, idempotency, logging, and source provenance.
7. Implement aggregation and historical query services.
8. Add tests for missing days, leap years, duplicate ingestion, invalid records, station selection, timezone boundaries, and E1a/E2a overlap.
9. Build the Blade UI and show coverage/limitations with every result.
10. Add Docker Compose, observability, README, and one-command setup/deployment.

## 14. Instructions for Codex

When working on this project:

- First inspect the repository and existing code before proposing or changing architecture.
- Read the assignment document and `DATA_SOURCE_ANALYSIS.md` if present in the repository.
- Do not overwrite or delete the user's exploratory scripts or downloaded source files.
- Do not assume that all 17 cities have data; the current E1a inventory covers seven cities only.
- Do not invent missing coordinates, station metadata, API responses, or source coverage.
- Do not call sampling-point averages "city averages" until the city aggregation method is agreed.
- Do not mix E1a, E2a, AirBase, gap-filled E2a, and CAMS values without explicit source-specific rules and provenance.
- Treat the 75% coverage threshold and the `Validity = 1` plus
  `Verification = 1` filters as confirmed project rules, not EEA standards.
- Treat station selection, multiple-station weighting, and city-level
  aggregation as unresolved until explicitly agreed.
- Do not use Google Search results or third-party web claims as ranking data;
  use verified EEA measurements for the first version.
- Prefer Laravel/PHP for production ingestion and application logic; Python was used for exploratory data inspection.
- Explain significant architectural choices and trade-offs so they can be defended in a technical interview.
- Make changes incrementally, run tests, and report exactly which files were changed and which commands/tests were run.
- If a critical station-selection or aggregation decision is unresolved, ask
  before hard-coding it.

## 15. Suggested first prompt to Codex

Read `CODEX_CONTEXT.md`, the assignment brief, and `DATA_SOURCE_ANALYSIS.md` if available. Then inspect the existing repository and report:

1. what already exists;
2. what is missing relative to the assignment;
3. any conflicts between the current code and this project context;
4. a proposed implementation plan in small milestones.

Do not implement anything yet. Do not make assumptions about the unresolved ranking methodology, station weighting, coverage thresholds, or timezone conversions. Wait for approval before changing files.
