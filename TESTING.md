# TESTING

Last executed: Phase 2 completion. Suites: `php artisan test` (SQLite in-memory) for the Laravel app and `node --test` for the crawler package.

## Results

| Suite | Tests | Assertions | Result |
|---|---|---|---|
| Feature | 48 | 154 | all passing |
| Unit | 37 | 109 | all passing |
| Laravel Feature | 60 | 184 | all passing |
| Laravel Unit | 58 | 169 | all passing |
| Crawler (node:test) | 21 | 21 node asserts | all passing |
| **Total** | **139** | **353 assertions** | **all passed, 0 failed** |

Breakdown:
- `tests/Unit/UrlValidatorTest.php` - 37 cases: valid URL acceptance/normalization (8 data sets), rejection of 28 invalid/dangerous URL classes, plus a whitespace-injection case.
- `tests/Feature/Auth/` (Breeze) + `tests/Feature/AuthFlowTest.php` - registration, login, logout, dashboard auth wall, empty states, real-count rendering.
- `tests/Feature/ProjectManagementTest.php` - project CRUD, per-user uniqueness, ownership denial (view/edit/update/delete), index isolation, website submission (valid/invalid/duplicate), cascade deletes, foreign-project website rejection, audit log writes.
- `tests/Feature/AnalysisJobTest.php` - job creation, duplicate protection, re-analysis after failure, ownership denial, job history in UI.
- `tests/Feature/ApiTest.php` - Sanctum auth wall, API CRUD, cross-user 403s, website + analysis flow, URL validation over API.
- `tests/Feature/ProfileTest.php` - profile flows (Breeze).

## Coverage gaps (to close in later phases)

- Phase 2: crawler tests (SSRF at DNS/redirect level, timeouts, malformed pages) - crawler belongs to a separate Node.js environment.
- Phase 3+: scoring reproducibility tests, module algorithm tests.
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
