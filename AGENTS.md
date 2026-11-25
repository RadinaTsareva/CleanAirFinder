# AGENTS.md --- Clean Air Finder

> Persistent project instructions for Codex and other coding agents.
>
> Related project documents: - `CODEX_CONTEXT.md` --- project goal,
> requirements, EEA investigation and current findings. -
> `DATA_SOURCE_ANALYSIS.md` --- source selection, coverage inventory,
> metadata findings and data limitations. - `TECHNOLOGY_DECISIONS.md`
> --- technology choices and architecture rationale.
>
> These documents are the project knowledge base. Read the relevant
> sections before implementing related features. If the repository's
> actual state conflicts with an assumption in these documents, inspect
> the code and configuration, explain the discrepancy, and ask before
> making a significant architectural change.

------------------------------------------------------------------------

## 1. Project overview

**Clean Air Finder** is a Laravel web application that helps a user
identify which Bulgarian city had the cleanest air over a selected
historical date range.

The initial pollutant is **PM2.5**. Results should include: - the city
selected as having the cleanest air for the requested period; - average
PM2.5 concentration in µg/m³; - data coverage/completeness; - the
requested date range; - source and methodology context; - a clear
explanation when data is insufficient or a result cannot be trusted.

The application must not imply that all Bulgarian cities are covered
when the source inventory only supports a subset.

### Core assignment constraints

-   Use real public data and respect the source's terms.
-   Ingest and persist source data locally. Do not call the EEA API for
    every user-facing query.
-   Keep at least the most recent 12 months queryable.
-   Archive raw data older than 36 months.
-   Support fast historical queries.
-   Handle slow, unavailable, incomplete or malformed source data.
-   Provide reproducible local setup and deployment.
-   Include tests, observability, and clear project documentation.

## 2. Technology direction

The current preferred baseline is:

  -----------------------------------------------------------------------
  Area                    Technology              Responsibility
  ----------------------- ----------------------- -----------------------
  Backend                 PHP / Laravel           Application, ingestion,
                                                  business rules and HTTP

  UI                      Laravel Blade           Server-rendered user
                                                  interface

  Database                PostgreSQL              Application data,
                                                  measurements and
                                                  queryable aggregates

  Queue/cache             Redis                   Background job queue
                                                  and cache

  Local environment       Docker Compose          Reproducible
                                                  development and
                                                  one-command startup

  External data           EEA Air Quality         Source of PM2.5
                          download service        measurements and
                                                  metadata

  Archive                 AWS S3 (proposed)       Long-term storage of
                                                  older raw Parquet data

  Exploration             Python, pandas, pyarrow Discovery and one-off
                                                  analysis only; not a
                                                  production microservice
  -----------------------------------------------------------------------

Treat this as the agreed direction, not permission to introduce
additional infrastructure. AWS production hosting beyond the proposed S3
archive has not yet been selected. Local S3-compatible storage (for
example MinIO) is a proposal, not a confirmed requirement.

Do not introduce React, a separate Python service, Kafka, Kubernetes,
Snowflake, Elasticsearch or another database unless a concrete
requirement demonstrates that the current architecture cannot reasonably
satisfy the need. Explain the trade-off and ask before adding a major
dependency.

## 3. Architectural principles

Use a **modular monolith** with explicit boundaries and dependency
direction.

### Required request flow

``` text
HTTP Request
    |
    v
Route
    |
    v
Controller
    |
    +--> Form Request (input validation)
    |
    v
Application Service / Action
    |
    +--> Domain rules / calculations
    |
    v
Repository Interface
    |
    v
Repository Implementation
    |
    v
Eloquent Model / PostgreSQL
```

For ingestion:

``` text
Laravel Scheduler
    |
    v
Queued Job
    |
    v
Ingestion Service
    |
    +--> EEA Provider / HTTP client
    +--> Parser / Normalizer
    +--> Validation and deduplication rules
    |
    v
Repository
    |
    v
PostgreSQL
```

For archiving:

