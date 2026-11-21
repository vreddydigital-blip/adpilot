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
5. From Phase 2, dispatch to GitHub Actions and callback persistence extend this flow. Jobs are never marked completed without crawler results.

## Key components

| Component | Responsibility |
|---|---|
| `App\Services\Security\UrlValidator` | submission-time URL validation (syntax, scheme, host policy) |
| `App\Support\UrlNormalizer` | canonical URL generation and hashing |
| `App\Services\Analysis\AnalysisJobService` | job creation, duplicate protection, idempotency |
| `App\Models\AuditLog` | security-relevant event logging |
| Policies (`ProjectPolicy`, `WebsitePolicy`, `AnalysisJobPolicy`) | ownership authorization for every action |

Planned services (Phase 2+): `WebsiteCrawlService`, `PageExtractionService`, `TechnicalAuditService`, `MarketingAnalysisService`, `CampaignScoringService`, `ConversionOpportunityService`, `BudgetCalculationService`, `WastedSpendAnalysisService`, `LeadReadinessService`, `CreativeAnalysisService`, `MarketingAdvisorService`, `ReportGenerationService`, `CrawlerDispatcherService`, `CrawlerCallbackService`.

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
    -> (Phase 3+) intelligence services consume the same stored crawl evidence
```

Alternative execution without any cloud: `php artisan analysis:run-local --all --engine=http` runs the same Node crawler locally and feeds results through the callback service directly (no HTTP hop), and `php artisan analysis:prune-stale` reaps timed-out pending/dispatched jobs.

Crawler package layout: `crawler/bin/crawl.js` (CLI + signing/POST), `crawler/src/ssrf.js` (IP/hostname policy + DNS validation), `crawler/src/request.js` (pinned-lookup transport + guarded redirect chain), `crawler/src/discovery.js` (robots.txt + sitemap), `crawler/src/extract.js` (marketing feature extraction), `crawler/src/engine-http.js` + `crawler/src/engine-playwright.js` (same JSON contract for both engines), `crawler/test/` (node:test suite).
