# Clean Air Finder --- Data Source Analysis

**Status:** Discovery and initial validation\
**Last updated:** 25 November 2026\
**Primary source:** European Environment Agency (EEA) Air Quality data\
**Initial pollutant:** PM2.5\
**Initial geography:** Selected Bulgarian cities with available EEA E1a
records

------------------------------------------------------------------------

## 1. Purpose

This document records the data-source investigation for Clean Air
Finder. It describes the datasets inspected, the current availability of
PM2.5 measurements for Bulgarian cities, the metadata findings, and the
assumptions that still need validation before implementing the
city-ranking algorithm.

The goal is to make historical date-range queries reproducible and
transparent. The application should report a city-level result only when
the available measurements meet clearly documented completeness and
quality rules.

## 2. Data source

The selected source is the **European Environment Agency (EEA)**
air-quality data download service.

-   EEA download portal:
    <https://eeadmz1-downloads-webapp.azurewebsites.net/>
-   EEA download API Swagger:
    <https://eeadmz1-downloads-api-appservice.azurewebsites.net/swagger/index.html>
-   EEA metadata CSV used during discovery:
    `https://ereporting.blob.core.windows.net/downloadservice/metadata.csv`

The metadata file downloaded during this investigation is tab-separated
(TSV), despite its `.csv` extension. It contains fields including
country, timezone, network, station, sampling point, pollutant,
coordinates, altitude, measurement type, measurement method, station
type, and station area.

### 2.1 Datasets considered

  ------------------------------------------------------------------------
  Dataset             Dataset ID used in Role in this     Notes
                                     API project          
  ---------------- --------------------- ---------------- ----------------
  E2a                                  1 Current /        Data is
                                         up-to-date       continuously
                                         measurements     transmitted and
                                                          is not
                                                          equivalent to
                                                          the validated
                                                          historical
                                                          dataset.

  E1a                                  2 Historical       Used for the
                                         validated        initial
                                         measurements     historical
                                                          analysis,
                                                          covering
                                                          2013--2025 in
                                                          the downloaded
                                                          files.
  ------------------------------------------------------------------------

The integer-to-dataset mapping above was established during API
investigation and testing. The Swagger schema exposes `dataset` as an
integer but does not provide a formal enum in the inspected definition.
Treat the mapping as empirically verified during this project, not as a
self-describing API contract.

E1a and E2a must not be silently mixed. If the application later
combines them to extend the latest date range, the source and
verification provenance must remain available, and the transition must
be documented.

### 2.2 Pollutant

The initial analysis is limited to **PM2.5**.

-   API pollutant notation: `PM2.5`
-   Pollutant code found in Parquet records: `6001`
-   Unit in inspected records: `ug.m-3` (µg/m³)

The API download request expects the pollutant notation `PM2.5`, not the
numeric code `6001`. The Parquet data itself stores `6001` in the
`Pollutant` column.

Do not combine PM2.5 with PM10, NO2, or other pollutants in a single
concentration average or ranking.

## 3. Discovery of Bulgarian city coverage

The EEA `/City` endpoint returned the following 17 Bulgarian cities
during discovery:

Varna, Pleven, Veliko Tarnovo, Sofia, Burgas, Ruse, Pazardzhik, Plovdiv,
Stara Zagora, Blagoevgrad, Pernik, Vidin, Shumen, Vratsa, Haskovo,
Dobrich, and Sliven.

The `/ParquetFile/urls` endpoint was queried separately for each city
with pollutant `PM2.5`.

### 3.1 E2a inventory

The E2a URL discovery returned four files across three cities:

  City             E2a sampling points
  ----------- ------------------------
  Varna                              1
  Sofia                              2
  Ruse                               1
  **Total**     **4 files / 3 cities**

No E2a PM2.5 files were returned for the other 14 cities in this
discovery run.

This is an inventory result, not proof that those cities have no
air-quality data of any kind. It is limited to the selected E2a dataset,
PM2.5 pollutant, API response, and discovery date.

### 3.2 E1a inventory

