# DATABASE_SCHEMA

Foundation tables from the Laravel skeleton (`users`, `cache`, `jobs`, plus Breeze's tables) are unchanged. All business tables below are new.

## projects

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned PK | |
| user_id | FK -> users.id | cascade on delete |
| name | varchar(120) | unique per user (validated in StoreProjectRequest) |
| description | text | nullable |
| created_at / updated_at | timestamps | |

Indexes: `(user_id, created_at)`.

## websites

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned PK | |
| project_id | FK -> projects.id | cascade on delete |
| user_id | FK -> users.id | cascade on delete; mirrors project owner |
| url | varchar(2048) | as submitted |
| normalized_url | varchar(2048) | canonical form |
| url_hash | varchar(64) | SHA-256 of normalized URL |
| host | varchar(255) | lowercase host |
| label | varchar(150) | nullable |
| last_analyzed_at | timestamp | nullable |
| created_at / updated_at | timestamps | |

Unique: `(project_id, url_hash)`. Indexes: `(user_id, created_at)`.

## analysis_jobs

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned PK | |
| website_id | FK -> websites.id | cascade on delete |
| user_id | FK -> users.id | cascade on delete |
| uuid | varchar(36), unique | secure job identifier for workflow dispatch/callback |
| status | varchar(20), index | pending, dispatched, crawling, processing, completed, failed, cancelled |
| attempt | smallint unsigned | retry counter |
| dispatched_at / started_at / finished_at | timestamps | nullable |
| crawled_pages | int unsigned | nullable; populated by callback (Phase 2) |
| crawl_duration_ms | int unsigned | nullable |
| failure_reason | varchar(120) | nullable |
| error_message | text | nullable |
| payload | json | nullable; dispatcher/callback metadata |
| created_at / updated_at | timestamps | |

Indexes: `(website_id, created_at)`, `(user_id, status)`.

## audit_logs

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned PK | |
| user_id | FK -> users.id | nullable, null on delete |
| action | varchar(120), index | e.g. `project.created`, `analysis_job.created` |
| auditable_type / auditable_id | polymorphic, indexed | |
| ip_address | varchar(45) | nullable |
| user_agent | varchar(500) | nullable, truncated |
| properties | json | nullable |
| created_at | timestamp | index via (user_id, created_at) |

Currently recorded actions: `project.created`, `project.updated`, `project.deleted`, `website.created`, `website.deleted`, `analysis_job.created`, `analysis_job.dispatched`, `crawler_callback.completed`, `crawler_callback.failed`, `crawler_callback.rejected`, `crawler_callback.host_mismatch`, `analysis_job.stale_timeout`, `analysis_job.local_runner_failure`.

## Crawler tables (created in Phase 2)

### crawled_pages

| Column | Type | Notes |
|---|---|---|
| analysis_job_id | FK -> analysis_jobs, cascade | index (analysis_job_id, created_at) |
| url | varchar(2048) | requested URL |
| final_url | varchar(2048) | nullable; after redirects |
| http_status | smallint unsigned | nullable |
| title | varchar(512) | nullable |
| meta | json | description/keywords/OpenGraph (stringified, max 25) |
| headings | json | `{h1: [], h2: [], h3: []}` (max 30 each) |
| text_length | int unsigned | nullable |
| load_time_ms | int unsigned | nullable |
| content_hash | varchar(64), index | sha256 of final URL (deduplication key for later phases) |
| features | json | `{feature_key, present, count, evidence[]}` summary array |
| depth | int unsigned | BFS depth, 0 = start page |

### page_snapshots

| Column | Type | Notes |
|---|---|---|
| analysis_job_id | FK -> analysis_jobs, cascade | |
| crawled_page_id | FK -> crawled_pages, cascade | unique (crawled_page_id, device) |
| device | varchar(20) | `desktop` or `mobile` |
| storage_path | varchar(512) | path/blob reference - screenshots are never stored inside the database |
| size_bytes | int unsigned | |
| captured_at | timestamp | |

Populated from Phase 3 onward (optional Playwright screenshots; stays empty when the http engine runs).

### page_features

| Column | Type | Notes |
|---|---|---|
| crawled_page_id | FK -> crawled_pages, cascade | unique (crawled_page_id, feature_key) |
| feature_key | varchar(100) | e.g. `cta_buttons`, `lead_forms`, `email_links`, `trust_signals` |
| present | boolean | |
| count | int | |
| evidence | json | capped evidence strings |

The per-page `features` JSON on `crawled_pages` is already populated; these normalized rows will be written when the Phase 3 inference services run.

### crawl_errors

| Column | Type | Notes |
|---|---|---|
| analysis_job_id | FK -> analysis_jobs, cascade | |
| url | varchar(2048) | nullable |
| error_type | varchar(60) | e.g. `dns_failure`, `blocked_ip`, `http_404`, `timeout` |
| message | varchar(1000) | truncated |
| occurred_at | timestamp | |

## Technical analysis tables (created in Phase 3)

### technical_audits

- `id`, `analysis_job_id` (FK -> analysis_jobs, cascade, **unique** - one audit per job), `website_id` (FK -> websites, cascade, indexed)
- `score` unsigned tiny int nullable (null = not computable; not computed means "unknown", never 0)
- `checks` json - array of check objects: `key`, `label`, `status` (pass|warn|fail|unknown), `value`, `evidence[]`, `explanation`, `recommendation`
- `version` string(20) default `v1.0` - audit rule-set version
- timestamps

Indexes: unique `analysis_job_id`, indexed `website_id` (from FK).

## Intelligence tables (Phase 3 portion)

### marketing_scores

- `id`, `website_id` (FK, cascade, indexed), `analysis_job_id` (FK, cascade)
- `module_key` string(60) - e.g. `campaign_readiness`; later phases add more module keys
- `score` unsigned tiny int nullable, `methodology` string(80) default `heuristic` (method label stored with the score), `version` string(20) `v1.0` (scoring version)
- `breakdown` json - for campaign readiness: `criteria[]` (score, weight, evidence, explanation, confidence, recommendation), `confidence`, `methodology`, `summary`
- `computed_at` timestamp nullable, timestamps

Constraints: **unique (`analysis_job_id`, `module_key`)** (idempotent re-processing), index (`website_id`, `module_key`), index `website_id`.

### campaign_insights

- `id`, `website_id` (FK, cascade, indexed), `analysis_job_id` (FK, cascade)
- `module_key` string(60) (`campaign_readiness` | `technical_audit`), `priority` string(20) (`high`|`medium`|`low`)
- `title` string(255), `evidence` json nullable, `recommendation` text nullable
- `effort` string(20) nullable (low|medium|high), `direction` string(40) nullable (`improvement`), `confidence` string(20) nullable
- timestamps

Indexes: (`analysis_job_id`, `module_key`), (`website_id`, `priority`), index `website_id`. Insights are replaced (deleted + re-inserted) on each pipeline run for a job, so there is no uniqueness constraint.

## Planned tables (created in later phases)

technical: `performance_audits` (deferred until a Lighthouse/PageSpeed-like source exists)
intelligence: `predictive_insights`, `budget_scenarios`, `waste_findings`, `lead_insights`, `creative_insights`, `recommendations`
reporting/admin: `reports`, `integration_settings`

TiDB compatibility notes: the schema uses standard migrations; website URLs exceed TiDB index-key limits only in composite unique constraints, which is why the 64-char `url_hash` is used for the unique key.
