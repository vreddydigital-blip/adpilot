# ARCHITECTURE

## Overview

AdPilot AI is a modular Laravel monolith for the application plus a separate Node.js/Playwright crawler executed through GitHub Actions (Phase 2). No advertising-platform credentials are required for the initial data collection: a website URL is the only input.

```
Browser
  -> Laravel (Blade + API)
      -> MySQL-compatible DB (SQLite locally / TiDB Cloud Starter in the free deployment)
      -> analysis_jobs row (status: pending)
      -> [Phase 2] GitHub Actions workflow dispatch (authenticated, bounded crawl)
      -> [Phase 2] Node.js Playwright crawler (SSRF-hardened, max 20 pages)
      -> [Phase 2] signed callback to the Laravel API
      -> intelligence services compute scores from stored evidence
      -> dashboard and reports
```

## Data flow (Phase 1 state)

1. User registers/logs in (Breeze authentication).
2. User creates a project; project ownership is enforced by `App\Policies\ProjectPolicy`.
3. User adds a website URL to a project. `App\Services\Security\UrlValidator` validates syntax, scheme, host and rejects local/private/metadata hosts at submission time; `App\Support\UrlNormalizer` produces a canonical URL whose SHA-256 hash acts as the unique key per project.
4. User (or API client) requests analysis: `App\Services\Analysis\AnalysisJobService` enforces duplicate protection (one active job per website) and creates a job in `pending` state with a UUID identifier.
5. From Phase 2, dispatch to GitHub Actions and callback persistence extend this flow; from Phases 3-4 the analysis pipeline computes scores and module results from the persisted crawl evidence. Jobs are never marked completed without crawler results.

## Key components

| Component | Responsibility |
|---|---|
| `App\Services\Security\UrlValidator` | submission-time URL validation (syntax, scheme, host policy) |
| `App\Support\UrlNormalizer` | canonical URL generation and hashing |
| `App\Services\Analysis\AnalysisJobService` | job creation, duplicate protection, idempotency |
| `App\Models\AuditLog` | security-relevant event logging |
| Policies (`ProjectPolicy`, `WebsitePolicy`, `AnalysisJobPolicy`) | ownership authorization for every action |

Implemented in Phase 7: global `SecurityHeaders` middleware (bootstrap middleware stack) and the integration-settings layer (`IntegrationSetting` model + `IntegrationController`/`IntegrationApiController`, catalogue in `config/integrations.php`). The `performance_audits` service/table remains deferred (decision in Phase 7): until a real Lighthouse/PageSpeed-style source exists no substitute will be built.

## Data integrity principles

- All user-owned records carry `user_id` plus foreign keys with proper index and cascade rules.
- Websites are deduplicated per project by SHA-256 of the normalized URL.
- Analysis state machine: `pending -> dispatched -> crawling/processing -> completed | failed | cancelled`.
- Extracted evidence, scores, confidence and methodology version will be stored per analysis so future reanalysis and historical comparison are possible (Phase 3+).
- Screenshots and large artifacts will be stored outside the relational database.

## Deployment topology (free hosting, Phase 8)

- Laravel app: Render free web service (Dockerfile deployment, ephemeral filesystem - no permanent files stored locally); Hostless documented as fallback
- Database: TiDB Cloud Starter (MySQL-compatible, TLS, zero spending limit)
- Crawler: GitHub Actions workflow_dispatch jobs, authenticated and bounded

## Phase 2 - crawler pipeline (implemented)

```
analyze (web UI or API)
    -> AnalysisJobService.startAnalysis    (pending job, duplicate protection, audit)
    -> GitHubActionsDispatcher.dispatch    (workflow_dispatch; or honestly stays pending)
    -> crawler workflow (.github/workflows/crawler.yml, private repository)
        -> node crawler/bin/crawl.js --engine playwright
            SSRF guards: hostname/IP allow-list per hop, pinned validated DNS answer for connect,
            redirect revalidation, response-size cap, timeouts
            limits: 20 pages, 5 MB/page, crawl deadline, 1 retry, concurrency <= 5
            extraction: title/meta/headings/CTAs/forms/contacts/trust signals/links
        -> HMAC-signed POST /api/internal/crawler-callback (secret = CRAWLER_CALLBACK_SECRET)
    -> CallbackVerifier (HMAC + timestamp window + nonce) -> CrawlerCallbackService
        -> validation (20-page cap, host match, field limits), terminal-state guard (409)
        -> persist crawled_pages + crawl_errors, job moved to completed, websites.last_analyzed_at
    -> (Phase 3) intelligence services consume the same stored crawl evidence
```

