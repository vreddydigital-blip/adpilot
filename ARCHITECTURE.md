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

- Laravel app: Hostless (Dockerfile deployment, ephemeral filesystem - no permanent files stored locally)
- Database: TiDB Cloud Starter (MySQL-compatible, TLS, zero spending limit)
- Crawler: GitHub Actions workflow_dispatch jobs, authenticated and bounded
