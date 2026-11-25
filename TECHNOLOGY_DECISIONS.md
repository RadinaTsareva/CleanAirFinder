# Clean Air Finder --- Technology and Architecture Decisions

**Document type:** Architecture Decision Record (ADR) / technology
rationale\
**Status:** Proposed baseline for the first implementation\
**Last updated:** 25 November 2026\
**Related documents:** `CODEX_CONTEXT.md`, `DATA_SOURCE_ANALYSIS.md`

------------------------------------------------------------------------

## 1. Purpose

This document explains why the initial Clean Air Finder architecture
uses Laravel, Blade, PostgreSQL, Redis, Docker Compose, and AWS
S3-compatible object storage.

It also explains why Snowflake is not part of the first version, and
clarifies an important distinction: **AWS S3 and Snowflake are not
direct alternatives**. S3 is object storage; Snowflake is a managed
cloud data platform and analytical data warehouse. Snowflake can itself
run on AWS infrastructure. The project needs an archive for source files
and a transactional database for application queries, not a separate
enterprise analytical warehouse at this stage.

The decisions are based on the assignment's scope and the data
investigation completed so far. They are not claims that the selected
technologies are universally superior.

## 2. Requirements driving the architecture

The assignment requires the application to:

-   allow users to select historical date ranges;
-   identify the Bulgarian city with the cleanest air for a selected
    period;
-   show average air quality and context about data
    quality/completeness;
-   use real public data and respect its terms;
-   ingest and store data locally instead of calling a third-party
    provider for every user request;
-   keep at least the most recent 12 months of data queryable;
-   archive data older than 36 months;
-   provide fast historical queries;
-   tolerate slow, unavailable, or malformed upstream data;
-   support one-command setup/deployment;
-   provide observability and documentation.

The current EEA investigation found E1a PM2.5 files for seven of the 17
Bulgarian cities returned by the EEA city endpoint. It found eight
sampling points in total. This limited coverage is a data limitation,
not something that a different database or cloud platform can solve.

## 3. Proposed architecture at a glance

``` text
                 EEA download API / Parquet files
                              |
                              v
                    Laravel ingestion jobs
                 (schedule, download, validate,
                   normalize, deduplicate)
                              |
                +-------------+-------------+
                |                           |
                v                           v
        PostgreSQL                    Object storage
   recent raw measurements,          AWS S3 (production)
   station metadata, aggregates      Parquet archive
                |                           |
                v                           |
       Laravel query services <-------------+
                |
                v
          Blade web interface
                |
         Redis cache / queues
```

Redis is an infrastructure component used for asynchronous work and
caching; it is not the authoritative store for measurements. PostgreSQL
remains the source of truth for application-queryable relational data.
S3 stores archived files/objects and is not queried as if it were a
relational database.

The diagram is logical. Exact deployment topology, hosting services, and
the final archive retrieval workflow remain to be implemented and
tested.

## 4. Backend: Laravel / PHP

**Decision: use Laravel as the application backend.**

### Why it fits

-   The project is a web application with ingestion workflows,
    validation, database access, scheduled tasks, queues, and a
    user-facing interface. Laravel provides these capabilities within
    one framework.
-   The EEA ingestion process can be implemented as jobs: download a
    file, validate its schema and metadata, normalize records, persist
    them, and record the outcome.
-   Laravel queues allow time-consuming work to run outside a normal
    HTTP request. This prevents a slow EEA download or large Parquet
    import from blocking a user-facing request.
-   Laravel's scheduler can trigger periodic ingestion and maintenance
    tasks.
-   The application can remain a modular monolith: ingestion,
    measurement processing, ranking, and presentation are separated into
    modules/services without the operational overhead of multiple
    independently deployed microservices.

Official references: - Laravel queues:
<https://laravel.com/docs/13.x/queues> - Laravel task scheduling:
<https://laravel.com/docs/13.x/scheduling>

### Why not microservices initially?

