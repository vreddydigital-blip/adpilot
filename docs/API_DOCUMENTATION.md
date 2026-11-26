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

### GET /api/websites/{id}/predictive-analytics (Phase 4)
Requires Sanctum auth; ownership enforced via the website policy. Returns the `conversion_opportunity` module for the latest completed job:

```json
{
  "website_id": 1,
  "module": "conversion_opportunity",
  "analysis_job": { "uuid": "...", "finished_at": "..." },
  "data": {
    "score": 40,
    "version": "v1.0",
    "breakdown": { "label": "Heuristic Conversion Opportunity Score", "methodology": "...", "opportunities": [ ... ], "high": ["mobile_usability"], "medium": ["form_complexity"], "low": [ ... ] },
    "opportunities": [ { "opportunity_key": "cta_placement", "priority": "high", "score": 90, "severity": 5, "impact": 5, "confidence": 90, "evidence": ["..."], "recommendation": "...", "effort": "low", "direction": "improvement" } ]
  }
}
```

### GET /api/websites/{id}/lead-intelligence (Phase 4)
Same shape; `module: "lead_intelligence"`. `data.score` is the heuristic Lead Capture Readiness Score; `data.breakdown` contains the parsed `forms[]`, `contact_methods` map and summary; `data.insights` lists stored lead insights ordered high -> medium -> low.

### GET /api/websites/{id}/wasted-spend (Phase 4)
Same shape; `module: "wasted_spend"`. `data.findings` are stored waste findings ordered high -> medium -> low by severity, and `data.disclaimer` states that findings are conversion-leakage indicators only, not measured wasted spend.

### GET /api/websites/{id}/creative-intelligence (Phase 4)
Same shape; `module: "creative_intelligence"`. `data` groups the stored creative rows: `google_headlines`, `google_descriptions`, `meta_headlines`, `meta_primary_text`, `messaging_angles`, plus the `message_match` overlap payload and a no-prediction disclaimer.

For all four endpoints: no completed analysis -> `200` with `data: null` and an honest message. 403 for non-owners; 401 without a token.

### GET /api/budget-channels (Phase 4)
Requires auth. Returns the static channel assumption factors (search/social/display with cpc_factor and rate_factor) used by budget simulations, plus a planning-only disclaimer.

### GET /api/websites/{id}/budget-scenarios (= /budget-intelligence) (Phase 4)
Requires auth; ownership enforced. Lists the caller's saved budget scenarios for the website (latest 25): `{ website_id, module: "budget_intelligence", scenarios: [ { id, label, channel, inputs, results, methodology: "deterministic simulation", version, created_at } ] }`.

### POST /api/websites/{id}/budget-scenarios (Phase 4)
Requires auth; ownership + analyze ability enforced; rate-limited to 20/min. Body (all simulations from user-provided assumptions):

- `budget` required numeric > 0 (max 100000000)
- `cpc` required numeric > 0 (max 1000)
- `lead_rate` required numeric 0-100; `conversion_rate` required numeric 0-100
- `average_order_value` required numeric >= 0; `gross_margin` required numeric > 0 (<= 100)
- `target_cpa` optional numeric >= 0; `channel` optional `search|social|display` (default `all`); `label` optional max 120

Returns `201` with `{ website_id, scenario_id, label, channel, results: { source_inputs, channel, scenarios[3] }, disclaimer }`. Channel factors are applied to CPC and both rates before scenario factors. Invalid input: `422`. Non-owner: `403`.

### GET /api/websites/{id}/marketing-advisor (Phase 5)

Requires Sanctum auth; ownership enforced via the website policy; throttle 60/min.

- Without a completed analysis: `200` with `data: null` and `message: "No completed analysis yet. Run an analysis first; marketing_advisor results are unavailable until a crawl has completed."`
- With a completed analysis: `{ website_id, module: "marketing_advisor", analysis_job: { uuid, finished_at }, data: { score, version, breakdown, recommendations } }`
- `score` = Advisor Priority Score (100 - mean of known module scores; documented as a simple aggregation, not a performance metric)
- `breakdown` = `{ label, engine, engine_note, methodology, summary, scores, priorities, disclaimer }`; `engine_note` states that the rule-based engine is not a large language model and not a validated ML prediction
- `recommendations[]` = ordered rows (priority high→low then position) with `recommendation_key`, `priority`, `title`, `rationale`, `evidence[]`, `action`, `effort`, `expected_impact`, `confidence`, `module_source[]`, `position`, `source` (`rule_based_v1`)

