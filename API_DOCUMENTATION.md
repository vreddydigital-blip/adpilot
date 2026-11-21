# API_DOCUMENTATION (Phase 1)

Base URL: `/api`. All endpoints except those under public access require a Sanctum bearer token obtained via either:
- an authenticated web session (cookie), or
- a personal access token (create one with Laravel Sanctum), sent as `Authorization: Bearer <token>`.

Errors follow Laravel's JSON conventions: `422` with an `errors` object for validation failures; `403` for ownership violations; `401` for missing/invalid authentication.

## Projects

### GET /api/projects
List current user's projects with website counts.

Response 200: `{ "data": [ { "id", "name", "description", "websites_count", "created_at", "updated_at" } ] }`

### POST /api/projects
Body: `{ "name": "required, max 120, unique per user", "description": "optional, max 2000" }`

Response 201 with project resource. Validation failure: 422.

### GET /api/projects/{id}
Response 200 with project resource; 403 if not owned by the caller.

### PUT /api/projects/{id}
Body: `{ "name": "...", "description": "..." }` — response 200. Ownership enforced.

### DELETE /api/projects/{id}
Response 200 `{ "deleted": true }`. Cascade-deletes websites and analysis jobs of the project.

## Websites

### POST /api/websites
Body: `{ "project_id": "required, must belong to caller", "url": "required, max 2048", "label": "optional" }`

The URL is validated (http/https only; localhost, private, loopback, link-local and metadata hosts rejected) and normalized. Duplicate submissions within the same project return the existing website with 200; new ones return 201.

Response 201/200: project-scoped website resource: `{ "data": { "id", "project_id", "url", "normalized_url", "host", "label", "last_analyzed_at", "created_at" } }`

Rejection of invalid URL: 422 with `errors.url`.

### GET /api/websites/{id}
Response 200 (includes `latest_analysis_job` when present); 403 if not owned.

### POST /api/websites/{id}/analyze
Creates an analysis job (rate-limited to 20 requests per minute).

- If an active job (pending/dispatched/crawling/processing) exists: returns it with 200 and `job_created: false`.
- Otherwise creates a job with 202 and `job_created: true`.

Response resource: `{ "data": { "id", "uuid", "website_id", "status", "attempt", "created_at", ... } }`

Phase 1 limitation: jobs are created and retained in `pending`; the crawler dispatch (GitHub Actions job) is implemented in Phase 2. The status is truthful at all times.

### GET /api/analysis-jobs/{id}
Job status lookup, rate-limited to 60 per minute. 403 if not owned.

## Planned endpoints (later phases)

`/api/websites/{id}/overview`, `/api/websites/{id}/campaign-intelligence`, `/api/websites/{id}/predictive-analytics`, `/api/websites/{id}/budget-intelligence`, `/api/websites/{id}/wasted-spend`, `/api/websites/{id}/lead-intelligence`, `/api/websites/{id}/creative-intelligence`, `/api/websites/{id}/marketing-advisor`, `/api/websites/{id}/budget-scenarios`, `/api/reports/{id}`, `/api/internal/crawler-callback`.

The internal crawler callback will use a shared-secret HMAC signature, timestamp-based replay protection, and strict job ownership checks (Phase 2).