The current system has one main domain, one primary upstream data
source, and a relatively small initial dataset. Splitting ingestion,
API, ranking, and UI into separate services would add network
boundaries, deployment configuration, service-to-service authentication,
distributed tracing, and failure modes before there is a demonstrated
scaling need.

A modular monolith keeps clear internal boundaries while allowing a
module to be extracted later if its independent scaling or deployment
becomes necessary.

### Why not a separate Python ingestion service?

Python and pandas/pyarrow were useful for exploratory Parquet
inspection. That does not automatically justify a permanent second
runtime and service.

The production ingestion workflow should initially live in Laravel to
keep deployment and operations simpler. If PHP Parquet processing proves
impractical or inefficient, a narrowly scoped Python command/worker can
be evaluated later. It should be introduced only with evidence and a
clearly defined interface.

## 5. Frontend: Blade instead of a separate React SPA

**Decision: use Laravel Blade for the first version.**

### Why it fits

-   The UI is relatively focused: date-range selection, result display,
    coverage, source details, and limitations.
-   Server-rendered pages avoid maintaining a separate frontend
    application, build pipeline, API authentication layer, and
    deployment.
-   Blade is integrated into Laravel and supports reusable layouts and
    components.
-   It is sufficient for forms, tables, status indicators, and simple
    charts. If richer interactions are needed, a small progressive
    enhancement can be added without turning the first version into a
    separate SPA.

Official reference: <https://laravel.com/docs/13.x/blade>

### Why not React initially?

React would be reasonable for a highly interactive application or a
frontend shared across multiple clients. For this assignment, a separate
React SPA would increase the number of moving parts without a clear
product benefit. It would also require a separately managed
frontend/backend contract.

This is a scope decision, not a claim that React is unsuitable.

## 6. Primary relational database: PostgreSQL

**Decision: use PostgreSQL for application data and queryable
measurements.**

### Why it fits

The domain contains related entities and constraints:

-   cities;
-   stations and sampling points;
-   pollutants;
-   source datasets;
-   measurement records;
-   ingestion runs;
-   daily station and city aggregates.

PostgreSQL provides relational constraints and SQL queries suited to
filtering measurements by station, pollutant, and date range. It can
also support indexes, date-based query patterns, and precomputed
aggregates.

PostgreSQL supports declarative table partitioning. Partitioning is an
option if data volume and query plans justify it; it should not be added
prematurely.

Official reference:
<https://www.postgresql.org/docs/current/ddl-partitioning.html>

### Why not Snowflake as the application's main database?

Snowflake is a managed cloud data platform with analytical SQL compute
and data-warehouse capabilities. It is designed for analytical workloads
and separates storage, compute, and cloud-services layers.

For the current application, the main workload is not enterprise-scale
BI across many departments or large concurrent analytical warehouses. It
is a web application's transactional metadata plus date-range reads over
measurements and precomputed aggregates.

PostgreSQL is a more direct fit for: - application transactions and
relational constraints; - Laravel integration; - local Docker
development and automated tests; - a straightforward deployment model; -
the current expected volume and query patterns.

Introducing Snowflake now would add another platform, integration path,
data-loading process, operational concepts, and potentially separate
cost controls without solving a current requirement that PostgreSQL
cannot reasonably handle.

This is not a technical limitation of Snowflake. Snowflake could become
useful if the project later grows into a larger analytical platform with
much larger datasets, multiple data sources, many independent analytical
workloads, or organization-wide BI.

Official Snowflake architecture reference:
<https://docs.snowflake.com/en/user-guide/intro-key-concepts>

### Important distinction: Snowflake versus S3

They are not interchangeable:

  -----------------------------------------------------------------------
  Technology              Primary role            Role in this project
  ----------------------- ----------------------- -----------------------
  PostgreSQL              Relational operational  Queryable measurements,
                          database                metadata, ingestion
                                                  state, aggregates

  Amazon S3               Object storage          Durable storage for
                                                  archived Parquet files

  Snowflake               Managed analytical data Not required for the
                          platform / warehouse    first version
  -----------------------------------------------------------------------