The E1a URL discovery returned eight files across seven cities:

  City             Sampling point ID                                           Number of points
  ---------------- -------------------------------------------------- -------------------------
  Varna            `SPO-BG0075A_06001_100`                                                    1
  Veliko Tarnovo   `SPO-BG0020A_06001_100`                                                    1
  Sofia            `SPO-BG0050A_06001_100`, `SPO-BG0070A_06001_100`                           2
  Ruse             `SPO-BG0045A_06001_100`                                                    1
  Plovdiv          `SPO-BG0051A_06001_100`                                                    1
  Stara Zagora     `SPO-BG0039A_06001_100`                                                    1
  Pernik           `SPO-BG0046A_06001_100`                                                    1
  **Total**                                                             **8 points / 7 cities**

No E1a PM2.5 files were returned for Pleven, Burgas, Pazardzhik,
Blagoevgrad, Vidin, Shumen, Vratsa, Haskovo, Dobrich, or Sliven in this
discovery run.

The application must not claim that all 17 cities are covered. Cities
without an eligible dataset should be shown as unavailable for the
selected period, or excluded from the comparison with an explicit
explanation.

## 4. E1a Parquet data structure

The eight downloaded files are daily Parquet datasets, one file per
sampling point. The inspected schema contains:

-   `Samplingpoint`
-   `Pollutant`
-   `Start`
-   `End`
-   `Value`
-   `Unit`
-   `AggType`
-   `Validity`
-   `Verification`
-   `ResultTime`
-   `DataCapture`
-   `FkObservationLog`

For the eight inspected files:

-   `AggType` is `day`.
-   `Unit` is `ug.m-3`.
-   `Pollutant` is `6001`.
-   All inspected rows have `Validity = 1`.
-   All inspected rows have `Verification = 1`.
-   No duplicate date key was found in the initial inspection of the
    individual files.
-   The date range is from 2013-01-01 (or shortly after for some points)
    through 2025-12-31.

These observations describe the downloaded files only. They should not
be generalized to every EEA file or future ingestion batch.

## 5. Initial coverage analysis

The initial analysis used the period **2013-01-01 through 2025-12-31**,
inclusive. This contains 4,748 calendar days.

Coverage is calculated as:

`number of calendar days with at least one qualifying daily record / number of calendar days in the requested period × 100`

### 5.1 Coverage over 2013--2025

  City             Sampling point     Available days   Expected days   Coverage
  ---------------- ---------------- ---------------- --------------- ----------
  Varna            `BG0075A`                   4,233           4,748     89.15%
  Veliko Tarnovo   `BG0020A`                   4,232           4,748     89.13%
  Sofia            `BG0050A`                   3,986           4,748     83.95%
  Sofia            `BG0070A`                   3,602           4,748     75.86%
  Ruse             `BG0045A`                   4,424           4,748     93.18%
  Plovdiv          `BG0051A`                   4,304           4,748     90.65%
  Stara Zagora     `BG0039A`                   4,712           4,748     99.24%
  Pernik           `BG0046A`                   3,472           4,748     73.13%

### 5.2 Coverage over 2025

  -------------------------------------------------------------------------
  City      Sampling       Available     Expected     Coverage   Mean PM2.5
            point               days         days                   (µg/m³)
  --------- ----------- ------------ ------------ ------------ ------------
  Varna     `BG0075A`            364          365       99.73%        11.19

  Veliko    `BG0020A`            342          365       93.70%        16.26
  Tarnovo                                                      

  Sofia     `BG0050A`            320          365       87.67%        15.22

  Sofia     `BG0070A`            349          365       95.62%        10.39

  Ruse      `BG0045A`            359          365       98.36%        15.07

  Plovdiv   `BG0051A`            357          365       97.81%        15.25

  Stara     `BG0039A`            360          365       98.63%        19.26
  Zagora                                                       

  Pernik    `BG0046A`            337          365       92.33%        16.51
  -------------------------------------------------------------------------

The 2025 results indicate that all eight sampling points have
comparatively high daily coverage for that calendar year. However, this
alone does not establish that each point is representative of its entire
city.

The mean values above are sampling-point means, not yet validated
city-level means or a final city ranking.

## 6. Station metadata findings

The downloaded metadata file was read as TSV and filtered by:

-   `Countrycode = BG`
-   `AirPollutant = PM2.5`
-   the eight known sampling-point IDs

