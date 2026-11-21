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

## Planned algorithms (documented in later phases)

- Phase 3: Campaign Readiness Score (weighted: value proposition 20%, CTA effectiveness 20%, landing relevance 15%, offer clarity 15%, lead capture 15%, mobile usability 10%, trust signals 5%) with evidence, confidence and unknown-evidence handling.
- Phase 3: Heuristic Conversion Opportunity Score = severity x impact x confidence, normalized 0-100, explicitly labelled heuristic.
- Phase 4: budget scenario formulas (estimated clicks = budget / CPC, leads = clicks x assumed lead rate, CPA = budget / conversions, revenue = conversions x AOV, break-even ROAS = 1 / gross margin) - clearly labelled simulations, never real performance.

## Fabrication policy

The application must never fabricate ad spend, CPC, CPA, conversion rate, revenue, ROAS, visitor behaviour or lead quality. Metrics that require integrations are displayed as unavailable with instructions to connect integrations in a future phase.