Snowflake can be deployed on AWS, Azure, or Google Cloud. Choosing AWS
S3 for object storage does not mean Snowflake is an alternative cloud
provider. Snowflake itself uses underlying cloud infrastructure.

Reference:
<https://docs.snowflake.com/en/user-guide/intro-cloud-platforms>

## 7. Cache and queue backend: Redis

**Decision: use Redis for queues and cache.**

### Queue use

EEA ingestion can take longer than a normal web request. Jobs such as
downloading files, parsing data, validating rows, and recalculating
aggregates should run asynchronously.

Redis provides a queue backend supported by Laravel. Queue workers can
retry transient failures and process jobs independently from web
requests.

### Cache use

Redis can cache frequently requested results, for example: - a city
ranking for a common date range; - a city/station summary; - metadata
that changes infrequently.

Cache entries must have explicit expiration or invalidation rules when
new measurements or aggregates are ingested.

### Why Redis is not the source of truth

Redis is used for speed and coordination, not permanent measurement
storage. PostgreSQL remains authoritative. If a cache entry disappears,
the application must be able to rebuild it from PostgreSQL.

### Why not start with only synchronous processing?

Synchronous ingestion would tie a user's request or deployment process
to external download speed and parsing time. Queue-based processing
isolates upstream slowness and supports retries and operational
visibility.

Official references: - Laravel queues:
<https://laravel.com/docs/13.x/queues> - Redis overview:
<https://redis.io/about/>

## 8. Archive storage: AWS S3

**Decision: use Amazon S3 as the proposed production object store for
archived source data.**

The assignment explicitly requires data older than 36 months to be
archived. The EEA source provides Parquet files, which are suitable
archive objects because they preserve the source's tabular data in a
compact, portable format.

### Why S3 fits the archive requirement

-   S3 is object storage designed to store and retrieve files/objects
    rather than execute relational queries.
-   It separates archived raw files from the operational PostgreSQL
    database.
-   It can store the original EEA Parquet files or normalized Parquet
    archive files.
-   S3 Lifecycle policies can transition objects between storage classes
    as they age.
-   The application can use an S3-compatible filesystem interface and
    keep the archive provider behind configuration.

Official references: - S3 storage classes:
<https://docs.aws.amazon.com/AmazonS3/latest/userguide/storage-class-intro.html> -
S3 Lifecycle:
<https://docs.aws.amazon.com/AmazonS3/latest/userguide/object-lifecycle-mgmt.html>

### Proposed data placement

A practical initial policy to validate:

  -----------------------------------------------------------------------
  Data                    Proposed location       Reason
  ----------------------- ----------------------- -----------------------
  Recent raw measurements PostgreSQL              Fast filtering and
  (within the operational                         historical queries
  window)                                         

  Daily station           PostgreSQL              Fast period
  aggregates                                      calculations

  Daily city aggregates   PostgreSQL              Fast ranking and result
                                                  display

  Raw source files /      S3 as Parquet objects   Archive and
  older raw data beyond                           reproducibility
  36 months                                       

  Ingestion metadata and  PostgreSQL              Track what was ingested
  archive object                                  and where the archive
  references                                      is stored
  -----------------------------------------------------------------------

The exact operational window and deletion/movement policy must be
aligned with the assignment. Keep at least the latest 12 months
queryable. A reasonable starting design is to keep a rolling 36-month
raw-data window in PostgreSQL, archive older raw files to S3, and retain
the compact daily aggregates needed for historical date-range results in
PostgreSQL. This lets users query historical results without restoring a
cold archive for every request.

If a future correction or reprocessing requires old raw measurements,
the ingestion/archive workflow can restore the relevant Parquet objects
to a temporary processing area, reprocess them, and update the
aggregates.

### Important Glacier / cold archive caveat

