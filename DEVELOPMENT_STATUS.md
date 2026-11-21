# DEVELOPMENT_STATUS

Last updated: Phase 2 completion.

## Current phase

**PHASE 2 - SECURE CRAWLER (complete).** Phases 3-8 not started.

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

## Incomplete features (planned phases - never faked in UI)

- Phase 3: technical audit, mobile readiness, marketing analysis, Campaign Readiness Score (schema tables `technical_audits`, `performance_audits` still to come; `page_features` rows will be populated during inference)
- Phase 4: seven intelligence modules with UI screens + budget calculators
- Phase 5: AI Marketing Advisor (rule-based + optional external AI)
- Phase 6: charts, historical comparison, PDF/CSV reports
- Phase 7: security/QA audit, crawler stress tests
- Phase 8: free hosting deployment (Render + TiDB + GitHub Actions)

## Tests executed

- Laravel: `php artisan test` - **118 passed, 0 failed** (353 assertions), SQLite in-memory, PHP 8.3.22, Laravel 12
- Crawler: `node --test crawler/test/crawler.test.js` - **21 passed, 0 failed**, Node v22.23.5
- Live end-to-end (executed in this environment): registered a user via real forms over local HTTP, created a project + website, started an analysis through the real job service (dispatch honestly unconfigured -> pending), ran `php artisan analysis:run-local --all`, the crawler fetched books.toscrape.com (20 pages, bounded), the callback service persisted pages/errors/features, job became `completed`, `websites.last_analyzed_at` updated
- Live SSRF checks (executed): loopback 127.0.0.1, private 10.0.0.1 and link-local 169.254.169.254 -> `blocked_ip`; `metadata.google.internal` and `*.localhost` -> `blocked_hostname`; non-existent host -> structured `dns_failure`
- Live positive crawls: example.com (1 page), books.toscrape.com (6 pages, depth ordering correct)
- Signed-callback security cases (forgery, replay, tampering, expired timestamp, unknown job) are covered by automated feature tests

Details in [TESTING.md](TESTING.md).

## Known bugs

- None open. (Fixed during Phase 2: worker-pool early-exit race in BFS; `WebsiteFactory` ignored explicit `url` overrides; nested-heading arrays crashed callback persistence; fake-host DNS in redirect tests.)

## Known limitations (honest)

- The callback endpoint protects with HMAC + timestamp + nonce; it returns no job detail and is not user-facing.
- The crawler-only callback mode signs with `CRAWLER_CALLBACK_SECRET` from runner secrets; the GitHub token is used only by the dispatcher and is never passed to the crawler or logged.
- Dispatcher requires `GITHUB_TOKEN`, `GITHUB_REPO` (private repo works) and `CRAWLER_CALLBACK_BASE_URL`; without them jobs stay pending (displayed as pending, never as completed).
- Playwright engine not exercised in this sandbox (Chromium download blocked by the environment proxy); the `http` engine was exercised live instead. The GHA workflow pins a fixed playwright version for the CI engine.
- Screenshots are optional (`--screenshot-dir`) and are not sent in the callback payload; `page_snapshots` therefore stays empty in this phase.
- No paid services are used or required by anything built so far.

## Deployment status

Not deployed. Phase 8 will deploy to Render + TiDB (see [DEPLOYMENT.md](DEPLOYMENT.md) and [FREE_HOSTING_LIMITATIONS.md](FREE_HOSTING_LIMITATIONS.md)).

## Remaining work

See "Incomplete features" above and [ARCHITECTURE.md](ARCHITECTURE.md) for the phased plan.
