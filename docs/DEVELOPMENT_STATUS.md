# DEVELOPMENT_STATUS

Last updated: Phase 5 completion.

## Current phase

**PHASE 5 - MODULE 7 AI MARKETING ADVISOR (complete).** Phases 6-8 not started.

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

## Incomplete features (planned phases - never faked in UI)

- `performance_audits` table deferred until a Lighthouse/PageSpeed or similar data source is available (Phase 2 crawl timing is used as a relative indicator inside the technical audit in the meantime)
- External AI provider (LLM) for the advisor: interface + config are ready, but nothing external calls any AI service today; the shipped engine is the deterministic rule-based one
- Phase 6: charts (Chart.js), historical comparison, PDF/CSV reports
- Phase 7: security/QA audit, crawler stress tests
- Phase 8: free hosting deployment (Render + TiDB + GitHub Actions)

## Tests executed

- Laravel: `php artisan test` - **206 passed, 0 failed** (751 assertions), SQLite in-memory, PHP 8.3.22, Laravel 12 (Unit: 108/387, Feature: 98/364)
- Crawler: `node --test crawler/test/crawler.test.js` - **21 passed, 0 failed**, Node v22.23.5
- Live Phase 5 advisor reprocessing (executed, twice for idempotency): `analysis:reprocess` against the stored books.toscrape.com 20-page crawl kept all previous scores and added Priority score 61 (100 - mean(45,69,40,0)) and 10 prioritised recommendations consolidated from 32 module findings (#1 mobile_usability drawing on 4 modules, then metadata_quality, lead_capture, cta_effectiveness, trust_signals, contact_paths, then medium/low topics); identical output on the second run (4 scores, 10 recommendations, same leading topic)
- Live Phase 4 reprocessing (executed, twice for idempotency): `php artisan analysis:reprocess` against the stored books.toscrape.com 20-page crawl produced Campaign Readiness 45, Technical audit 69, Conversion opportunity 40, Lead readiness 0 (correct: the store has no forms or contact links), 8 insights, 7 waste findings, 9 creative items; row counts identical across both runs (3 marketing scores, 10 predictive, 7 waste, 9 creative); spot-checked evidence matches real crawl data (H1 "All products", no viewport meta, no lead forms), confidence high
- Live Phase 3 reprocessing (executed): `php artisan analysis:reprocess` against the same crawl produced Campaign Readiness 45/100 and Technical audit 69/100 with 8 insights; spot-checked evidence strings match real crawl data, confidence high
- Live end-to-end (executed in this environment): registered a user via real forms over local HTTP, created a project + website, started an analysis through the real job service (dispatch honestly unconfigured -> pending), ran `php artisan analysis:run-local --all`, the crawler fetched books.toscrape.com (20 pages, bounded), the callback service persisted pages/errors/features, job became `completed`, `websites.last_analyzed_at` updated
- Live SSRF checks (executed): loopback 127.0.0.1, private 10.0.0.1 and link-local 169.254.169.254 -> `blocked_ip`; `metadata.google.internal` and `*.localhost` -> `blocked_hostname`; non-existent host -> structured `dns_failure`
- Live positive crawls: example.com (1 page), books.toscrape.com (6 pages, depth ordering correct)
- Signed-callback security cases (forgery, replay, tampering, expired timestamp, unknown job) are covered by automated feature tests

Details in [TESTING.md](TESTING.md).

## Known bugs

- None open. (Fixed during Phase 2: worker-pool early-exit race in BFS; `WebsiteFactory` ignored explicit `url` overrides; nested-heading arrays crashed callback persistence; fake-host DNS in redirect tests. Fixed during Phase 3: integer division made `$share === 1.0` false for perfect sites, so healthy sites scored "warn" instead of "pass" - shares are now cast to float. Fixed during Phase 4: missing `WasteFinding` import in the waste service; skip behavioural quirk - web module view needed `@include` partials, not anonymous component tags. Fixed during Phase 5: advisor topic slug stripped digits from keys, collapsing distinct unrecognised keys into a single topic.)
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
