# SECURITY (Phase 1)

Implemented in this phase:

- **Authentication** - Laravel session auth (Breeze); passwords hashed with bcrypt.
- **Authorization** - `ProjectPolicy`, `WebsitePolicy`, `AnalysisJobPolicy` enforce owner-only access on every read/write action, web and API.
- **CSRF** - Laravel CSRF middleware on all web forms (verified by auth feature tests).
- **Input validation** - FormRequests (`StoreProjectRequest`, `StoreWebsiteRequest`) plus `UrlValidator` for URLs.
- **Output escaping** - Blade `{{ }}` escaping everywhere; no raw output directives in business views.
- **SQL injection** - Eloquent query builder only; no raw SQL.
- **Rate limiting** - API analyze (20/min) and job status (60/min); Laravel's default framework throttles otherwise apply.
- **SSRF submission defenses** - scheme allow-list, credential stripping, host deny-list (localhost variants, .local/.internal/.test, cloud metadata hosts), literal-IP public-range filtering.
- **Residual SSRF risk** - a hostname can still resolve to a private IP at crawl time. This is fully handled by the crawler's DNS resolution + redirect revalidation + connect-time IP checks in Phase 2; the URL string alone is never trusted as sufficient.
- **Audit logs** - creation/update/deletion of projects, websites and analysis jobs recorded with user, IP, user-agent.
- **Secrets** - `.env` excluded from git (`gitignore`); production secrets managed via provider environment/secret management. No credentials in frontend JS; only the CSRF token is exposed to the browser.

Sessions: database driver locally (provider-agnostic); cookie flags `secure`/`httponly` default per Laravel's `session` config; set `SESSION_SECURE_COOKIE=true` behind HTTPS.

## Phase 2+ security backlog

- Signed crawler callbacks (HMAC + timestamp replay protection + job ownership checks).
- DNS-resolution-based SSRF checks, redirect revalidation, response size limits, browser isolation in the crawler.
- Shared hosting hardening, backup/restore procedures, monitoring.

## Reporting

Report suspected security issues in this portfolio project by opening a private advisory in the repository.

## Crawler callback authentication (Phase 2)

- `POST /api/internal/crawler-callback` verifies `hash_hmac('sha256', '<timestamp>.<rawBody>', CRAWLER_CALLBACK_SECRET)` using `hash_equals`, with an absolute timestamp tolerance of 300 seconds and a nonce cache (`Cache::add`, TTL twice the tolerance) so one body+signature pair can only be processed once. Rejections are audited (`crawler_callback.rejected`) with IP and user agent; no job data is returned on failure.
- The endpoint performs its own authorization: jobs are looked up by UUID and payloads are rejected if any page URL points outside the analysed website's host. A maximum of 20 pages is accepted with a strict validator bounding every field length.
- Terminal-state guard: callbacks for completed/failed/cancelled jobs return 409 and mutate nothing.
- Secret management: `CRAWLER_CALLBACK_SECRET` exists only as a server-side environment variable and as a GitHub Actions repository secret runner-side. It is separate from the `GITHUB_TOKEN`, which never leaves the dispatcher and is never forwarded into crawler logs.
- The dispatch uses the repository `workflow_dispatch` API with a fixed input allow-list (`job_id`, `target_url`, `callback_url`); the crawl runs only in the private repository.

## Phase 3 additions

- `GET /api/websites/{id}/campaign-intelligence` lives inside the `auth:sanctum` group and authorizes through the existing website ownership policy (403 for other users, 401 unauthenticated); the token-authenticated feature tests cover all three cases.
- The analysis pipeline only consumes already-stored crawl rows; it never accepts external payload data beyond the validated crawler callback, so no trust boundary is crossed by scoring.
- Pipeline failures are audited (`analysis_pipeline.failed`) and cannot upgrade a crawl to completed retroactively - the callback flow itself still controls the job lifecycle; the pipeline only ever marks a job completed after results from an already-successful crawl persist.
