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

## Implemented (Phase 4)

### Heuristic Conversion Opportunity Score (ConversionOpportunityService, version v1.0)

Module 2 (Predictive Analytics). Evaluates 10 opportunities across the master-prompt analysis areas, each from crawl evidence only: `cta_placement`, `form_complexity`, `content_clarity`, `value_proposition`, `navigation`, `landing_relevance`, `mobile_usability`, `trust_signals`, `contact_accessibility`, `page_performance`.

Per opportunity, when evidence is known: `raw = severity x impact x (confidence / 100)` with severity and impact on 1-5 scales; `score = round(100 x raw / 25)`; priority: score >= 50 high, >= 25 medium, else low. When evidence is missing, severity/impact/confidence/score are all `null` and priority is `low`; the opportunity is excluded from the aggregate (unknown exclusion, same philosophy as Phase 3).

Aggregate score = `round(100 x sum(raw of known opportunities) / (25 x count(known)))`. All opportunities unknown -> score null. Label: "Heuristic Conversion Opportunity Score"; methodology string explicitly states it is not a validated conversion probability. Each opportunity stores evidence, recommendation, effort (low/medium/high), direction (`improvement`) and its own score.

Persistence: `marketing_scores` row (module `conversion_opportunity`, breakdown contains label, methodology, full opportunity list and high/medium/low key buckets) + one `predictive_insights` row per opportunity (the internal `raw` intermediate is stripped before persisting).

### Lead Capture Readiness Score (LeadReadinessService, version v1.0)

Module 5 (Lead Intelligence). Additive presence heuristic over crawl evidence: lead form detected 40, short form (<= 3 fields) +10, email link 10, phone link 10, WhatsApp link 5, booking link 15, CTA on entry page +10; capped at 95 (URL-only evidence cannot prove quality).

Forms are parsed from the crawler's compact evidence strings ("N-field get|post form") into `{field_count, method, sample}`; unparseable strings are kept as sample-only with null structure. No pages -> score null, confidence low, honest summary.

Insights (stored in `lead_insights`): `no_forms` (high), `form_friction` (medium, when any form has > 4 fields), `missing_contact_channels` (high when 3+ of email/phone/WhatsApp/booking missing, else medium), `homepage_cta_consistency` (medium), `contact_page` (medium, no URL/title matching contact/about patterns within the 20-page cap). Confidence medium when a score exists, low otherwise.

### Wasted spend findings (WastedSpendAnalysisService, version v1.0)

Module 4. Produces no score - it derives findings purely from already-computed Phase 3 results, never fabricates monetary values. From technical checks: fail -> severity high, warn degrades high to medium; mapped checks: https, status_codes, titles, meta_descriptions, mobile_viewport, load_time, content, crawl_errors (each with evidence, potential marketing impact, recommended correction). From campaign criteria: score < 50 becomes a finding (initial severity from map over cta_effectiveness, lead_capture_readiness, offer_clarity, trust_signals, mobile_usability), escalated to high when criterion score < 30. Zero findings with pages -> a low `no_obvious_leakage` finding. Disclaimer stored with the module: findings are conversion-leakage indicators only; actual wasted spend requires advertising cost + conversion data (integrations, future phase).

### Creative generation (CreativeAnalysisService, version v1.0)

Module 6. Deterministic templates assembled from the website's own titles, headings, meta descriptions and CTA labels. No page content is invented.

| Output | Source | Character limit | Notes |
|---|---|---:|---|
| Google Search headlines | H1/title word-boundary fits (6-word subset when >= 12 chars), brand, brand + offer hook when it fits, first CTA label; skip raw text equal to brand but still fit its word subset | 30 | 5-30 chars kept, case-insensitive dedupe, max 8 |
| Google Search descriptions | meta description verbatim (fitted), else H1/title + " Learn more at {brand}." | 90 | >= 30 chars kept, max 4 |
| Meta primary text | meta description or H1 (+ brand) | 125 | >= 20 chars kept, max 3 |
| Meta headlines | brand, H1, title, brand + offer hook | 40 | >= 5 chars kept, max 5 |
| Messaging angles | offer-word detection (free/trial/demo/quote, price/plan, book/buy/cart/checkout) | - | risk-reversal / transparency / direct-response conditionally + problem-solution always |
| Message match | token overlap: tokens >= 3 chars; per headline, share of tokens that appear in page text (page token starting with the ad token counts); average x 100 | - | verdict: good >= 60%, partial >= 30%, else low; explicit note that this does NOT predict CTR or conversions |

`fit()` truncates at the last word boundary. No crawled pages -> empty output. Persistence: `creative_insights` rows with kind `google_headline`, `google_description`, `meta_headline`, `meta_primary_text`, `messaging_angle` and one `message_match` row carrying the verdict payload.

### Budget simulation (BudgetCalculationService, version v1.0)

Module 3. Pure user-assumption math (never crawl-derived, never presented as real performance):

- `clicks = budget / cpc` (cpc > 0, else 0); `leads = clicks x lead_rate / 100`; `conversions = leads x conversion_rate / 100`; `cpa = conversions > 0 ? budget / conversions : null`; `revenue = conversions x aov`; `roas = budget > 0 ? revenue / budget : null`; `break_even_roas = margin > 0 ? 1 / margin : null`; `profit = revenue - budget`; `meets_target_cpa` = cpa <= target (null when no CPA possible or no target); `meets_break_even_roas` = roas >= break-even.

Scenario factors (applied to assumptions, all rounded to 2 dp):

