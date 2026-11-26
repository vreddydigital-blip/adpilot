# DEVELOPMENT_STATUS

Last updated: Phase 8 completion.

## Current phase

**ALL EIGHT PHASES COMPLETE.** The application is feature-complete; the only outstanding step is executing the first real cloud deployment from the Phase 8 runbook (requires a browser and provider sign-up, which the build sandbox cannot do).

Deployment target decision: **Render (free web service) + TiDB Cloud Starter + GitHub Actions crawler** is the Phase 8 target (user-approved). Hostless remains documented as a fallback.

## Completed features (implemented + verified)

### Phase 1 - Foundation
- Laravel 12 application scaffold with PHP 8.3
- Authentication: registration, login, logout, email verification scaffolding, profile edit/password/delete (Breeze)
- Database schema phase 1: `projects`, `websites`, `analysis_jobs`, `audit_logs`
- Ownership policies across project/website/analysis-job actions (web + API)
- URL validation service (syntax + scheme + host policy) and URL normalization/hashing
- Analysis job service: state constants, duplicate protection, audit entries
- Web UI: sidebar dashboard layout, Overview with real counts, project CRUD, website analysis screen
- REST API: projects CRUD, websites + analyze, jobs status (Sanctum), rate-limited routes
- Audit logging of project/website/job mutations

### Phase 2 - Crawler
- Node.js crawler package (`crawler/`, Node >= 20, cheerio; playwright loaded only if installed):
  - `http` engine: SSRF-hardened fetcher (node http/https with a pinned `lookup` so connections can only use validated DNS answers), manual redirect revalidation per hop, 5 MB body cap, per-request timeout, BFS crawl in bounded batch rounds (max 5 concurrent), max 20 pages, overall deadline, per-page retry (1), same-origin restriction, executable-extension filtering, robots.txt parsing (Disallow/Allow/Sitemap) and sitemap.xml discovery (max 50 URLs)
  - `playwright` engine: Chromium via guarded require, navigation timeout, same-origin + robots checks, optional desktop screenshots via `--screenshot-dir`; requires installing the playwright package + chromium (used by the GitHub Actions workflow)
  - Extraction: title, meta description/keywords/OpenGraph, h1-h3 headings, text length, CTA buttons (label patterns), lead forms (field count/required/submit; password forms excluded), contact methods (mailto/tel/WhatsApp/booking links), trust signals (testimonials, guarantees, privacy/terms, ...), mobile viewport meta, navigation, internal links
  - CLI (`crawler/bin/crawl.js`): `--url --job-id --engine --max-pages --max-duration-ms --page-timeout-ms --output|--callback-url --screenshot-dir`; a crawl that cannot fetch any page (e.g. DNS failure) reports `failed` with the structured reason
  - HMAC-signed callback POST mode matching the Laravel verifier (`X-AdPilot-Timestamp` + `X-AdPilot-Signature` over `timestamp.rawBody`)
- Laravel callback API: `POST /api/internal/crawler-callback` (no auth:sanctum; HMAC + timestamp tolerance 300 s + nonce replay cache + job lookup by UUID + host-mismatch check + 20-page cap validator)
- Crawler callback service: terminal-state guard (409), started/progress -> crawling, completed -> transaction insert of `crawled_pages`/`crawl_errors` + job completed stats + `websites.last_analyzed_at`, failed -> job failed
- GitHub Actions dispatcher (config-driven workflow_dispatch); unconfigured -> job honestly stays pending; failure -> stays pending with audit log
- Local crawler execution mode: `php artisan analysis:run-local --job=<uuid>|--all [--engine=http|playwright]`
- Stale job reaper: `php artisan analysis:prune-stale [--minutes=]` (default 30) marks pending/dispatched jobs failed with reason `timeout`
- Crawling schema tables: `crawled_pages`, `page_snapshots`, `page_features`, `crawl_errors` (migrated)
- UI: analysis screen now shows latest crawl results (page table with title/HTTP/depth/text length/load time), crawl errors, and a real modules-pending notice
- GHA workflow `.github/workflows/crawler.yml` (workflow_dispatch with job_id/target_url/callback_url inputs; installs playwright + chromium; the runner POSTs the HMAC-signed callback itself so no token appears in logs)