``` text
Scheduled Archive Job
    |
    v
Archive Service
    |
    +--> Read eligible raw records/files through repository
    +--> Write archive object to S3
    +--> Verify uploaded object
    +--> Mark archive state / apply approved retention action
```

### Dependency rules

-   Controllers depend on application services/actions, not on Eloquent
    models or repositories directly.
-   Services depend on repository interfaces, provider interfaces, and
    other services through constructor injection.
-   Repository implementations own persistence and database query
    construction.
-   External providers are isolated behind provider/client abstractions.
-   Jobs orchestrate background execution and delegate real work to
    services.
-   Blade templates display prepared data. They do not query the
    database or calculate business results.
-   Avoid circular dependencies.
-   Keep dependencies flowing inward: HTTP, queue and provider details
    must not leak into domain/business rules.

## 4. Mandatory coding guidelines

These rules apply to all new and modified code.

### 4.1 Controllers must remain thin

Controllers are responsible only for HTTP concerns: - receive the
request; - rely on a Form Request for validation where appropriate; -
call one application service/action; - return a view, redirect or
response.

Controllers must **not**: - contain business logic, calculations,
ranking rules or data-quality decisions; - contain SQL, query-builder
calls, `DB::table()`, `Model::query()`, `where()`, `get()`, `first()` or
other database access; - call Eloquent models to retrieve or persist
records; - make HTTP calls to EEA or other external services; - dispatch
a sequence of low-level operations that belongs in a service; -
implement parsing, normalization, deduplication or archive logic.

A controller may map a service result to an HTTP response. Keep that
mapping simple.

### 4.2 All database queries belong in Repository classes

**This is a strict project rule: every application database query must
be implemented in a Repository class.**

This includes: - Eloquent reads (`get`, `first`, `find`, `exists`,
`count`, aggregates); - Eloquent writes (`create`, `update`, `delete`,
`upsert`); - query-builder operations; - raw SQL; - joins, filters,
grouping, sorting and pagination; - transaction-related persistence
operations.

Do not put database queries in: - controllers; - services/actions; -
jobs; - commands; - Blade views or view composers; - Form Requests or
validation rules; - model accessors, observers or event listeners.

Those components may call a repository or a service that uses a
repository. They must not build their own application queries.

**Allowed exceptions:** - Laravel migrations and schema definitions; -
database seeders/factories when setting up test or development data; -
repository implementations themselves; - narrowly justified framework
infrastructure where Laravel requires direct database configuration.

If a validation rule needs to check whether a record exists, delegate
that check to a repository-backed validation service/rule. Do not add a
database query directly to the Form Request.

### 4.3 Repository design

-   Create a dedicated Repository class for each meaningful persistence
    boundary/aggregate, not one giant repository for the entire
    application.
-   Repositories encapsulate query construction and persistence details.
-   Services must not know whether data is retrieved with Eloquent, the
    query builder or raw SQL.
-   Use explicit, intention-revealing method names, for example:
    -   `findById()`
    -   `findBySamplingPointAndDate()`
    -   `getDailyMeasurementsForPeriod()`
    -   `getCityAggregatesForPeriod()`
    -   `upsertMeasurements()`
-   Return well-defined types: model, collection, DTO, paginator, scalar
    or nullable result as appropriate.
-   Avoid returning unstructured arrays when a DTO or typed result
    object makes the contract clearer.
-   Do not expose Eloquent query builders to services/controllers.
    Return results or provide purpose-specific repository methods.
-   Keep query optimizations (eager loading, indexes, grouping, SQL
    expressions) inside the repository.
-   Avoid N+1 queries. Use eager loading or purpose-built aggregate
    queries.
-   Use pagination for potentially large result sets.
-   Use chunking/cursors for large ingestion and archive operations; do
    not load an unbounded dataset into memory.
-   Use transactions for multi-step writes that must be atomic.
    Transaction orchestration may live in a service, but all actual
    database queries remain in repositories.
-   Prefer bulk inserts/upserts for ingestion when appropriate, with
    explicit deduplication keys and tested behavior.

#### Repository interfaces and dependency injection