| Scenario | CPC factor | Rate factor |
|---|---:|---:|
| Conservative case | 1.1 | 0.7 |
| Expected case | 1.0 | 1.0 |
| Best case | 0.9 | 1.3 |

| Channel | CPC factor | Rate factor |
|---|---:|---:|
| Search ads | 1.0 | 1.0 |
| Social ads | 0.6 | 0.8 |
| Display ads | 0.4 | 0.6 |

Every result row carries the disclaimer: "Deterministic simulation from your stated assumptions - not actual campaign performance." Scenarios are persisted in `budget_scenarios` (inputs, results, methodology `deterministic simulation`, version) linked to the website and creating user; channel-specific requests apply the channel factor to CPC and both rates before scenario factors.

### MarketingAdvisorService - Module 7 (implemented in Phase 5)

- **Engine**: `RuleBasedAdvisor` (engine key `rule_based_v1`, bound via `config/advisor.php` + the `AiProvider` interface). An external provider (LLM or ML service) can be swapped in by binding another `AiProvider` implementation. The engine_note is stored verbatim in the score breakdown: "Deterministic rule engine running on stored crawl evidence and module results; it is not a large language model and its output is not a validated ML prediction."
- **Input**: only already-persisted module results for the job - the four module scores (`technical_audits.score` + `marketing_scores` for `campaign_readiness`, `conversion_opportunity`, `lead_intelligence`), the row-level findings from `campaign_insights`, `predictive_insights`, `lead_insights`, `waste_findings`, and the technical audit's fail/warn checks that carry a recommendation. Nothing is crawled again; no advertising or revenue data is used.
- **Topic consolidation**: every finding key+title is mapped to one topic bucket by keyword rules (lead capture, contact paths, CTA effectiveness, mobile usability, metadata quality, technical health, load performance, content depth, trust signals, offer clarity, messaging; unrecognised keys fall back to `other_<key>`). Findings within a topic are merged (evidence lines de-duplicated, max 4). Topics are ranked by best contributing priority (high < medium < low), ties broken by finding count descending; PHP's stable sort keeps the remaining order deterministic (module iteration order). At most 10 recommendations are emitted, positions 1..n.
- **Advisor Priority Score** (persisted as the `marketing_advisor` `marketing_scores.score`): `100 - round(mean(known module scores))`. It is documented as a simple aggregation of the four heuristic module scores and explicitly not a marketing performance metric. Without any known module score it is `null`.
- **Executive summary**: deterministic sentence listing the known module scores with the chapters' confidence, the counts of consolidated findings/topics/recommendations and reminders that scores are heuristic and that no spend/CPA/ROAS forecast is ever made.
- **Persistence**: one `marketing_scores` row (`module_key = marketing_advisor`, methodology `rule_based`, breakdown = label/engine/engine_note/methodology/summary/scores/priorities/disclaimer) plus one `recommendations` row per emitted topic (recommendation_key, priority, title, rationale, evidence, action, effort, expected_impact, confidence, module_source array, position, source). Table rows are replaced delete-then-insert on rerun.
- **Honesty**: no finding is upgraded or softened beyond its module-derived priority; missing module results yield the honest no-results summary instead of invented advice; a website with zero findings yields zero recommendations.

### Pipeline invocation and idempotency

`AnalysisPipeline::run(job)`: refuses jobs with no crawled pages; otherwise, inside one database transaction, persists `technical_audits` (unique per job) and `marketing_scores` for `campaign_readiness`, `conversion_opportunity`, `lead_intelligence` and `marketing_advisor` (unique per job+module, updated in place) and replaces the job's `campaign_insights`, `predictive_insights`, `lead_insights`, `waste_findings`, `creative_insights` and `recommendations` rows (delete-then-insert), so re-running `analysis:reprocess` never duplicates rows (verified live across Phases 3-5: consecutive reprocesses on a 20-page crawl produced identical scores and identical row counts). The advisor always runs last so it reads the other modules' persisted results. A pipeline exception is caught, audited (`analysis_pipeline.failed`) and never marks the crawl failed - crawl results are already persisted and remain usable.

### ReportGenerationService (implemented in Phase 6)

- **Snapshot**: deterministic projection of a completed analysis - website/job metadata, the five module scores (technical audit from `technical_audits`, the rest from `marketing_scores` with methodology/version), campaign-readiness criteria, technical checks, every module finding (campaign/predictive/lead/waste with module, priority, title, recommendation), the prioritised advisor action plan and the standard disclaimer. Persisted as the `reports.payload` JSON, so downloads remain reproducible even on an ephemeral filesystem (`ensureFile` regenerates the file from the stored snapshot) and re-download never silently recrawles anything.
- **PDF**: dompdf renders a print layout (module score table, criteria, checks, action plan, up to 30 findings, disclaimers; real PDF-1.7 output). **CSV**: fputcsv with `SECTION` header rows (module scores / criteria / checks / advisor recommendations / findings / disclaimer). Neither format ever contains real advertising metrics; both state the heuristic disclaimer.
- **Historical comparison**: `history()` returns the last 12 completed jobs chronologically with all five module scores; the Reports screen renders it with a locally-vendored Chart.js line chart explicitly labelled as heuristic crawl-evidence scores.

## Planned algorithms (documented in later phases)

- Later phases: performance_audits once Lighthouse/PageSpeed data is available; a real ML advisor only when labelled conversion data exists (until then the rule-based engine remains the sole advisor).

## Fabrication policy

## Fabrication policy

The application must never fabricate ad spend, CPC, CPA, conversion rate, revenue, ROAS, visitor behaviour or lead quality. Metrics that require integrations are displayed as unavailable with instructions to connect integrations in a future phase.
