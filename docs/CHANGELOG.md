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

## Phase 4.1 - Documentation reorganisation

- All 11 required documentation files moved from the repository root into `docs/` (git history preserved); no content changed and doc-to-doc links remain valid

## Phase 5 - AI Marketing Advisor (Module 7)

- `AiProvider` interface + default `RuleBasedAdvisor` engine (`rule_based_v1`, bound via `config/advisor.php` -> `ADVISOR_ENGINE`; an external LLM/ML provider can replace it by binding another `AiProvider` implementation) + `MarketingAdvisorService` orchestrator
- The advisor consolidates already-persisted module results (four module scores, campaign insights, predictive insights, lead insights, waste findings, technical audit fail/warn checks) into a prioritised action plan: keyword-based topic buckets, cross-module de-duplication with merged evidence, priority ranking, cap of 10 recommendations with sequential positions; executive summary with the real score list and explicit heuristic/disclaimer statements
- Advisor Priority Score (`100 - round(mean(known module scores))`) stored in `marketing_scores` under `module_key = marketing_advisor`, documented as a simple aggregation and never a performance metric; engine_note and disclaimer stored verbatim in the breakdown
- New `recommendations` table (migrated): recommendation_key, priority, title, rationale, evidence, action, effort, expected_impact, confidence, module_source, position, source; rows replaced on rerun (idempotent, advisor runs last in the pipeline)
- API: `GET /api/websites/{id}/marketing-advisor` (auth + ownership, honest `data: null` without a completed analysis)
- UI: AI Advisor screen (priority score + module score cards, executive summary, prioritised action plan) on a new AI Advisor tab; campaign page links to it; the "coming in Phase 5" placeholder span was removed
- Tests: 14 new (206 Laravel total, 751 assertions) + 21 crawler node tests, all passing; live reprocess on the stored books.toscrape.com crawl: Priority score 61, 32 module findings consolidated into 10 prioritised recommendations (top: mobile_usability drawing on 4 modules); identical output on rerun
- Fixed in this phase (caught by the new cap test): topic-distinct keys were collapsed into one topic because the slug regex stripped digits

## Phase 6 - Reports, Charts and Historical Comparison

- `ReportGenerationService` (v1.0): snapshot builder over a completed analysis (five module scores, campaign criteria, technical checks, advisor action plan, all module findings, crawl stats, disclaimers); deterministic PDF rendering via **dompdf** (real PDF-1.7 files) and CSV via fputcsv with clearly-labelled sections; report metadata + the full snapshot persisted in the new `reports` table (uuid, format pdf|csv, status, path, filename, size_bytes, payload JSON, generator, version)
- Download resilience: `ensureFile` regenerates a missing file deterministically from the stored snapshot (ephemeral-filesystem safety for Render free tier); binary-expired reports flip to `expired` with an honest message instead of pretending to download
- Historical comparison: `history()` returns the last 12 completed jobs chronologically with all five module scores (technical audit score pulled from `technical_audits`); rendered as a Chart.js line chart (local, vendored at `public/vendor/chart.umd.min.js`, no CDN dependency) plus an on-page score-history list
- Dependency: `dompdf/dompdf ^3.1` added (PDF generation is real, no paid services)
- API: `GET/POST /api/websites/{id}/reports` (list latest 25 / generate with FormRequest-validated format pdf|csv, POST rate-limited 20/min, honest 422 without a completed analysis), `GET /api/reports/{id}` (metadata), `GET /api/reports/{id}/download` (streams with correct content type)
- Web: Reports screen with generate buttons, Chart.js score-history chart, stored-reports table with downloads and honest empty states; new Reports tab; ownership enforced on every endpoint
- Tests: 19 new (225 Laravel total, 843 assertions) + 21 crawler node tests, all passing; live verification against the books.toscrape.com 20-page analysis produced a valid 14 KB PDF and a 85-line CSV containing the real module scores (45/69/40/0/61) and disclaimers


## Phase 7 - Security/QA audit and integration settings

- Security audit executed across the whole codebase: authorization policy coverage, mass assignment (`$fillable` on all models), unauthenticated access to Sanctum routes, crawler-callback HMAC hardness, SSRF defenses, session cookie defaults, debug-mode guidance - findings recorded in `docs/SECURITY.md`
- Fixes: global `SecurityHeaders` middleware (nosniff, frame-deny, referrer policy, permissions policy on every response incl. API and `/up`); `tests/Feature/ApiTest.php` moved to `tests/Feature/Api/ApiTest.php` fixing a PSR-4 autoload violation
- Dependencies: `composer update` moved `symfony/http-kernel`, `symfony/mailer`, `symfony/routing` to v7.4.24 - `composer audit` is now clean (was 5 advisories)
- Integration settings (honest by design): `config/integrations.php` catalogue (Google Ads, Meta Ads, GA4, Google Search Console), `integration_settings` table (migrated), `IntegrationSetting` model with `encrypted:json` credentials, API (`GET/PUT/DELETE /api/integrations...`) and a new Integrations screen with sidebar link; saving credentials stores them encrypted and explicitly does **not** connect to anything - statuses are `not_connected` / `credentials_saved` only, flagged `live_connection: false`, `data_fetched: false`
- `performance_audits`: decision recorded - deferred until a real Lighthouse/PageSpeed-style data source exists; no heuristic stand-in will be built
- Tests: 16 new (241 Laravel total, 924 assertions: Feature 126/495 + Unit 115/429) + 21 crawler node tests, all passing
