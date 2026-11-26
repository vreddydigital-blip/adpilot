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

## Intelligence tables (Phase 3 + Phase 4 portion)

### marketing_scores

- `id`, `website_id` (FK, cascade, indexed), `analysis_job_id` (FK, cascade)
- `module_key` string(60) - `campaign_readiness` (Phase 3), `conversion_opportunity`/`lead_intelligence` (Phase 4), `marketing_advisor` (Phase 5)
- `score` unsigned tiny int nullable, `methodology` string(80) default `heuristic` (method label stored with the score; the advisor row stores `rule_based`), `version` string(20) `v1.0` (scoring version)
- `breakdown` json - per module: campaign readiness stores `criteria[]` (score, weight, evidence, explanation, confidence, recommendation), `confidence`, `methodology`, `summary`; conversion opportunity stores `label`, `methodology`, `opportunities[]`, `high`/`medium`/`low` key buckets; lead intelligence stores `label`, `methodology`, `summary`, `forms[]`, `contact_methods`, `confidence`; marketing advisor stores `label`, `engine`, `engine_note`, `methodology`, `summary`, `scores` (map of the four module scores), `priorities` (high/medium/low counts), `disclaimer`
- `computed_at` timestamp nullable, timestamps

Constraints: **unique (`analysis_job_id`, `module_key`)** (idempotent re-processing), index (`website_id`, `module_key`), index `website_id`.

### campaign_insights

- `id`, `website_id` (FK, cascade, indexed), `analysis_job_id` (FK, cascade)
- `module_key` string(60) (`campaign_readiness` | `technical_audit`), `priority` string(20) (`high`|`medium`|`low`)
- `title` string(255), `evidence` json nullable, `recommendation` text nullable
- `effort` string(20) nullable (low|medium|high), `direction` string(40) nullable (`improvement`), `confidence` string(20) nullable
- timestamps

Indexes: (`analysis_job_id`, `module_key`), (`website_id`, `priority`), index `website_id`. Insights are replaced (deleted + re-inserted) on each pipeline run for a job, so there is no uniqueness constraint.

## Intelligence tables (created in Phase 4)

### predictive_insights

- `id`, `website_id` (FK, cascade, indexed), `analysis_job_id` (FK, cascade)
- `opportunity_key` string(60) (e.g. `cta_placement`, `page_performance`), `priority` string(20) (`high`|`medium`|`low`), `title` string(255)
- `evidence` json nullable, `recommendation` text nullable, `effort` string(20) nullable, `direction` string(40) nullable (`improvement`)
- `severity`/`impact`/`confidence`/`score` unsigned tiny int nullable (all null when evidence is missing)
- timestamps

Indexes: (`analysis_job_id`, `priority`), (`website_id`, `priority`). Rows are replaced per job on each pipeline run.

### waste_findings

- `id`, `website_id` (FK, cascade, indexed), `analysis_job_id` (FK, cascade)
- `finding_key` string(60) (e.g. `technical_https`, `criterion_cta_effectiveness`, `no_obvious_leakage`), `severity` string(20) (`high`|`medium`|`low`), `title` string(255)
- `evidence` json nullable, `potential_impact` text nullable, `correction` text nullable
- timestamps

Indexes: (`analysis_job_id`, `severity`), (`website_id`, `severity`). Rows are replaced per job on each pipeline run.

### lead_insights

- `id`, `website_id` (FK, cascade, indexed), `analysis_job_id` (FK, cascade)
- `insight_key` string(60) (`no_forms`, `form_friction`, `missing_contact_channels`, `homepage_cta_consistency`, `contact_page`), `priority` string(20), `title` string(255)
- `evidence` json nullable, `recommendation` text nullable, `effort` string(20) nullable
- timestamps

Indexes: (`analysis_job_id`, `priority`), (`website_id`, `priority`). Rows are replaced per job on each pipeline run.

### creative_insights

- `id`, `website_id` (FK, cascade, indexed), `analysis_job_id` (FK, cascade)
- `kind` string(60) (`google_headline`, `google_description`, `meta_headline`, `meta_primary_text`, `messaging_angle`, `message_match`)
- `channel` string(40) nullable (`google`|`meta`; null for angles/match), `headline` string(255) nullable, `text` string(500) nullable
- `char_length` integer nullable, `payload` json nullable (message-match verdict data)
- timestamps

