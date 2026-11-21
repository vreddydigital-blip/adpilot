# ALGORITHM_DOCUMENTATION

Phase 1 introduces no marketing scores. To be explicit: the Campaign Readiness Score, Conversion Opportunity Score and all module algorithms described in the product specification are **not yet implemented**. Nothing in the application pretends otherwise - the dashboard and website screens state that these arrive with the crawler and intelligence modules (Phases 2-4).

## Implemented (Phase 1)

### URL validation and normalization
`App\Services\Security\UrlValidator`

1. Trim; reject empty or > 2048 characters.
2. Parse; require scheme in {http, https} and a host; reject embedded credentials.
3. Host policy: reject `localhost` / `*.localhost`, `*.local`, `*.internal`, `*.test`; reject cloud metadata hosts (`metadata.google.internal`, `metadata.goog`); for literal IPs require public ranges (RFC1918, loopback, link-local, and IETF-reserved ranges are rejected via PHP's FILTER_FLAG_NO_PRIV_RANGE / NO_RES_RANGE); otherwise validate hostname label syntax and length limits.

`App\Support\UrlNormalizer`

- Lowercase scheme/host; strip default ports (80/443); strip trailing slash; preserve non-default ports, path and query; drop fragment.
- `url_hash` = SHA-256 of the normalized URL, used to deduplicate websites per project.

### Analysis job state machine
States: `pending, dispatched, crawling, processing, completed, failed, cancelled`.
Duplicate protection: only one active (non-terminal) job per website may exist. Terminal jobs do not block re-analysis. Jobs are never marked completed without crawler results (currently jobs remain `pending`; the dispatcher arrives in Phase 2).

## Implemented (Phase 3)

### Technical audit (TechnicalAuditService, version v1.0)

Runs over stored crawl data only (no network access). Eight checks; each returns `pass | warn | fail | unknown` with value, evidence and explanation, where `unknown` means the crawl did not capture the required data:

| Check | Pass | Warn | Fail | Unknown when |
|---|---|---|---|---|
| HTTPS on entry page | scheme https and 2xx-3xx | - | non-https scheme | scheme or status not captured |
| 2xx response coverage | 100% | >= 70% | < 70% | - |
| Titles present and unique | all have unique titles | - | any missing title | duplicated titles only |
| Meta description coverage | >= 70% | >= 30% | < 30% | - |
| Mobile viewport tag | 100% | >= 50% | < 50% | - |
| Observed load time | <= 1500 ms avg | <= 3000 ms | > 3000 ms | no page reported timing |
| Homepage content depth | >= 500 chars | >= 200 chars | < 200 chars | text length not captured |
| Crawl errors | 0 | <= 5 | > 5 | - |

Score = 100 x sum(check value) / number of known checks, with pass = 1.0, warn = 0.5, fail = 0.0. All checks unknown -> score null. Shares are computed as casts to float (PHP integer division would make `$share === 1.0` false otherwise - bug fixed in Phase 3).

### Campaign Readiness Score (CampaignReadinessService, version v1.0)

Weighted heuristic over criteria derived from crawled evidence (features, headings, metadata, text volume). All scoring functions cap below 100 (max 85-95) because usable evidence from a URL-only crawl cannot prove visual quality, above-the-fold position, or visitor behaviour.

| Criterion | Weight | Heuristic summary |
|---|---:|---|
| Value proposition | 20 | homepage H1 present/usable (10-120 chars), meta description present, text volume >= 300 chars; clamped 5-95 |
| CTA effectiveness | 20 | distinct CTA labels detected: 1 -> 40, 2-3 -> 70, > 3 -> 85 (capped 95); no CTA on entry page caps at 45; short signup form + 10; no CTA anywhere -> 15 |
| Landing page relevance | 15 | pages crawled: >= 5 -> 75, 3-4 -> 55, else 30; entry-page text >= 200 chars + 15; meta description on >= 50% of pages + 10; cap 95 |
| Offer clarity | 15 | offer/pricing keywords (price, plan, free, trial, quote, demo, "$", "%"...) in headings/titles/meta: 0 -> 25, 1-2 -> 50, 3-5 -> 70, > 5 -> 85 (cap 90) |
| Lead capture readiness | 15 | additive points: lead form 40, signup form 15, email 10, phone 10, WhatsApp 10, booking 15 (cap 95); none -> 10 |
| Mobile usability | 10 | viewport tag coverage: >= 50% -> 90, > 0% -> 60, 0% -> 15 |
| Trust signals | 5 | trust wording hits: >= 5 -> 85, 1-4 -> 60, 0 -> 20 |

- Unknown handling: a criterion with no crawl evidence gets score `null`, is excluded, and the remaining weights are renormalized. All criteria unknown -> total score null. Confidence: high (0 unknown), medium (1), low (2+).
- Overall score = round(sum(score x weight) / sum(weight of known criteria)). The same job data always produces the same result (pure function over stored crawl rows).
- Every criterion returns: score (or null), weight, evidence strings referencing real crawl data, explanation of what the heuristic can and cannot see, confidence, and a concrete recommendation. Scores are labelled "heuristic ... not a validated ML prediction" everywhere they are shown.

### Insight / finding priority (stored in `campaign_insights`)

- Campaign criteria: score < 40 and weight >= 15 -> high; score < 40 -> medium; score < 70 and weight >= 10 -> medium; score < 70 otherwise -> low; >= 70 -> no insight.
- Technical checks: every fail/warn check with a recommendation becomes an insight (fail -> high, warn -> medium).
- Each insight stores priority, title, evidence, recommendation, effort (low/medium/high), direction (`improvement`) and confidence.

### Pipeline invocation and idempotency

`AnalysisPipeline::run(job)`: refuses jobs with no crawled pages; otherwise persists `technical_audits` (unique per job) and `marketing_scores` (unique per job+module, updated in place) and replaces the job's `campaign_insights`, so re-running `analysis:reprocess` never duplicates rows. A pipeline exception is caught, audited (`analysis_pipeline.failed`) and never marks the crawl failed - crawl results are already persisted and remain usable.

## Planned algorithms (documented in later phases)

- Phase 3: Heuristic Conversion Opportunity Score = severity x impact x confidence, normalized 0-100, explicitly labelled heuristic. (Planned for Phase 4 - Predictive Analytics module.)
- Phase 4: budget scenario formulas (estimated clicks = budget / CPC, leads = clicks x assumed lead rate, CPA = budget / conversions, revenue = conversions x AOV, break-even ROAS = 1 / gross margin) - clearly labelled simulations, never real performance.

## Fabrication policy

The application must never fabricate ad spend, CPC, CPA, conversion rate, revenue, ROAS, visitor behaviour or lead quality. Metrics that require integrations are displayed as unavailable with instructions to connect integrations in a future phase.