-   Define repository interfaces when they provide a useful abstraction
    for application services, testing, or alternative implementations.
-   Bind interfaces to implementations in a dedicated service provider
    or the existing application provider.
-   Application services should depend on interfaces, not concrete
    Eloquent repository implementations.
-   Do not create empty interfaces mechanically if they add no value;
    however, for core persistence boundaries used by business services,
    prefer an interface.
-   Do not instantiate repositories manually inside services. Use
    constructor dependency injection.

### 4.4 Business logic belongs in Services / Actions

Services/actions own business behavior and orchestration, including: -
deciding which measurements qualify for a calculation; - calculating
averages and coverage; - comparing cities and determining the cleanest
city; - applying ranking and tie-breaking rules; - deciding whether data
is sufficient for a trustworthy result; - coordinating ingestion,
normalization and deduplication; - coordinating archive and retention
workflows.

Rules: - Keep each service focused on one use case or cohesive
responsibility. - Prefer small, intention-revealing methods. - Do not
mix HTTP response formatting with business logic. - Do not put
SQL/query-builder code in services. Ask repositories for the required
data. - Do not duplicate business rules across controllers, jobs and
commands. - Extract reusable domain rules into dedicated classes/value
objects when that improves clarity. - Do not finalize ranking
thresholds, station weighting, timezone rules or data-quality rules
unless they are documented/approved. Treat unresolved methodology as an
explicit open decision.

### 4.5 Form Requests and validation

-   Use dedicated Form Request classes for HTTP input validation when
    appropriate.
-   Keep validation declarative and focused on input shape and basic
    constraints.
-   Use custom Rule classes or validation services for complex
    validation.
-   Any database-backed validation must go through a repository.
-   Validate date ranges, supported pollutants and allowed filters at
    the application boundary.
-   Do not silently change invalid input into a different query.

### 4.6 DTOs and result objects

-   Use typed DTOs/value objects for data passed between layers when the
    data has a meaningful structure.
-   DTOs should make required and optional fields explicit.
-   Avoid passing large associative arrays between services.
-   Do not use Eloquent models as general-purpose API/view DTOs.
-   Keep serialization/presentation concerns at the HTTP/view boundary.
-   Use immutable DTOs where practical.

### 4.7 Eloquent Models

-   Models represent persistence entities and define relationships,
    casts and narrowly scoped persistence metadata.
-   Do not put application use cases, ranking logic or orchestration in
    models.
-   Do not execute hidden database queries from accessors or computed
    attributes used by views.
-   Avoid model observers for core business workflows if they make
    behavior implicit. Prefer explicit service orchestration.
-   Define casts and relationships clearly.
-   Use factories for test data.

### 4.8 Jobs, commands and scheduling

-   Jobs and Artisan commands are entry points/orchestrators, not places
    for business logic or database queries.
-   Delegate to services; services use repositories.
-   Jobs should be safe to retry where possible.
-   Use timeouts, retry/backoff and failure handling for external
    downloads.
-   Make ingestion idempotent: retrying a job must not create duplicate
    measurements or corrupt aggregates.
-   Do not assume a scheduled job ran successfully just because it was
    dispatched.
-   Log useful context (source, dataset, pollutant, sampling point, date
    range, job identifier) without logging secrets.
-   Keep scheduler definitions centralized and understandable.

### 4.9 External API and EEA integration

-   Isolate EEA-specific HTTP behavior behind an `AirQualityProvider`
    (or equivalent) interface and implementation.
-   Keep URLs, timeouts and provider configuration in Laravel
    config/environment variables, not scattered through code.
-   Use Laravel's HTTP client with explicit connection and request
    timeouts.
-   Handle non-success status codes, malformed responses, empty
    downloads, invalid ZIP/Parquet files and partial failures.
-   Do not trust external data blindly. Validate required columns,
    pollutant, unit, aggregation type, dates, validity and verification
    fields as applicable.
-   Preserve source identifiers and provenance so measurements can be
    traced to the source file/sampling point.
-   Do not silently treat modelled or gap-filled data as direct
    measurements.
