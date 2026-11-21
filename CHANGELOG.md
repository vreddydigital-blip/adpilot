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

## Phase 4 - Intelligence Modules 2-6

- Four new analysis services (v1.0, all evidence-driven from stored crawl data): `ConversionOpportunityService` (Heuristic Conversion Opportunity Score, severity x impact x confidence normalised to 0-100 with priority buckets, effort and direction per opportunity), `LeadReadinessService` (Lead Capture Readiness Score from parsed forms, contact methods and CTA consistency with explicit unknown handling), `WastedSpendAnalysisService` (conversion-leakage findings with evidence, severity, potential marketing impact and recommended correction; no claim of actual wasted spend without advertising data), `CreativeAnalysisService` (Google Search headlines/descriptions, Meta primary text/headlines, messaging angles and a message-match verdict within platform character limits; no CTR or conversion predictions)
- `BudgetCalculationService` (v1.0, deterministic simulation, clearly labelled as such): estimated clicks, leads, conversions, CPL, CPA, break-even ROAS, revenue scenarios and conservative/expected/best cases with static channel factors; explicitly never represents actual campaign performance
- `AnalysisPipeline` extended to run all six modules per completed analysis; idempotent (scores/audits upserted, module rows replaced on rerun); return payload now carries all four scores plus persisted row counts; Module 7 AI Marketing Advisor remains scheduled for Phase 5 per the phased plan
- Five new tables: `predictive_insights`, `waste_findings`, `lead_insights`, `creative_insights`, `budget_scenarios` (user+website FKs, priority/severity ordering indexes)
- REST API: `GET /api/websites/{id}/predictive-analytics`, `/lead-intelligence`, `/wasted-spend`, `/creative-intelligence` (auth + ownership, honest `data: null` when no completed analysis exists), `GET /api/budget/channels` (static factor table + disclaimer), `GET/POST /api/budget-scenarios` (POST rate-limited 20/min, FormRequest validation)
- Web UI: module tab navigation on the website page, five new screens (Predictive, Budget calculator with saved scenarios and delete, Wasted Spend, Lead Intelligence, Ad Creative) with empty states and ownership-checked scenario creation/deletion
- `analysis:reprocess` output now reports all module scores and stored row counts
- Creative service improvement: brand-identical source text no longer discards the headline candidate (six-word subset is still generated)
- Tests: 49 new (192 Laravel total, 701 assertions) + 21 crawler node tests, all passing; live verification on the stored books.toscrape.com crawl: Campaign 45, Technical 69, Conversion Opportunity 40, Lead Readiness 0 (correct - no forms or contact methods on that store), 8 insights, 7 waste findings, 9 creative rows, confidence high; second reprocess produced identical scores and row counts, confirming idempotency
- Fixed in this phase: missing `WasteFinding` import in `WastedSpendAnalysisService` (fatal at pipeline runtime); integer-typed argument in the API score formatter caught by tests