### Phase 3 - Technical and Marketing Analysis
- Analysis pipeline (`AnalysisPipeline`): after every completed crawl callback, runs the technical audit + campaign readiness scoring, persists results, and is re-runnable via `php artisan analysis:reprocess <uuid>` (idempotent: audit/score rows update in place, insights are replaced)
- Technical audit service: 8 evidence-backed checks (HTTPS on entry page, 2xx response coverage, titles present/unique, meta description coverage, mobile viewport tag, observed load time, homepage content depth, crawl errors); unknown checks are excluded from the score with weights renormalized over known checks
- Campaign readiness service (Module 1 scoring engine): 7 weighted criteria (value proposition 20%, CTA effectiveness 20%, landing-page relevance 15%, offer clarity 15%, lead capture readiness 15%, mobile usability 10%, trust signals 5%); unknown criteria excluded with weight renormalization; heuristic caps (< 100) because visual/behavioural data is unavailable from URL-only crawls; every criterion carries score/weight/evidence/explanation/confidence/recommendation
- Findings: campaign + technical insights stored in `campaign_insights` with priority (high/medium/low), evidence, recommendation, effort, direction, confidence; priority derived from criterion score + weight
- Schema tables (migrated): `technical_audits`, `marketing_scores` (per-module scoring store for later phases), `campaign_insights`
- API: `GET /api/websites/{id}/campaign-intelligence` (auth:sanctum + ownership policy) returns persisted campaign readiness, technical audit and insights, or an honest "no completed analysis yet"
- UI: website page now renders the real Campaign Readiness Score with per-criterion breakdown and evidence, the technical audit check list (pass/warn/fail/unknown), and prioritised findings & recommendations
- Methodology honesty: all scores labelled heuristic, URL-only evidence, "not a validated ML prediction"

### Phase 4 - Modules 2-6 (backend + API + UI)
- Pipeline extended: after every completed crawl the `AnalysisPipeline` now also runs the four new intelligence services inside its transaction: ConversionOpportunityService (Module 2), LeadReadinessService (Module 5), WastedSpendAnalysisService (Module 4) and CreativeAnalysisService (Module 6); all persist idempotently (scores update in place; insight/finding/creative rows are replaced per job) and the whole block is re-runnable via `php artisan analysis:reprocess` (verified live: two consecutive runs produce identical scores and row counts)
- Module 2 Predictive Analytics: 10 opportunities (CTA placement, form complexity, content clarity, value proposition, navigation, landing relevance, mobile usability, trust signals, contact accessibility, page performance) scored `severity x impact x confidence / 25 x 100`, priority thresholds 50/25, unknown evidence excluded from the aggregate; stored in `predictive_insights` + `marketing_scores` (`conversion_opportunity`)
- Module 3 Budget Intelligence: `BudgetCalculationService` with the exact master-prompt formulas (clicks = budget/CPC, leads, conversions, CPL/CPA, break-even ROAS = 1/markup margin, revenue, profit), three scenario cases (conservative/expected/best factor table) and three channel assumption sets (search/social/display); interactive calculator UI with validation; scenarios persisted per user+website in `budget_scenarios` with methodology `deterministic simulation` + disclaimer everywhere
- Module 4 Wasted Spend: findings derived only from already-computed Phase 3 results (technical check failures/degradations + low campaign criteria with severity escalation), stored in `waste_findings` with potential impact + correction; explicit disclaimer that actual wasted spend requires ad-cost/conversion integrations
- Module 5 Lead Intelligence: parses the crawler's form evidence ("N-field get|post form") into structured form data, scores form/lead-contact presence (cap 95), stores insights (no forms, form friction > 4 fields, missing contact channels, homepage CTA consistency, contact page) in `lead_insights` + `marketing_scores` (`lead_intelligence`)
- Module 6 Ad Creative Intelligence: Google Search headlines (30 chars), Google descriptions (90), Meta headlines (40), Meta primary text (125) generated deterministically from the site's own titles/headings/descriptions/CTA labels with word-boundary fitting; messaging angles from detected offer words; token-overlap message-match verdict explicitly labelled as not predicting CTR; stored in `creative_insights`
- Schema tables (migrated): `predictive_insights`, `waste_findings`, `lead_insights`, `creative_insights`, `budget_scenarios`
- API: `GET /api/websites/{id}/predictive-analytics`, `/lead-intelligence`, `/wasted-spend`, `/creative-intelligence` (ownership-enforced, honest `data: null` without a completed job), `GET /api/websites/{id}/budget-intelligence` + `/budget-scenarios` (saved scenarios), `POST /api/websites/{id}/budget-scenarios` (validated inputs -> simulation -> persisted scenario, channel factors applied), `GET /api/budget-channels`
- UI: module tab navigation on every website page; five working per-website screens (Predictive Analytics, Budget Intelligence with save/delete scenario forms, Wasted Spend, Lead Intelligence, Ad Creative) with empty states; website overview placeholder note updated (AI advisor coming in Phase 5)
- Tests: 49 new tests over the five services, pipeline persistence, module APIs and module pages (192 total for the Laravel app)

