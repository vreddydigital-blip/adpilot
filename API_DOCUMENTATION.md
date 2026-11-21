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

## Internal endpoint (Phase 2) - crawler callback

### POST /api/internal/crawler-callback

Called by the crawler runner (GitHub Actions workflow or `analysis:run-local`) after a crawl.

- **Authentication:** HMAC-SHA256 signature over `"{timestamp}.{rawBody}"` using the shared `CRAWLER_CALLBACK_SECRET` (runner-side secret). Headers: `X-AdPilot-Timestamp` (unix seconds; +/- 300 s tolerance) and `X-AdPilot-Signature` (64 hex chars). A nonce cache rejects replays inside the window. Not behind `auth:sanctum`.
- **Request:** JSON body `{ job_id, status, ... }` where `status` is `started`, `progress`, `completed` or `failed`.
    - For `completed`: `summary.duration_ms`, `pages[]` (max 20; each with `url`, optional `final_url`, `http_status`, `title`, `meta`, `headings`, `text_length`, `load_time_ms`, `depth`, `features[]`), optional `errors[]` (`error_type`, `message`, optional `url`). Every page host must match the analysed website host.
    - For `failed`: optional `failure_reason` (max 120) and `error_message` (max 2000).
- **Responses:**
    - `401` `{ message }` - missing/invalid signature headers, expired timestamp, forged signature, or replayed request (audited as `crawler_callback.rejected`).
    - `404` `{ message: "Unknown analysis job." }` - `job_id` not found.
    - `422` `{ message }` - non-JSON body, missing `job_id`, unknown `status`, payload validation failure, or a page outside the analysed website (`host_mismatch`).
    - `409` `{ outcome: "job_already_terminal" }` - job already completed/failed/cancelled (idempotency guard).
    - `200` `{ outcome, message }` - recorded. `outcome` is `progress_recorded`, `completed` or `failed_recorded`.

### Dispatch behavior (analysis endpoints)

`POST /api/websites/{id}/analyze` (API) and the web "Start Analysis" button create the job and then attempt a GitHub Actions `workflow_dispatch` through the repository dispatches endpoint (`api.github.com/repos/{repo}/actions/workflows/{workflow}/dispatches`). The API response includes `dispatch`:

- `{ dispatched: true }` - job moved to `dispatched`.
- `{ dispatched: false, reason: "dispatcher_not_configured" }` - credentials unset; job stays `pending` (use the local `analysis:run-local` mode).
- `{ dispatched: false, reason: "dispatch_request_failed" }` - GitHub unreachable or rejected; job stays `pending`.

A job is never marked completed without a validated crawler callback.