S3 Glacier Flexible Retrieval and S3 Glacier Deep Archive are archival
storage classes, not instant query stores. Objects in those classes must
be restored before they can be read. Therefore, do not place data needed
for an immediate user request in a cold archive tier unless the
application has an asynchronous restore workflow and clearly
communicates the delay.

A Lifecycle transition is not the same thing as a database query or a
live mount. Keep query-ready aggregates separately in PostgreSQL.

Reference:
<https://docs.aws.amazon.com/AmazonS3/latest/userguide/lifecycle-transition-general-considerations.html>

### Why not Snowflake for archiving?

Snowflake is not a simple replacement for an S3 bucket. It is an
analytical data platform that manages data storage and compute for SQL
analytics. The project needs durable file/object storage for older
source data, not a separate analytical engine just to retain Parquet
files.

S3 is therefore the more direct fit for the archive requirement.
Snowflake could be layered on top of S3 or used as an analytical
warehouse in a future architecture, but it is not needed now.

### Why AWS S3 rather than committing to the whole AWS platform?

Only S3 is selected in this document as the proposed archive service.
The application hosting platform is still undecided. We are not
committing at this stage to EC2, ECS, EKS, RDS, Lambda, or a full
AWS-native architecture.

This keeps the storage decision independent from the future
compute/deployment decision.

### Local development

For local development, the application can use a local S3-compatible
service such as MinIO, or a filesystem-based test disk. Production
configuration can point to AWS S3.

This keeps local development reproducible and avoids requiring AWS
credentials for every developer/test run. The exact local service is a
proposed choice and should be validated with the selected Laravel
filesystem adapter and integration tests.

## 9. File format: Parquet for archive; relational rows for application queries

**Decision: retain EEA source Parquet files and use PostgreSQL for
query-ready records.**

Parquet is already the source delivery format for the inspected EEA
files. Keeping the original source files in the archive supports
traceability and reprocessing.

The application should not require every user query to read Parquet
objects from S3. Instead: 1. download the source file in a background
job; 2. validate its schema and provenance; 3. normalize records into
PostgreSQL; 4. calculate station/day and city/day aggregates; 5. archive
source files and older raw data according to the retention policy.

A later optimization may store normalized historical partitions as
Parquet in S3, but the canonical archive format and object naming
convention should be finalized during implementation.

## 10. Containers and local environment: Docker Compose

**Decision: use Docker Compose for local development and repeatable
setup.**

The application depends on multiple processes/services, such as: -
Laravel/PHP runtime; - PostgreSQL; - Redis; - queue worker; -
scheduler; - optional local S3-compatible storage.

Docker Compose describes and runs a multi-container application from a
YAML configuration. It supports a repeatable local setup and a
one-command start, which directly supports the assignment's
deployment/setup expectation.

Official reference: <https://docs.docker.com/compose/>

Docker Compose is the local orchestration choice. It does not imply that
production must run on Docker Compose; production hosting and
orchestration remain a separate decision.

## 11. Why not add more infrastructure now?

The first version should avoid introducing services without a clear
requirement:

  -----------------------------------------------------------------------
  Technology/service not  Why it is not needed    When to reconsider
  selected initially      yet                     
  ----------------------- ----------------------- -----------------------
  Snowflake               No current need for a   Much larger datasets,
                          separate analytical     multiple analytical
                          warehouse               teams/workloads,
                                                  complex BI

  Kafka                   A scheduled ingestion   High-throughput event
                          pipeline and Laravel    streaming, many
                          queues are sufficient   independent consumers,
                          initially               replayable event
                                                  streams

  Kubernetes              One application and a   Multiple independently
                          few supporting services scaled services and
                          do not justify cluster  operational team
                          orchestration           maturity

  Elasticsearch /         The main access pattern Search requirements or
  OpenSearch              is relational filtering query patterns that
                          and aggregation, not    PostgreSQL cannot serve
                          full-text search        well

  Separate Python         Exploratory Python      PHP parsing/performance
  microservice            scripts are sufficient  limitations
                          for discovery;          demonstrated by
                          production ingestion    benchmarks
                          can start in Laravel    

  Dedicated data          PostgreSQL aggregates   Analytics workloads
  warehouse               can serve the current   begin to compete with
                          result screens          application queries
  -----------------------------------------------------------------------