-   Do not invent station metadata. Nullable coordinates/type/area must
    remain nullable when the source does not provide them.
-   Do not assume that an API discovery response proves complete
    national coverage.
-   Never fetch third-party data synchronously for each user-facing
    historical query.

### 4.10 Data and methodology integrity

Current investigation findings are documented in
`DATA_SOURCE_ANALYSIS.md` and `CODEX_CONTEXT.md`.

Important constraints: - E1a validated historical PM2.5 is the selected
primary source for the initial historical implementation. - Dataset ID
mappings observed during testing are empirical findings, not a formal
enum guaranteed by the Swagger contract. - The initial inventory found
PM2.5 E1a files for seven Bulgarian cities and eight sampling points;
this is not proof that no other source data exists. - Sofia has two
sampling points with materially different averages. Do not arbitrarily
select one or average them without an approved methodology. - Varna data
exists even though its matching metadata record was not found in the
inspected metadata file. Do not discard the measurements or invent
missing station metadata. - EEA date/time and timezone conventions
require explicit validation, especially around daylight-saving
changes. - Coverage must use the full expected calendar-day denominator,
including days with no measurements. - Distinguish measured, modelled
and gap-filled values. - Preserve raw/source provenance where feasible.

Do not hard-code provisional thresholds (for example, minimum daily
completeness or minimum period coverage) as official EEA rules. Make
methodology configurable or encapsulated, document assumptions, and ask
when a decision is not yet approved.

### 4.11 Blade and presentation

-   Use Laravel Blade for the UI. Do not introduce a React SPA.
-   Views are presentation-only: no database access, business
    calculations or external API calls.
-   Pass prepared DTOs/view models from controllers or presentation
    services.
-   Keep templates readable; extract reusable UI into Blade
    components/partials.
-   Escape user/source-provided content by default.
-   Keep formatting (dates, units, labels) consistent.
-   Clearly show unavailable data, incomplete coverage and methodology
    limitations to users.

### 4.12 Error handling and observability

-   Fail clearly and preserve useful diagnostic context.
-   Do not swallow exceptions silently.
-   Do not expose stack traces, secrets, internal file paths or provider
    credentials to users.
-   Log structured context for ingestion, query and archive workflows.
-   Distinguish expected data-quality issues from
    infrastructure/application failures.
-   Track ingestion outcomes: files discovered/downloaded/processed,
    rows accepted/rejected, duplicates, date coverage and failures.
-   Make archive operations verifiable: record object key, checksum/size
    where practical, and archive status before deleting source records.
-   Never delete source data before successful archive verification.

### 4.13 Security and configuration

-   Never commit `.env`, credentials, API keys or private data.
-   Add new environment variables to `.env.example` with safe
    placeholder values and document them.
-   Validate and constrain user input.
-   Use CSRF protection for state-changing web forms.
-   Use authorization middleware/policies if authenticated or privileged
    functionality is introduced.
-   Do not log secrets or sensitive request headers.
-   Avoid unsafe deserialization and untrusted file extraction paths.

## 5. Suggested application structure

Follow the repository's existing conventions if they are already
established. Do not move large parts of the application just to match
this example.

A possible structure:

``` text
app/
├── DTOs/
│   ├── AirQuality/
│   └── City/
├── Enums/
├── Http/
│   ├── Controllers/
│   └── Requests/
├── Jobs/
├── Models/
├── Providers/
│   └── AirQuality/
│       ├── Contracts/
│       └── Eea/
├── Repositories/
│   ├── Contracts/
│   └── Eloquent/
├── Services/
│   ├── AirQuality/
│   ├── Ingestion/
│   ├── Ranking/
│   └── Archiving/
└── Support/
```

Possible persistence boundaries (adjust to the actual schema): -
`CityRepository` - `StationRepository` / `SamplingPointRepository` -
`MeasurementRepository` - `DailyAggregateRepository` -
`IngestionRunRepository` - `ArchiveRepository`