### Phase 5 - Module 7 AI Marketing Advisor (backend + API + UI)
- `AiProvider` interface + `RuleBasedAdvisor` (`rule_based_v1`) + `MarketingAdvisorService`; external AI providers can be swapped in by binding another `AiProvider` implementation via `config/advisor.php` (`ADVISOR_ENGINE`; unknown engines fall back to `rule_based`)
- The advisor reads only already-persisted results for the job - the four module scores, the technical audit's fail/warn checks with a recommendation, and the row-level findings from `campaign_insights`, `predictive_insights`, `lead_insights`, `waste_findings` - no re-crawl, no advertising/revenue data
- Findings are mapped to topic buckets (lead capture, contact paths, CTA effectiveness, mobile usability, metadata quality, technical health, load performance, content depth, trust signals, offer clarity, messaging, other), consolidated with de-duplicated evidence (max 4 lines) and ranked by best contributing priority then finding count descending; at most 10 recommendations with positions 1..n
- Advisor Priority Score persisted in `marketing_scores` (`module_key = marketing_advisor`, methodology `rule_based`): `100 - round(mean(known module scores))`, documented as a simple aggregation and explicitly not a marketing performance metric; honest engine_note ("not a large language model...not a validated ML prediction") and never-forecast disclaimer stored in the breakdown
- Persistence: `recommendations` table (recommendation_key, priority, title, rationale, evidence, action, effort, expected_impact, confidence, module_source, position, source) with delete-then-insert idempotency; pipeline runs the advisor last so it reads the other modules' persisted results
- API: `GET /api/websites/{id}/marketing-advisor` (auth + ownership, honest `data: null` without a completed job)
- UI: AI Advisor screen (priority score + four module score cards, executive summary with engine/priority badges, prioritised action plan cards with rationale/evidence/action/module sources) reached from the new AI Advisor tab; the campaign page now links to it
- Tests: 14 new tests (206 total for the Laravel app; Feature 98/364, Unit 108/387)