These are deferred options, not technologies that are inherently
unsuitable.

## 12. Trade-offs and risks

The proposed design intentionally favors simplicity and explainability
over maximum theoretical scale.

Trade-offs: - PostgreSQL may eventually need partitioning, tuning, or a
separate analytical store as data grows. - S3 archives are not directly
queryable like PostgreSQL; old raw files may require restoration and
processing. - Redis introduces another service to monitor, but supports
asynchronous ingestion and caching. - A modular monolith is simpler to
deploy but requires disciplined module boundaries as the codebase
grows. - Blade reduces frontend complexity but is less suited than a
dedicated SPA to highly interactive client-side workflows. - EEA
coverage currently includes only seven Bulgarian cities in the
discovered E1a PM2.5 inventory; the technology stack cannot compensate
for missing source coverage.

## 13. Decisions still open

Do not hard-code these until they are explicitly decided and tested:

1.  Production hosting provider and deployment target.
2.  Whether AWS S3 or another S3-compatible provider will be used in
    production.
3.  Local object storage choice (MinIO versus filesystem test disk).
4.  Exact S3 storage class and Lifecycle transition age.
5.  Exact archive object layout and Parquet partitioning scheme.
6.  Whether raw records older than 36 months are removed from PostgreSQL
    after successful archive verification.
7.  Which historical aggregates remain in PostgreSQL and for how long.
8.  How users are informed when raw data must be restored from a cold
    archive.
9.  Final city aggregation, station eligibility, and data-coverage
    thresholds.
10. Whether E2a is incorporated for current dates.

## 14. Short interview explanation

A concise way to explain the architecture:

> I chose Laravel with Blade because this is a focused web application,
> and keeping the backend and UI in one framework reduces deployment and
> integration complexity. PostgreSQL stores the relational measurement
> data and precomputed aggregates because the main workload is
> date-range filtering and aggregation, not enterprise-scale warehouse
> analytics. Redis handles background ingestion jobs and caching so slow
> upstream downloads do not block user requests. Docker Compose makes
> the local environment reproducible. For long-term raw-data retention,
> I propose S3 object storage with Parquet archives and lifecycle
> policies. I did not introduce Snowflake because we do not currently
> need a separate analytical warehouse. Also, S3 and Snowflake solve
> different problems: S3 stores objects, while Snowflake provides an
> analytical data platform. If the project grows into a larger BI
> workload, Snowflake could be reconsidered later.

## 15. Official references

-   Laravel queues: <https://laravel.com/docs/13.x/queues>
-   Laravel task scheduling: <https://laravel.com/docs/13.x/scheduling>
-   Laravel Blade: <https://laravel.com/docs/13.x/blade>
-   PostgreSQL partitioning:
    <https://www.postgresql.org/docs/current/ddl-partitioning.html>
-   Redis overview: <https://redis.io/about/>
-   Docker Compose: <https://docs.docker.com/compose/>
-   Amazon S3 storage classes:
    <https://docs.aws.amazon.com/AmazonS3/latest/userguide/storage-class-intro.html>
-   Amazon S3 Lifecycle:
    <https://docs.aws.amazon.com/AmazonS3/latest/userguide/object-lifecycle-mgmt.html>
-   Amazon S3 archive retrieval considerations:
    <https://docs.aws.amazon.com/AmazonS3/latest/userguide/lifecycle-transition-general-considerations.html>
-   Snowflake architecture:
    <https://docs.snowflake.com/en/user-guide/intro-key-concepts>
-   Snowflake supported cloud platforms:
    <https://docs.snowflake.com/en/user-guide/intro-cloud-platforms>