Seven of the eight points matched metadata rows.

  ----------------------------------------------------------------------------------------------
  City        Station EoI Sampling point            Station type Station area       Altitude (m)
              code                                                                
  ----------- ----------- ------------------------- ------------ ---------------- --------------
  Veliko      `BG0020A`   `SPO-BG0020A_06001_100`   background   suburban                    214
  Tarnovo                                                                         

  Stara       `BG0039A`   `SPO-BG0039A_06001_100`   background   urban                       223
  Zagora                                                                          

  Ruse        `BG0045A`   `SPO-BG0045A_06001_100`   background   urban                        41

  Pernik      `BG0046A`   `SPO-BG0046A_06001_100`   background   suburban                    770

  Sofia       `BG0050A`   `SPO-BG0050A_06001_100`   background   urban                       581

  Plovdiv     `BG0051A`   `SPO-BG0051A_06001_100`   background   urban                       166

  Sofia       `BG0070A`   `SPO-BG0070A_06001_100`   background   rural-nearcity             1321

  Varna       `BG0075A`   `SPO-BG0075A_06001_100`   **not        **not found**     **not found**
                                                    found**                       
  ----------------------------------------------------------------------------------------------

All seven matched metadata rows have missing (`NaN`) longitude and
latitude values in this metadata export. The `Timezone` field in these
rows points to `UTC+01`.

The `BG0075A` station was not found in the metadata export, including a
broader search across Bulgarian rows. Its Parquet file itself is present
and contains 4,233 daily records. Therefore, the missing metadata must
not be interpreted as missing measurement data.

An attempt to access the EEA ArcGIS spatial service timed out.
Coordinates and the missing Varna station metadata remain unresolved.

### 6.1 Implications for city aggregation

Sofia has two sampling points with different reported station areas:

-   `BG0050A`: urban background
-   `BG0070A`: rural-nearcity background

Their 2025 means differ (15.22 vs 10.39 µg/m³). They should not
automatically be treated as equivalent observations of the same urban
environment.

The other matched points include both urban and suburban
classifications. The current metadata does not support treating every
point as an interchangeable representation of a city.

Before finalizing city-level aggregation, investigate whether
station-area classifications should affect eligibility or weighting. Do
not silently discard a point or assign an invented weight.

## 7. Provisional data-quality and calculation rules

The following are **project assumptions for evaluation**, not official
EEA requirements:

1.  Use E1a as the primary source for historical validated measurements.
2.  Initially include only PM2.5.
3.  Preserve the original measurement value, unit, dates, validity,
    verification, and source provenance.
4.  For the inspected E1a files, `Validity = 1` is the observed value on
    every row. Keep validity as an explicit filter in the
    ingestion/aggregation logic, but do not assume all future files have
    the same distribution.
5.  Calculate coverage against every calendar day in the requested
    period, including days with zero measurements. Do not calculate
    coverage only over dates present in the data.
6.  Do not treat a sampling-point mean as a city mean until a defensible
    station-selection and aggregation method has been documented.
7.  Do not mix E1a and E2a records without preserving dataset provenance
    and defining overlap/deduplication rules.
8.  Do not invent coordinates or missing station metadata. Store
    unavailable metadata as nullable and record its provenance.

A minimum coverage threshold for user queries has not yet been
finalized. Candidate thresholds such as 75% may be tested, but should
not be described as an EEA standard.

## 8. Known limitations and risks

-   Only 7 of the 17 discovered Bulgarian cities returned E1a PM2.5
    files in this API inventory run.
-   Only 3 cities returned E2a PM2.5 files in the corresponding
    discovery run.
-   The metadata export matched 7 of 8 E1a sampling points.
-   Coordinates were missing for all seven matched points in the
    downloaded metadata file.
-   The EEA spatial-service request timed out during this investigation.
-   Station representativeness differs: urban, suburban, and
    rural-nearcity classifications are present.
-   Sofia has two sampling points with materially different 2025 means.
-   EEA hourly timestamps and daily aggregation timezone conventions
    require further validation before E2a hourly records are converted
    to daily records or combined with E1a.
-   A successful URL discovery does not guarantee that every requested
    date has complete observations.
-   The initial report uses the records and metadata available on 25
    November 2026; availability may change as EEA publishes or updates
    datasets.

## 9. Proposed application behaviour

For a selected city and date range, the application should:

1.  Query locally stored daily PM2.5 measurements.
2.  Determine the expected calendar days in the requested range.
3.  Calculate coverage using the full calendar-day denominator.
4.  Apply the documented validity and eligibility rules.
5.  Calculate the average only over eligible observations.
6.  Return the result with its coverage and source context.
7.  Explain when the city is unavailable or does not meet the chosen
    completeness threshold.
8.  Avoid presenting a ranking as definitive when station metadata or
    city representativeness is insufficient.

The UI should distinguish: - measured concentration (µg/m³); - data
coverage (%); - date range; - number of contributing sampling points; -
source dataset (E1a/E2a); - relevant limitations or missing metadata.

## 10. Next investigation tasks

-   [ ] Find a reliable EEA source for station coordinates and resolve
    the missing Varna metadata.
-   [ ] Validate the EEA timezone conventions for daily E1a and hourly
    E2a records, including daylight-saving transitions.
-   [ ] Inspect station metadata and measurement history for possible
    station changes or discontinuities.
-   [ ] Decide how to handle urban, suburban, and rural-nearcity points
    in city comparisons.
-   [ ] Test candidate completeness thresholds against several date
    ranges.
-   [ ] Decide whether the product should compare only cities with
    sufficient E1a coverage or use a clearly disclosed fallback source
    for cities without E1a.
-   [ ] Document the final city aggregation method before implementing
    it.

------------------------------------------------------------------------

## Appendix A --- Local investigation artifacts

The following local files were created during discovery:

``` text
eea_inventory/
  eea_e1a_pm25_inventory.csv
  eea_e2a_pm25_inventory.csv
  metadata.csv
  station_metadata_bg_pm25.csv

eea_data/
  e1a/
    SPO-BG0075A_06001_100.parquet
    SPO-BG0020A_06001_100.parquet
    SPO-BG0050A_06001_100.parquet
    SPO-BG0070A_06001_100.parquet
    SPO-BG0045A_06001_100.parquet
    SPO-BG0051A_06001_100.parquet
    SPO-BG0039A_06001_100.parquet
    SPO-BG0046A_06001_100.parquet

eea_reports/
  sampling_points_summary.csv
  yearly_coverage.csv
```

The local investigation artifacts are exploratory inputs. The production
application should implement repeatable ingestion, validation,
provenance tracking, and aggregation rather than depending on manually
downloaded files.

## 11. Alternative APIs and datasets considered

The source selection was based on the assignment's practical requirements:

- historical date-range queries, not only current conditions;
- enough history to retain and query at least 12 months of data;
- data relevant to Bulgarian cities;
- permission to store data locally and build a repeatable ingestion process;
- traceable measurements and source metadata;
- predictable behaviour when a provider is unavailable or returns incomplete data.

The alternatives below were considered during the initial source investigation. The decision is specific to this project and does not mean that the alternatives are generally unsuitable.

### 11.1 OpenAQ API

**Decision: not selected as the primary source.**

OpenAQ provides a useful unified interface to air-quality measurements from multiple providers. However, during the initial evaluation, its coverage could not be established as sufficiently complete and consistent for the Bulgarian city set required by this assignment.

The project needs to know not only whether an API returns measurements, but also whether it provides a dependable historical series for the selected pollutant, the relevant cities, and the required date ranges. The EEA source is closer to the original reporting system for Bulgarian monitoring data and exposes the E1a/E2a datasets and station-level Parquet files used in this investigation.

OpenAQ could be reconsidered as a discovery or supplementary source, but it should not silently fill gaps in the EEA series. Any future use would require a separate coverage audit, source attribution, licence/terms review, and deduplication strategy.

### 11.2 Google Maps Platform — Air Quality API

**Decision: not selected as the primary historical data source.**

The Google Maps Platform Air Quality API was considered, but its historical-data capability was not suitable for the assignment's long-range historical query requirement. The historical window discussed during evaluation was limited to approximately 30 days, which is insufficient for a product expected to query and retain at least 12 months of historical data.

The API is oriented toward providing air-quality information for locations and times, rather than serving as the project's long-term, locally stored station-measurement archive. The applicable storage, caching, and reuse terms would also need careful review before using it as the basis of a persistent historical database.

It may be useful for a separate current-conditions feature, but it was not chosen for the core historical ranking.

### 11.3 EEA E2a — up-to-date measurements