Possible application services: - `GetCleanestCityService` -
`CalculateCityAirQualityService` - `IngestEeaMeasurementsService` -
`ValidateAndNormalizeMeasurementService` -
`ArchiveHistoricalMeasurementsService`

Do not create all these classes pre-emptively. Introduce only the
classes required by a concrete feature, and keep names aligned with the
actual domain model.

## 6. Testing standards

Write tests for new behavior and bug fixes. Tests are part of the
implementation, not an optional follow-up.

### Test categories

-   **Unit tests:** pure business rules, calculations, ranking, coverage
    and validation logic. Mock repository/provider interfaces; do not
    require a real database unless the test is specifically testing
    persistence.
-   **Repository/database tests:** verify query correctness, filters,
    joins, aggregates, upserts, transaction behavior and date boundaries
    against the configured test database.
-   **Feature tests:** verify routes, request validation,
    controller/service integration, response status and rendered
    content.
-   **Job/ingestion tests:** verify parsing, normalization, idempotency,
    retries/failure behavior and persistence orchestration using fake
    HTTP responses and test files.
-   **Archive tests:** verify object upload, checksum/verification
    workflow and that deletion/retention does not occur before
    verification.

### Testing rules

-   Test observable behavior, not private implementation details.
-   Use factories and realistic fixtures.
-   Fake external HTTP requests; tests must not depend on the live EEA
    service.
-   Fake queues/storage where appropriate.
-   Test empty datasets, missing days, duplicate rows, invalid units,
    invalid flags, malformed files and partial source failures.
-   Test leap years, inclusive/exclusive date boundaries and
    daylight-saving transitions where relevant.
-   Test that insufficient coverage does not produce a misleading city
    winner.
-   Test that controllers do not need direct database access by testing
    through their service dependency.
-   Do not weaken or delete tests simply to make a change pass.
-   Add regression tests for fixed bugs.

## 7. Code quality and style

-   Follow PSR-12 and the conventions already present in the repository.
-   Use strict, meaningful PHP type declarations wherever practical.
-   Use constructor dependency injection.
-   Prefer composition over inheritance.
-   Keep methods small and single-purpose.
-   Prefer intention-revealing names over explanatory comments.
-   Avoid premature abstractions, generic helper classes and unnecessary
    interfaces.
-   Avoid duplicated business rules.
-   Avoid magic numbers; use named constants or configuration for
    meaningful domain values.
-   Do not add dependencies without explaining why they are needed.
-   Do not use broad exception catches unless the code can
    handle/recover from the failure meaningfully.
-   Keep one class/interface/trait/enum per file.
-   Use the project's configured formatter, linter and static-analysis
    tools if present. Do not invent tool commands; inspect
    `composer.json`, `phpunit.xml`, `phpstan.neon`, Pint/PHPCS
    configuration and CI workflows first.
-   Add or update PHPDoc only where it adds type information that PHP
    cannot express or is required by the existing project conventions.

## 8. Database and performance

-   PostgreSQL is the source of truth for application-queryable data.
-   Redis is for queues/cache, not durable measurement storage.
-   Add indexes based on actual query patterns and verify with query
    plans when performance matters.
-   Use database constraints for invariants where appropriate (unique
    keys, foreign keys, not-null constraints).
-   Make ingestion writes idempotent using a well-defined unique key.
-   Avoid loading years of raw measurements into PHP memory for a user
    query.
-   Prefer precomputed daily/city aggregates for frequent historical
    queries when the methodology is established.
-   Keep the ranking query bounded to the requested date range and
    eligible data.
-   Use database transactions for atomic multi-step writes.
-   Use migrations for schema changes; never modify production schema
    manually.
-   Consider partitioning only when measured volume justifies its
    operational complexity.

## 9. Archiving and retention

The current proposal is to archive raw data older than 36 months to S3
in Parquet format while retaining compact aggregates in PostgreSQL for
fast historical queries.

This is a proposed design and must be implemented cautiously: 1.
Identify eligible data according to the approved retention cutoff. 2.
Build a deterministic archive object and object key. 3. Upload the
object. 4. Verify the upload (for example, checksum, size and successful
read/metadata check). 5. Persist archive metadata/status. 6. Only then
apply the approved source-data deletion/retention action.

