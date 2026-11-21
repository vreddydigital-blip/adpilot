# CHANGELOG

## Phase 1 - Foundation (2026-11-21)

Added:
- Laravel 12 + Breeze authentication scaffold
- Migrations: projects, websites, analysis_jobs, audit_logs
- Models (User relations, Project, Website, AnalysisJob, AuditLog), policies, factories
- UrlValidator + UrlNormalizer services (submission-time SSRF-oriented checks)
- AnalysisJobService (duplicate protection, audit trail)
- Web UI: sidebar layout, dashboard with real counts, project CRUD, website analysis screen with job history
- REST API: projects CRUD, websites, analyze, job status (Sanctum), rate limiting
- Test suite: 85 tests / 263 assertions, all passing
- Documentation set (README, ARCHITECTURE, DATABASE_SCHEMA, API_DOCUMENTATION, ALGORITHM_DOCUMENTATION, SECURITY, TESTING, DEPLOYMENT, FREE_HOSTING_LIMITATIONS, DEVELOPMENT_STATUS, CHANGELOG)

## Phase 2 - Secure crawler

- Node.js crawler package (`crawler/`) with two interchangeable engines (`http` and `playwright`) and one JSON contract
- SSRF-hardened networking: hostname/IP allow-listing, metadata-host blocking, pinned validated DNS answers for connections, per-hop redirect revalidation, response size cap, timeouts, 20-page cap, crawl deadline, bounded concurrency and retries, robots.txt + sitemap.xml discovery
- Marketing-relevant extraction: titles, meta/OG, headings, CTA buttons, lead forms (auth forms excluded), contact methods (email/tel/WhatsApp/booking), trust signals, viewport meta, internal links
- Laravel HMAC callback API (`POST /api/internal/crawler-callback`) with timestamp window, nonce replay protection, strict payload validation (page cap, per-field limits), host-mismatch rejection, terminal-state idempotency (409), and audit trails
- GitHub Actions dispatch service + committed workflow (the token stays app-side; the runner signs its own callback)
- Console commands: `analysis:run-local` (local crawler execution mode) and `analysis:prune-stale` (timeout reaper)
- New schema tables: `crawled_pages`, `page_snapshots`, `page_features`, `crawl_errors` (migrated; snapshots/features-row population targeted for Phase 3)
- Analysis screen now renders real crawl results (pages, stats, errors)
- Tests: 118 Laravel (353 assertions) + 21 crawler node tests, all passing; live 20-page end-to-end crawl executed
- Docs updated for the Render + TiDB Phase 8 target (DEVELOPMENT_STATUS, DEPLOYMENT, FREE_HOSTING_LIMITATIONS, ARCHITECTURE, API_DOCUMENTATION, SECURITY, TESTING)

## Phase 3 - Technical and Marketing Analysis

- Analysis pipeline (`AnalysisPipeline`): after each completed crawl callback, runs the technical audit and campaign-readiness scoring and persists results; idempotent (scores/audits update in place, findings replaced); pipeline errors are caught, audited, and never falsify the crawl status
- `TechnicalAuditService` (v1.0): 8 evidence-backed checks (HTTPS, 2xx coverage, titles present/unique, meta description coverage, mobile viewport tag, observed load time, homepage content depth, crawl errors); pass/warn/fail/unknown with score over known checks only
- `CampaignReadinessService` (v1.0): 7-criterion weighted heuristic Campaign Readiness Score (value proposition 20, CTA effectiveness 20, landing relevance 15, offer clarity 15, lead capture 15, mobile usability 10, trust signals 5); unknown criteria excluded with weight renormalization; heuristic caps below 100; every criterion carries score, weight, evidence, explanation, confidence and a recommendation; UI + API label the score heuristic and "not a validated ML prediction"
- Findings stored in new `campaign_insights` table with priority/evidence/recommendation/effort/direction/confidence; new `technical_audits` and `marketing_scores` tables (migrated; `marketing_scores` is the shared per-module scoring store for later phases)
- New API endpoint `GET /api/websites/{id}/campaign-intelligence` (auth + ownership) and a new console command `analysis:reprocess <uuid>`
- Website page now renders the real Campaign Readiness Score with per-criterion evidence, the technical audit check list, and prioritised findings & recommendations
- Tests: 143 Laravel (460 assertions) + 21 crawler node tests, all passing; live reprocessing of the stored books.toscrape.com crawl executed (Campaign Readiness 45/100, Technical 69/100, 8 insights, evidence spot-checked against real crawl data)
- Fixed in this phase: float-cast crawler share comparison (healthy sites scored "warn" instead of "pass"); duplicate SQLite index in the technical_audits migration
