# TESTING

Last executed: Phase 3 completion. Suites: `php artisan test` (SQLite in-memory) for the Laravel app and `node --test` for the crawler package.

## Results

| Suite | Tests | Assertions | Result |
|---|---|---|---|
| Laravel Feature | 69 | 219 | all passing |
| Laravel Unit | 74 | 241 | all passing |
| Crawler (node:test) | 21 | 21 node asserts | all passing |
| **Total** | **164** | **460 assertions + crawler asserts** | **all passed, 0 failed** |

Anomaly log: during Phase 3 development, one isolated Feature-suite run reported 1 failed / 68 passed; the failing test was not captured before re-run and the failure was never reproduced in 6 subsequent full and isolated runs (all pass). Being watched; no code suspect identified.

Breakdown:
- `tests/Unit/UrlValidatorTest.php` - 37 cases: valid URL acceptance/normalization (8 data sets), rejection of 28 invalid/dangerous URL classes, plus a whitespace-injection case.
- `tests/Feature/Auth/` (Breeze) + `tests/Feature/AuthFlowTest.php` - registration, login, logout, dashboard auth wall, empty states, real-count rendering.
- `tests/Feature/ProjectManagementTest.php` - project CRUD, per-user uniqueness, ownership denial (view/edit/update/delete), index isolation, website submission (valid/invalid/duplicate), cascade deletes, foreign-project website rejection, audit log writes.
- `tests/Feature/AnalysisJobTest.php` - job creation, duplicate protection, re-analysis after failure, ownership denial, job history in UI.
- `tests/Feature/ApiTest.php` - Sanctum auth wall, API CRUD, cross-user 403s, website + analysis flow, URL validation over API.
- `tests/Feature/ProfileTest.php` - profile flows (Breeze).

## Coverage gaps (to close in later phases)

- Phase 2: crawler tests (SSRF at DNS/redirect level, timeouts, malformed pages) - crawler belongs to a separate Node.js environment.
- Phase 4: module algorithm tests (conversion opportunity, budget calculators, lead readiness detail, creative analysis).
- Phase 6: report/CSV tests. Phase 7: full security audit suite.

## Test report maintenance

DEVELOPMENT_STATUS.md is updated after every phase with executed/passed/failed counts and any disputes or known bugs.

## Phase 2 additions

Laravel (`php artisan test`, all passing):

- `tests/Unit/Crawler/CallbackVerifierTest.php` (9): unconfigured secret, missing headers, malformed timestamp, timestamp outside the 300 s window, wrong-secret signature, tampered body, valid signature, replay rejection, in-window timestamp accepted.
- `tests/Unit/Crawler/CrawlerCallbackServiceTest.php` (8): started moves job to crawling with `started_at`; completed persistence (pages, errors, stats, `last_analyzed_at`); terminal 409 guard; invalid payload 422; 21-page payload 422 (cap is 20); cross-host page 422; failed transition; unknown status 422.
- `tests/Unit/Crawler/AnalysisJobDispatchTest.php` (4): unconfigured dispatcher keeps job pending (fake HTTP asserted no request), successful 204 moves job to `dispatched` with payload checks (job id, target URL, callback URL, Bearer token), failed dispatch keeps pending, connection exception keeps pending.
- `tests/Feature/CrawlerCallbackFlowTest.php` (8): full HMAC-signed round trip (200, rows written, audit), forged secret 401 + audited, replay 401, expired timestamp 401, unknown job 404, missing job_id 422, non-JSON body 422, Sanctum routes still locked.
- `tests/Feature/AnalysisCommandsTest.php` (4): prune-stale marks old pending jobs failed with reason `timeout` and spares fresh ones, custom `--minutes`, "nothing stale" report, and a clean failure for an unknown job UUID in `analysis:run-local`.

Crawler (`node --test crawler/test/crawler.test.js`, all passing):

- IP allow-listing: 22 IPv4 range families blocked (loopback, private, link-local, CGNAT, benchmark, documentation, multicast, reserved) and public addresses accepted; IPv6 loopback/ULA/link-local/multicast/doc `2001:db8::/32` and IPv4-mapped private variants blocked.
- `validateTargetUrl`: ftp/javascript/file schemes, embedded credentials, localhost-style, `.local`/`.internal`/`.test` and metadata hosts, and literal loopback/private IPs all rejected; public web addresses accepted.
- URL queue-key normalization (fragment/trailing-slash/query handling).
- robots.txt parsing (user-agent selection, Disallow/Allow, Sitemap) and allow-exception logic.
- HTML extraction: title/meta/OG, h1-h3, text length, CTA labels, lead forms (password forms excluded), email/phone/WhatsApp/booking links, trust signals, viewport meta, navigation; external links and downloadable-file links excluded.
- Crawl engine with an injected fake fetcher: multi-page BFS with correct depth ordering, page cap, cross-origin link exclusion, per-page HTTP error recording.
- Redirect SSRF (injected fake transport + resolver): a redirect to `169.254.169.254` is rejected before any contact (the fake transport throws if the private target is ever touched), public redirects are followed with per-hop revalidation, and the redirect-hop cap is enforced.
- Direct private-IP targets rejected; DNS-failure target reported as a structured `dns_failure`.

Live (executed during Phase 2, outside the test runner):

- books.toscrape.com 20-page bounded crawl through `analysis:run-local` with full database persistence.
- SSRF rejections (loopback/private/link-local/metadata hosts/DNS failure) against the real fetcher.

## Phase 3 additions

Laravel (`php artisan test`, all passing):

- `tests/Unit/Analysis/CampaignReadinessServiceTest.php` (9): weights sum to 100, reproducibility (identical score + criteria for identical data), high-evidence site capped below 100, empty crawl produces null score + "no score calculated" summary, missing-feature criteria score low instead of unknown while all-unknown handling is covered by the empty case, high-priority insight for a zero-CTA site (priority `high`, direction `improvement`, concrete recommendation), strong criteria emit no insights, every criterion carries evidence/explanation/confidence/recommendation, lead-capture points accumulate (lead form = 40).
- `tests/Unit/Analysis/TechnicalAuditServiceTest.php` (8): HTTPS check pass/fail, high-failure page scores < 50, compliant page scores > 70 with `high` confidence, load-time check `unknown` when timings absent, empty job -> null score, crawl errors downgrade to `warn`, reproducibility.
- `tests/Feature/AnalysisPipelineTest.php` (5): pipeline persists technical audit + marketing score + insights and audits `analysis_pipeline.completed`; refuses jobs without pages (`no_pages`); re-processing is idempotent (same score, 1 score row, 1 audit row, insights not duplicated); `analysis:reprocess` command reports scores and fails cleanly for an unknown/unfinished job UUID; a thrown service error is caught, audited (`analysis_pipeline.failed`) and never marks the crawl failed.
- `tests/Feature/CampaignIntelligenceApiTest.php` (4): owner receives persisted readiness/technical/insights JSON (7 criteria, heuristic methodology), cross-user 403, unauthenticated 401, honest `data: null` message when no completed analysis exists.

Bug found by these tests: PHP integer division made `$share === 1.0` false for fully healthy sites (scored "warn" instead of "pass"); shares are now explicit `(float)` casts.