### Reports endpoints (Phase 6)

Requires Sanctum auth; ownership enforced per report (`user_id`) and via the website policy; formats `pdf` (dompdf) or `csv` (fputcsv).

- `GET /api/websites/{id}/reports` - the requesting user's 25 latest reports for the website (metadata only: id, uuid, title, format, status, size_bytes, analysis_job, generator, version, download_url, created_at); `200` with an empty list when none exist
- `POST /api/websites/{id}/reports` `{ format: pdf|csv }` - snapshots the latest completed analysis into a stored report; `201` with `{ report: metadata, message }`; rate-limited 20/min; **422** with `message: "No completed analysis yet. Reports require a completed crawl; run an analysis first."` without one; `422` validation errors for other formats; **500** with audit entry `report.generation_failed` on renderer failure
- `GET /api/reports/{report}` - single report metadata; `403` for non-owners
- `GET /api/reports/{report}/download` - streams the file (`application/pdf` / `text/csv; charset=UTF-8`, attachment); a missing file is deterministically regenerated from the stored snapshot; **404** with an honest message when the payload no longer allows regeneration; rate-limited 30/min

All report content is derived from stored crawl evidence and carries the heuristic/no-real-metrics disclaimer.

## Integration endpoints (Phase 7)

All require Sanctum auth. The integration layer stores credentials only - there is no live provider connection, `live_connection` and `data_fetched` are always `false`, and every response carries the no-fabrication note from `config/integrations.php`.

### GET /api/integrations

Returns the provider catalogue merged with the authenticated user's stored state: `providers[]` (provider, label, description, credential_fields, provides, status `not_connected|credentials_saved`, `credentials_saved`, `live_connection: false`, `data_fetched: false`), `note`, `credential_fields` (names of stored fields only - values are never returned).

### PUT /api/integrations/{provider}

Body: `{"credentials": {<field>: <value>, ...}}` (field names restricted to the provider's catalogue entry; unknown fields dropped server-side; each value max 500 chars). Stores credentials with Laravel's `encrypted:json` cast, sets status `credentials_saved`, audits `integration.credentials_saved` with field names only. `201` on first save, `200` on update; blank/omitted fields keep previously stored values. Honest message: "Credentials stored encrypted. No live connection was made and no advertising data is fetched yet."

### DELETE /api/integrations/{provider}

Clears stored credentials, resets status to `not_connected`, audits `integration.credentials_removed`. `404` when nothing is stored for the provider.

## Planned endpoints (later phases)

`/api/websites/{id}/overview`, `/api/internal/crawler-callback` (implemented - see below).

### GET /api/websites/{id}/campaign-intelligence (Phase 3)

Requires Sanctum auth; ownership enforced via the website policy.

- If the website has no completed analysis job: `200` with `data: null` and `message: "No completed analysis yet. Run an analysis first; results are unavailable until a crawl has completed."`
- Else returns the data persisted by the analysis pipeline for the latest completed job:

```json
{
  "website_id": 1,
  "analysis_job": { "uuid": "...", "finished_at": "..." },
  "campaign_readiness": {
    "score": 45,
    "version": "v1.0",
    "methodology": "heuristic",
    "computed_at": "...",
    "breakdown": { "criteria": [ { "key": "value_proposition", "weight": 20, "score": 85, "evidence": ["..."], "explanation": "...", "confidence": "medium", "recommendation": "..." } ], "confidence": "high", "methodology": "weighted heuristic (v1.0); URL-only evidence; not a validated ML prediction", "summary": "..." }
  },
  "technical_audit": { "score": 69, "version": "v1.0", "checks": [ { "key": "https", "label": "...", "status": "pass", "value": "https", "evidence": [], "explanation": "...", "recommendation": null } ] },
  "insights": [ { "module_key": "campaign_readiness", "priority": "high", "title": "...", "evidence": [], "recommendation": "...", "effort": "low", "direction": "improvement", "confidence": "medium" } ]
}
```

`campaign_readiness.score` is a heuristic score derived from crawl evidence; criteria with missing evidence have `score: null` and their weight is redistributed. `insights` is ordered high -> medium -> low.

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