Indexes: (`analysis_job_id`, `kind`), (`website_id`, `kind`). Rows are replaced per job on each pipeline run.

### budget_scenarios

- `id`, `website_id` (FK, cascade, indexed), `user_id` (FK, cascade, indexed), `analysis_job_id` (FK, cascade, **nullable** - scenarios are user-assumption math and do not require a completed crawl)
- `label` string(120), `channel` string(60) default `all`
- `inputs` json (budget, cpc, lead_rate, conversion_rate, average_order_value, gross_margin, target_cpa), `results` json (source_inputs, channel, scenarios[] with every simulated figure + disclaimer)
- `methodology` string(80) default `deterministic simulation`, `version` string(20) default `v1.0`
- timestamps

Indexes: (`website_id`, `created_at`), (`user_id`, `created_at`). Per-user ownership is enforced (website view authorization + user_id match); scenarios are never crawl-derived.

## Intelligence table (created in Phase 5)

### recommendations

- `id`, `website_id` (FK, cascade, indexed), `analysis_job_id` (FK, cascade)
- `recommendation_key` string(60) (consolidated topic key, e.g. `mobile_usability`, `lead_capture`, `cta_effectiveness`)
- `priority` string(20) (`high`|`medium`|`low`), `confidence` string(20) nullable
- `title` string(255), `rationale` text nullable (`N cross-module finding(s) consolidated from: <modules>`), `action` text nullable
- `evidence` json nullable (de-duplicated evidence lines, max 4), `module_source` json nullable (array of contributing module keys)
- `effort` string(20) nullable, `expected_impact` string(40) nullable, `position` unsigned tiny int (1..n within the job)
- `source` string(60) default `rule_based_v1`
- timestamps

Indexes: (`analysis_job_id`, `priority`), (`website_id`, `priority`), index `website_id`. Rows are replaced (deleted + re-inserted) on each pipeline run for a job.

## Reporting table (created in Phase 6)

### reports

- `id`, `user_id` (FK, cascade, indexed), `website_id` (FK, cascade, indexed), `analysis_job_id` FK nullable (cascade, indexed)
- `uuid` string(36) unique (report identifier + filename seed), `title` string(160)
- `format` string(10) (`pdf`|`csv`), `status` string(20) default `ready` (ready|expired), `version` string(20) default `v1.0`
- `path` string(255) nullable (storage path `reports/<uuid>.<ext>`), `filename` string(200), `size_bytes` unsigned int nullable
- `payload` json nullable (the full snapshot: website/job meta, five module scores, campaign criteria, technical checks, findings, advisor recommendations, disclaimer)
- `generator` string(60) default `dompdf` (dompdf | fputcsv), timestamps

Indexes: (`user_id`, `created_at`), (`website_id`, `created_at`), index `analysis_job_id`. Files live on the local disk; with an ephemeral filesystem the `payload` allows deterministic regeneration on download.

## Integration table (created in Phase 7)

`integration_settings` (migrated, `2026_01_01_000700_create_integration_settings_table.php`):
- `user_id` FK -> `users`, cascade delete
- `provider` string(40) (catalogue keys: `google_ads`, `meta_ads`, `google_analytics`, `google_search_console`)
- `status` string(20) default `not_connected` (only `not_connected` | `credentials_saved` exist - no live connection is made in Phase 7)
- `credentials` text nullable - Laravel `encrypted:json` cast (at-rest encryption; readable only with the app's `APP_KEY`)
- `settings` json nullable
- `connected_at` / `last_synced_at` timestamps nullable (unused until a real connector lands)
- timestamps; unique (`user_id`, `provider`); index `status`

## Planned tables (created in later phases)

technical: `performance_audits` - decision recorded in Phase 7: deferred until a real Lighthouse/PageSpeed-style data source exists; no heuristic stand-in will be built

TiDB compatibility notes: the schema uses standard migrations; website URLs exceed TiDB index-key limits only in composite unique constraints, which is why the 64-char `url_hash` is used for the unique key.
