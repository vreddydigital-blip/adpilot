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