Do not assume a particular S3 storage class or Glacier restore workflow
without an explicit decision. Do not delete raw data until the archive
is verified and the retention policy has been approved. Keep historical
aggregates queryable.

## 10. Git and change safety

-   Inspect `git status` and the current branch before making changes.
-   Do not discard, overwrite or reformat unrelated user changes.
-   Do not run destructive commands (including database resets,
    migration refreshes, file deletion or force pushes) without explicit
    approval.
-   Do not commit, push, create branches or open pull requests unless
    asked.
-   Keep changes focused and reviewable.
-   Do not silently change public routes, database contracts or data
    methodology.
-   If a task requires a choice that has not been decided, explain the
    options and ask before locking in the decision.

## 11. Working procedure for Codex

For every task:

1.  Read this file and the relevant project documents.
2.  Inspect the existing repository structure, relevant classes, routes,
    migrations, tests and configuration before proposing changes.
3.  Check the current Git branch and working tree.
4.  Briefly state the implementation plan and any assumptions.
5.  Implement the smallest coherent change that follows the
    architecture.
6.  Add or update tests.
7.  Run relevant tests and quality checks using commands that actually
    exist in the project.
8.  Review the diff for accidental changes, direct database queries
    outside repositories, business logic in controllers, secrets, and
    unrelated edits.
9.  Summarize what changed, files touched, tests/checks run and any
    remaining risks or decisions.

### Before considering a task complete, verify

-   [ ] No database query was added outside a Repository implementation
    (except documented framework/setup exceptions).
-   [ ] No business logic was added to a Controller, Job, Command or
    Blade view.
-   [ ] Services use dependency injection and repository/provider
    contracts where appropriate.
-   [ ] External calls are isolated and have timeouts/error handling.
-   [ ] Tests cover the new behavior and relevant edge cases.
-   [ ] No unresolved methodology assumption was silently converted into
    a product rule.
-   [ ] Documentation/config examples are updated where needed.
-   [ ] No secrets or unrelated changes are included.

## 12. Documentation expectations

Update documentation when changing: - architecture or technology
choices; - EEA source/dataset behavior; - data schema or ingestion
flow; - ranking, coverage, station aggregation or timezone
methodology; - retention/archive behavior; - setup, deployment or
environment variables; - user-visible limitations.

Keep `CODEX_CONTEXT.md`, `DATA_SOURCE_ANALYSIS.md` and
`TECHNOLOGY_DECISIONS.md` consistent. If a discovery changes a previous
finding, update the relevant document and explicitly call out the
correction.

## 13. Open decisions --- do not guess

The following are not yet fully finalized: - exact city-ranking
methodology; - how multiple sampling points are aggregated into one city
result (especially Sofia); - minimum daily completeness and minimum
period coverage thresholds; - timezone conversion and daylight-saving
handling; - treatment of missing days and invalid/unverified
measurements; - exact PostgreSQL raw-data retention/deletion policy; -
S3 bucket layout, storage class and lifecycle policy; - production
hosting platform; - whether and how supplementary sources are
introduced.

When a task depends on one of these decisions, identify the dependency
and ask for confirmation or implement only a clearly isolated,
configurable proposal that does not present the assumption as an
official rule.

------------------------------------------------------------------------

## 14. Short non-negotiable rules

1.  **All application database queries go through Repository classes.**
2.  **Controllers contain no business logic and no database access.**
3.  **Services/actions own business logic and orchestrate use cases.**
4.  **Jobs and commands delegate to services; they do not contain
    business logic or queries.**
5.  **Blade is presentation-only.**
6.  **External APIs are isolated behind provider/client abstractions.**
7.  **Use dependency injection; do not instantiate dependencies
    manually.**
8.  **Write tests for new behavior; never rely on live EEA calls in
    tests.**
9.  **Do not invent data, metadata, coverage or methodology.**
10. **Do not introduce major technologies or make unresolved
    architecture decisions without explaining and confirming them.**