### Phase 6 - Reports, Chart.js score history and PDF/CSV export (backend + API + UI)
- Dependencies: `dompdf/dompdf ^3.1` for PDF rendering (smoke-tested real PDF output); Chart.js 4.4.7 UMD vendored locally at `public/vendor/chart.umd.min.js` (no CDN dependency at runtime)
- Schema: `reports` table (user/website/analysis-job FKs, uuid, title, format, status, path, filename, size_bytes, payload JSON snapshot, generator, version; (user_id, created_at) + (website_id, created_at) indexes); `Report` model with PDF/CSV format and `ready` status constants
- `ReportGenerationService`: builds a full evidence snapshot (website/job meta, the five module scores incl. the technical audit read from `technical_audits`, campaign criteria, technical checks, advisor recommendations, all findings), renders `reports/pdf.blade.php` via dompdf (A4, `isRemoteEnabled = false`) or CSV via fputcsv - both carry the honesty disclaimer and never contain real advertising metrics; stores the file on the `local` disk and persists the payload JSON so a deleted/expired file can be regenerated from the snapshot (`ensureFile()`), or flips the report to status `expired` with an honest message
- Score history: last 12 completed jobs in chronological order with all five module scores (campaign_readiness, technical_audit, conversion_opportunity, lead_intelligence, marketing_advisor)
- API: `GET/POST /api/websites/{id}/reports`, `GET /api/reports/{report}`, `GET /api/reports/{report}/download` (ownership-enforced, honest 422 without a completed analysis, generation failures audited as `report.generation_failed`)
- UI: new Reports tab (8th module tab) with pdf/csv generate buttons, a Chart.js score-history line chart fed by embedded history data (labelled "Not a marketing performance chart - all values are heuristic crawl-evidence scores"), and a stored-reports table with download links and an ephemeral-filesystem note
- Tests: 19 new tests (225 total for the Laravel app; Feature 115/414, Unit 110/429): 7 unit (snapshot contents, PDF magic bytes, CSV sections, pending-job/format guards, ensureFile regeneration, two-job chronological history), 7 API feature (store/index/show/downloads, 422 without a completed analysis, object-level 403 + 401, format validation), 5 web-page feature (screen render, empty state, csv generation flash + download, cross-user 403, auth redirect)
- Live verification: generated PDF (14,047 bytes with real `%PDF-` magic) and CSV (85 lines) from the real books.toscrape.com 20-page analysis; the CSV carries the real scores technical_audit 69 / campaign_readiness 45 / conversion_opportunity 40 / lead_intelligence 0 / marketing_advisor 61 with methodology rows (incl. `rule_based`); `history()` returns all five scores; regeneration after file deletion via `ensureFile()` verified

### Phase 7 - Security/QA audit + integration settings (backend + API + UI)
- Security/QA audit executed across the codebase; findings and fixes recorded in `docs/SECURITY.md`: authorization policy coverage everywhere (policies + explicit owner checks), `$fillable` on all 19 models, Sanctum 401 walls, HMAC crawler-callback hardness (8 test cases), SSRF defenses (validator + crawler-runtime checks, live-verified in Phase 2), session cookie defaults, debug-mode guidance
- Fixes from the audit: global `SecurityHeaders` middleware (X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy on every response incl. API + `/up`); `tests/Feature/ApiTest.php` moved to `tests/Feature/Api/ApiTest.php` (PSR-4 fix)
- Dependencies: `composer update symfony/http-kernel symfony/mailer symfony/routing` -> all three at v7.4.24; `composer audit` now reports zero advisories
- Integration settings (honest by design): `config/integrations.php` catalogue (Google Ads, Meta Ads, GA4, Search Console), `integration_settings` table (migrated), `IntegrationSetting` model with `encrypted:json` credentials, API (`GET /api/integrations`, `PUT /api/integrations/{provider}`, `DELETE /api/integrations/{provider}`) and an Integrations screen in the sidebar; credentials are stored encrypted and never echoed back or logged; statuses only `not_connected` / `credentials_saved` with `live_connection: false`, `data_fetched: false`
- `performance_audits` decision: deferred until a real Lighthouse/PageSpeed-style data source exists; no heuristic stand-in will be built
- Tests: 16 new (241 total for the Laravel app; Feature 126/495, Unit 115/429)

### Phase 8 - Deployment artifacts + runbook (Render + TiDB + GitHub Actions)
- Commit: Dockerfile (PHP 8.3 production container with pdo_mysql/bcmath/opcache/zip, no-dev composer install, boot-time config/route/view cache + `migrate --force`, `php artisan serve` on `$PORT`), `.dockerignore`, `render.yaml` Blueprint (`runtime: docker`, `plan: free`, `healthCheckPath: /up`, production env with APP_DEBUG=false, SESSION_SECURE_COOKIE=true, database session/cache/queue, `sync: false` for secrets)
- `TRUSTED_PROXIES` middleware support in `bootstrap/app.php` (Render TLS termination -> correct client IPs/https scheme), verified by `TrustedProxyTest`
- `docs/DEPLOYMENT.md` finalised: step-by-step no-card runbook (TiDB Cloud Starter, Render Blueprint deploy, the single required GitHub repository secret, first end-to-end browser check, troubleshooting pending jobs), complete production env matrix, backup/export path, wallet-safe guardrails, Hostless/local fallback
- `docs/FREE_HOSTING_LIMITATIONS.md`: fill-in verification checklist for the first real deploy
- Tests: 3 new (244 total for the Laravel app; Feature 129/498, Unit 115/429)

