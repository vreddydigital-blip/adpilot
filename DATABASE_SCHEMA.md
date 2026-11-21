# DATABASE_SCHEMA (Phase 1)

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

Currently recorded actions: `project.created`, `project.updated`, `project.deleted`, `website.created`, `website.deleted`, `analysis_job.created`.

## Planned tables (created in later phases)

crawler: `crawled_pages`, `page_snapshots`, `page_features`, `crawl_errors`
technical: `technical_audits`, `performance_audits`
intelligence: `marketing_scores`, `campaign_insights`, `predictive_insights`, `budget_scenarios`, `waste_findings`, `lead_insights`, `creative_insights`, `recommendations`
reporting/admin: `reports`, `integration_settings`

TiDB compatibility notes: the schema uses standard migrations; website URLs exceed TiDB index-key limits only in composite unique constraints, which is why the 64-char `url_hash` is used for the unique key.