**Decision: not used as the primary historical dataset; retained as a possible source for current-period updates.**

E2a is the EEA's up-to-date dataset. During discovery, the API returned four PM2.5 files for three Bulgarian cities: Varna, Sofia, and Ruse. This was narrower city coverage than the eight E1a files found for seven cities.

The inspected E2a files for the two Sofia sampling points contained hourly records and a mixture of validity and verification flag values. The records also require careful handling of incomplete days and timezone conversion before they can be compared with E1a daily aggregates.

E2a is therefore not substituted for E1a in the historical analysis. It may later be ingested for the most recent period, provided that the application preserves dataset provenance, applies documented quality rules, handles hourly-to-daily aggregation consistently, and defines how overlapping E1a/E2a periods are reconciled.

### 11.4 EEA AirBase — historical data

**Decision: not selected for the initial implementation.**

AirBase covers an earlier historical period (2002–2012), whereas the initial E1a files inspected for this project cover 2013–2025. The assignment's immediate goal can be addressed with the more recent E1a series, so adding AirBase would introduce another dataset and another transition between reporting systems without being necessary for the first implementation.

AirBase could be considered if the product later needs to extend its historical range before 2013. Such an extension would require validation of pollutant definitions, station identifiers, units, aggregation conventions, and continuity across the AirBase/E1a boundary.

### 11.5 EEA gap-filled E2a

**Decision: excluded from the primary ranking.**

The gap-filled E2a dataset includes hourly values that have been imputed for missing observations using information from similar stations. This can be useful for analyses that require more temporally complete series, but imputed values are not the same as direct measurements from the target sampling point.

The first version of Clean Air Finder should make its result explainable in terms of observed EEA measurements. Including imputed values in the primary ranking could make the coverage appear better while obscuring which values were measured and which were estimated.

It may be useful in a separate analytical view in the future, provided that imputed observations are clearly labelled and never presented as direct measurements.

### 11.6 EEA CAMS downscaled data

**Decision: excluded from the primary ranking.**

The EEA CAMS downscaled product is modelled air-quality data at approximately 1 km resolution, rather than the same kind of station-reported measurement series used in E1a.

Modelled data can provide spatial coverage where monitoring stations are absent, which could be valuable for cities without E1a files. However, combining model estimates with measured station values in one ranking would mix different data-generation methods and could make comparisons misleading.

CAMS could be evaluated later as a clearly separate fallback or model-based view for cities without sufficient monitoring data. It should not be blended into the E1a measurement ranking without explicit labelling and a documented methodology.

### 11.7 Why EEA E1a was selected for the first version

E1a was selected as the primary historical source because it best matches the core use case investigated so far:

- it provides validated historical measurements from 2013 onward;
- the EEA API can return station-level Parquet files;
- the inspected records contain daily PM2.5 values with validity and verification fields;
- the downloaded files provide a reproducible basis for local storage and historical queries;
- the source is the official European reporting environment relevant to Bulgarian monitoring stations.

This selection has an important limitation: the current E1a inventory contains PM2.5 files for only seven of the 17 Bulgarian cities returned by the EEA city endpoint. The application must expose this coverage limitation rather than implying nationwide completeness.

### 11.8 Summary of source decisions

| Option | Decision | Main reason |
|---|---|---|
| EEA E1a | **Selected for historical core** | Validated historical station measurements; suitable date range and local ingestion workflow |
| EEA E2a | Possible future extension | Current/up-to-date data, but narrower observed city coverage and hourly quality/timezone handling still required |
| OpenAQ | Not primary | Bulgarian city/pollutant historical coverage was not established as sufficiently complete during initial evaluation |
| Google Maps Air Quality API | Not primary | Historical window discussed during evaluation was too short for the assignment's long-term history requirement; persistent storage terms need review |
| EEA AirBase | Not in initial version | Earlier period (2002–2012); not needed for the initial 2013-onward scope |
| EEA gap-filled E2a | Excluded from main ranking | Includes imputed values, which must not be confused with direct measurements |
| EEA CAMS downscaled | Excluded from main ranking | Modelled estimates are methodologically different from station measurements |

These decisions are provisional engineering decisions based on the assignment requirements and the source investigation performed so far. They should be revisited if the required city coverage, historical range, or product scope changes.