## Outstanding (first real deployment - not executable from this sandbox)

Docker builds, Render account creation, TiDB cluster creation and the end-to-end browser check require a browser and provider sign-up; the build sandbox has none of these. Follow `docs/DEPLOYMENT.md` and record the results in `docs/FREE_HOSTING_LIMITATIONS.md`.

## Incomplete features (planned phases - never faked in UI)

- `performance_audits` table deferred until a Lighthouse/PageSpeed or similar data source is available (Phase 2 crawl timing is used as a relative indicator inside the technical audit in the meantime)
- External AI provider (LLM) for the advisor: interface + config are ready, but nothing external calls any AI service today; the shipped engine is the deterministic rule-based one
- Integration execution: credential storage exists, but no provider connector fetches real data yet (nothing is fabricated in the meantime; advertised metrics stay marked as requiring integrations)
- `performance_audits` stays deferred until a real Lighthouse/PageSpeed-style data source exists (Phase 7 decision: no heuristic stand-in)
- First real cloud deployment (Render + TiDB + GitHub Actions): artifacts + runbook complete, live deployment pending owner execution

## Tests executed

- Laravel: `php artisan test` - **241 passed, 0 failed** (924 assertions), SQLite in-memory, PHP 8.3.22, Laravel 12 (Unit: 115/429, Feature: 126/495)
- Crawler: `node --test crawler/test/crawler.test.js` - **21 passed, 0 failed**, Node v22.23.5
- Live Phase 8 verification (executed): trusted-proxy behaviour covered by real HTTP tests (forwarded + direct IP audit rows); `TRUSTED_PROXIES` wired through `bootstrap/app.php` with env-gating so a bare local install is unchanged; Docker/Render/TiDB execution explicitly out of sandbox reach (documented in DEPLOYMENT.md + FREE_HOSTING_LIMITATIONS.md)
- Live Phase 7 verification (executed): `composer audit` clean after the symfony update; security headers present on web/API/health responses (also asserted by tests); integration settings exercised by HTTP tests including encryption-at-rest (raw DB rows contain no plaintext credential), audit-log credential-leak assertions and blank-field-keeps-old-value behaviour
- Live Phase 6 report generation (executed): PDF and CSV generated via the real UI-equivalent service path against the stored books.toscrape.com 20-page analysis - PDF 14,047 bytes starting with `%PDF-`, CSV 85 lines with the SECTION layout, the five real score rows (technical_audit 69, campaign_readiness 45, conversion_opportunity 40, lead_intelligence 0, marketing_advisor 61), methodology rows (`rule_based` advisor) and the honesty disclaimer; score history returned all five modules for the completed jobs; `ensureFile()` regenerated a report after its stored file was deleted; Report generation is idempotent-safe (new report row per request, snapshot reproducible)
- Live Phase 5 advisor reprocessing (executed, twice for idempotency): `analysis:reprocess` against the stored books.toscrape.com 20-page crawl kept all previous scores and added Priority score 61 (100 - mean(45,69,40,0)) and 10 prioritised recommendations consolidated from 32 module findings (#1 mobile_usability drawing on 4 modules, then metadata_quality, lead_capture, cta_effectiveness, trust_signals, contact_paths, then medium/low topics); identical output on the second run (4 scores, 10 recommendations, same leading topic)
- Live Phase 4 reprocessing (executed, twice for idempotency): `php artisan analysis:reprocess` against the stored books.toscrape.com 20-page crawl produced Campaign Readiness 45, Technical audit 69, Conversion opportunity 40, Lead readiness 0 (correct: the store has no forms or contact links), 8 insights, 7 waste findings, 9 creative items; row counts identical across both runs (3 marketing scores, 10 predictive, 7 waste, 9 creative); spot-checked evidence matches real crawl data (H1 "All products", no viewport meta, no lead forms), confidence high
- Live Phase 3 reprocessing (executed): `php artisan analysis:reprocess` against the same crawl produced Campaign Readiness 45/100 and Technical audit 69/100 with 8 insights; spot-checked evidence strings match real crawl data, confidence high
- Live end-to-end (executed in this environment): registered a user via real forms over local HTTP, created a project + website, started an analysis through the real job service (dispatch honestly unconfigured -> pending), ran `php artisan analysis:run-local --all`, the crawler fetched books.toscrape.com (20 pages, bounded), the callback service persisted pages/errors/features, job became `completed`, `websites.last_analyzed_at` updated
- Live SSRF checks (executed): loopback 127.0.0.1, private 10.0.0.1 and link-local 169.254.169.254 -> `blocked_ip`; `metadata.google.internal` and `*.localhost` -> `blocked_hostname`; non-existent host -> structured `dns_failure`
- Live positive crawls: example.com (1 page), books.toscrape.com (6 pages, depth ordering correct)
- Signed-callback security cases (forgery, replay, tampering, expired timestamp, unknown job) are covered by automated feature tests

Details in [TESTING.md](TESTING.md).

## Known bugs

- None open. (Fixed during Phase 2: worker-pool early-exit race in BFS; `WebsiteFactory` ignored explicit `url` overrides; nested-heading arrays crashed callback persistence; fake-host DNS in redirect tests. Fixed during Phase 3: integer division made `$share === 1.0` false for perfect sites, so healthy sites scored "warn" instead of "pass" - shares are now cast to float. Fixed during Phase 4: missing `WasteFinding` import in the waste service; skip behavioural quirk - web module view needed `@include` partials, not anonymous component tags. Fixed during Phase 5: advisor topic slug stripped digits from keys, collapsing distinct unrecognised keys into a single topic. Fixed during Phase 6: `history()` initially returned `technical_audit: null` because the audit score lives in `technical_audits`, not `marketing_scores`; it now reads `TechnicalAudit` rows directly.)
- One transient, non-reproducible failure occurred in a single isolated Feature-suite run during Phase 3 development (test not captured before re-run); 6 subsequent full/isolated runs all pass. Being watched; no code suspect identified yet.

## Known limitations (honest)

- The callback endpoint protects with HMAC + timestamp + nonce; it returns no job detail and is not user-facing.
- The crawler-only callback mode signs with `CRAWLER_CALLBACK_SECRET` from runner secrets; the GitHub token is used only by the dispatcher and is never passed to the crawler or logged.
- Dispatcher requires `GITHUB_TOKEN`, `GITHUB_REPO` (private repo works) and `CRAWLER_CALLBACK_BASE_URL`; without them jobs stay pending (displayed as pending, never as completed).
- Playwright engine not exercised in this sandbox (Chromium download blocked by the environment proxy); the `http` engine was exercised live instead. The GHA workflow pins a fixed playwright version for the CI engine.
- Screenshots are optional (`--screenshot-dir`) and are not sent in the callback payload; `page_snapshots` therefore stays empty in this phase.
- Scores are heuristic and derived only from crawl evidence (metadata, headings, feature presence/counts, timings); they are explicitly labelled as such in UI and API, and no real advertising metrics (spend, CPC, CPA, CR, ROAS) exist anywhere in the system yet.
- No paid services are used or required by anything built so far.

## Deployment status

Not deployed. Phase 8 will deploy to Render + TiDB (see [DEPLOYMENT.md](DEPLOYMENT.md) and [FREE_HOSTING_LIMITATIONS.md](FREE_HOSTING_LIMITATIONS.md)).

## Remaining work

See "Incomplete features" above and [ARCHITECTURE.md](ARCHITECTURE.md) for the phased plan.