Phase 3 additions: `CrawlerCallbackService` now invokes `AnalysisPipeline.run(job)` after persisting a completed crawl; the pipeline runs `TechnicalAuditService` (8 pass/warn/fail/unknown checks) and `CampaignReadinessService` (7-criterion weighted heuristic Campaign Readiness Score) and persists into `technical_audits`, `marketing_scores` (per-module scoring store) and `campaign_insights` (prioritised findings with evidence). Re-running `php artisan analysis:reprocess <uuid>` regenerates them idempotently. Scores are heuristic, derived only from crawl evidence, and labelled as not validated ML predictions.

Alternative execution without any cloud: `php artisan analysis:run-local --all --engine=http` runs the same Node crawler locally and feeds results through the callback service directly (no HTTP hop), and `php artisan analysis:prune-stale` reaps timed-out pending/dispatched jobs.

Crawler package layout: `crawler/bin/crawl.js` (CLI + signing/POST), `crawler/src/ssrf.js` (IP/hostname policy + DNS validation), `crawler/src/request.js` (pinned-lookup transport + guarded redirect chain), `crawler/src/discovery.js` (robots.txt + sitemap), `crawler/src/extract.js` (marketing feature extraction), `crawler/src/engine-http.js` + `crawler/src/engine-playwright.js` (same JSON contract for both engines), `crawler/test/` (node:test suite).

## Intelligence pipeline (Phases 3-4, implemented)

`AnalysisPipeline.run(job)` executes inside the crawl-persistence transaction and runs seven modules against the same stored evidence (one crawl per analysis; no per-module crawling):

```
CrawlerCallbackService->handleCompleted
    -> persist crawled pages + crawl errors
    -> AnalysisPipeline.run(job)   (errors caught + audited; crawl status never falsified)
        -> TechnicalAuditService      -> technical_audits (upsert)
        -> CampaignReadinessService   -> marketing_scores[module=campaign_readiness] (upsert)
                                      -> campaign_insights (delete + insert)
        -> ConversionOpportunityService -> marketing_scores[module=conversion_opportunity]
                                        -> predictive_insights (delete + insert)
        -> LeadReadinessService       -> marketing_scores[module=lead_intelligence]
                                      -> lead_insights (delete + insert)
        -> WastedSpendAnalysisService -> waste_findings (delete + insert)
        -> CreativeAnalysisService    -> creative_insights (delete + insert)
        -> MarketingAdvisorService    -> marketing_scores[module=marketing_advisor] (upsert)
                                      -> recommendations (delete + insert)
```

All scores are stored in the shared `marketing_scores` table keyed by `(analysis_job_id, module_key)` with methodology version, breakdown JSON and confidence. `BudgetCalculationService` is on-demand only (no cron/pipeline work): users submit budget assumptions through the API or the Budget screen and results are persisted to `budget_scenarios` as clearly-labelled deterministic simulations.

The advisor (`MarketingAdvisorService`) always runs last: it reads the other six modules' persisted results through an injected `AiProvider` (`config/advisor.php`, default `RuleBasedAdvisor` `rule_based_v1`), consolidates the findings into topic buckets and emits at most 10 prioritised `recommendations` rows plus an Advisor Priority Score (100 - mean of known module scores). Swapping in an external AI provider means binding another `AiProvider` implementation; the shipped engine is fully deterministic and never calls any AI service.

Module results are exposed through `GET /api/websites/{id}/{predictive-analytics|lead-intelligence|wasted-spend|creative-intelligence|marketing-advisor}` plus the Phase 3 `campaign-intelligence` endpoint, and through seven UI screens reached via module tabs on the website page (`resources/views/websites/modules/`, tabs partial in `resources/views/websites/partials/`). Screens without a completed analysis show an honest empty state; persisted rows are ordered by priority/severity.

## Reports and history (Phase 6, implemented)

`ReportGenerationService` snapshots a completed analysis into a JSON payload (five module scores, campaign criteria, technical checks, advisor action plan, all module findings, crawl stats, disclaimer) and renders it persistently:

```
snapshot(job) -> payload
    -> renderPdf (dompdf v3, real PDF)  or renderCsv (fputcsv, sectioned)
    -> Storage local 'reports/<uuid>.(pdf|csv)' -> reports row (metadata + payload)

download -> ensureFile: file kept on disk, or deterministically rebuilt from the stored
           payload/snapshot when the ephemeral filesystem dropped it
```

Historical comparison comes from `history(website)`: the last 12 completed jobs chronologically with all five module scores (`technical_audit` from `technical_audits`, the rest from `marketing_scores`). The Reports screen renders it with Chart.js (vendored locally at `public/vendor/chart.umd.min.js` - no CDN calls) and the chart is explicitly labelled as heuristic crawl-evidence scores, not marketing performance. Access: `GET/POST /api/websites/{id}/reports`, `GET /api/reports/{id}` + `/download` (Sanctum; ownership via user_id + website policy), web equivalents under `websites.reports.*`.
