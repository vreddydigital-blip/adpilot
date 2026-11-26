# TESTING

Last executed: Phase 6 completion. Suites: `php artisan test` (SQLite in-memory) for the Laravel app and `node --test` for the crawler package.

## Results

| Suite | Tests | Assertions | Result |
|---|---|---|---|
| Laravel Feature | 115 | 414 | all passing |
| Laravel Unit | 110 | 429 | all passing |
| Crawler (node:test) | 21 | 21 node asserts | all passing |
| **Total** | **246** | **843 assertions + crawler asserts** | **all passed, 0 failed** |

Anomaly log: during Phase 3 development, one isolated Feature-suite run reported 1 failed / 68 passed; the failing test was not captured before re-run and the failure was never reproduced in 6 subsequent full and isolated runs (all pass). Being watched; no code suspect identified.

Breakdown:
- `tests/Unit/UrlValidatorTest.php` - 37 cases: valid URL acceptance/normalization (8 data sets), rejection of 28 invalid/dangerous URL classes, plus a whitespace-injection case.
- `tests/Unit/Analysis/MarketingAdvisorServiceTest.php` (7) - Phase 5 advisor (see below).
- `tests/Feature/Auth/` (Breeze) + `tests/Feature/AuthFlowTest.php` - registration, login, logout, dashboard auth wall, empty states, real-count rendering.
- `tests/Feature/ProjectManagementTest.php` - project CRUD, per-user uniqueness, ownership denial (view/edit/update/delete), index isolation, website submission (valid/invalid/duplicate), cascade deletes, foreign-project website rejection, audit log writes.
- `tests/Feature/AnalysisJobTest.php` - job creation, duplicate protection, re-analysis after failure, ownership denial, job history in UI.
- `tests/Feature/ApiTest.php` - Sanctum auth wall, API CRUD, cross-user 403s, website + analysis flow, URL validation over API.
- `tests/Feature/ProfileTest.php` - profile flows (Breeze).
- `tests/Feature/ModuleIntelligenceApiTest.php` (16) - Phase 4 module endpoints + Phase 5 advisor endpoint (see below).
- `tests/Feature/WebsiteModulePagesTest.php` (11) - Phase 4 module web pages + Phase 5 advisor page (see below).
- `tests/Unit/Reports/ReportGenerationServiceTest.php` (7) - Phase 6 reports (see below).
- `tests/Feature/ReportApiTest.php` (7) + `tests/Feature/ReportPagesTest.php` (5) - Phase 6 report API and web pages (see below).

## Coverage gaps (to close in later phases)

- Phase 2: crawler tests (SSRF at DNS/redirect level, timeouts, malformed pages) - crawler belongs to a separate Node.js environment.
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

## Phase 4 additions

Laravel (`php artisan test`, all passing; 192 tests / 701 assertions total for the app):

- `tests/Unit/Analysis/BudgetCalculationServiceTest.php` (7): clicks/leads/conversions/CPA math, ROAS + break-even ROAS, target-CPA flag, null CPA/ROAS handling when no conversions occur, three labeled scenario factors (exact expected values: conservative 454.55 clicks, expected 500, best 555.56), disclaimers on every scenario row, channel factors (search 500, social 833.33, display 1250 for budget 1000 / CPC 2).
- `tests/Unit/Analysis/ConversionOpportunityServiceTest.php` (4): exactly 10 opportunities with fixed key order, reproducible aggregate score 21 for the standard fixture, methodology string states it is not a validated conversion probability, missing-CTA site scores 90 (high) and lands in the high bucket, missing evidence yields all-null scores excluded from the aggregate, high/medium buckets computed.
- `tests/Unit/Analysis/LeadReadinessServiceTest.php` (5): crawler form-evidence parsing ("2-field post form" -> field_count 2 / method post), score composition (form 40 + short-form 10 + email 10 + entry CTA 10 = 70), unparseable evidence kept as sample only, >4-field forms flag `form_friction` and forfeit the short-form bonus (score 60), 3+ missing contact channels flag `missing_contact_channels` as high, no-page jobs return null score with low confidence.
- `tests/Unit/Analysis/WastedSpendAnalysisServiceTest.php` (5): failed technical check -> high-severity finding, warn degrades high to medium, unmapped check keys ignored, criterion score < 50 becomes a finding with < 30 escalating to high, healthy criterion ignored, empty result set becomes `no_obvious_leakage` on crawled sites, disclaimer text present.
- `tests/Unit/Analysis/CreativeAnalysisServiceTest.php` (6): all generated ads respect platform limits (Google 30/90, Meta 40/125), candidates derived from H1/title word fits and CTA labels, word-boundary truncation of long meta descriptions, message match computed as 0-100 int with an explicit no-CTR note, source attribution + disclaimer, empty page set returns empty output.
- `tests/Feature/AnalysisPipelineTest.php` - extended (7): the three module scores per job (`campaign_readiness`, `conversion_opportunity`, `lead_intelligence`), all four Phase 4 tables populated, re-run replaces (not duplicates) predictive/waste/creative rows.
- `tests/Feature/ModuleIntelligenceApiTest.php` (12): four module GET endpoints return persisted data for the owner with correct module keys and disclaimers; cross-user 403 on all; 401 unauthenticated (incl. budget-scenarios); honest `data: null` without a completed job; budget scenario POST persists with valid math (3000/1.5 -> 2000 expected-case clicks), channel factor applied (display CPC 2 x 0.4 -> 1250 clicks), validation rejects bad inputs (budget/cpc/lead_rate/gross_margin), cross-user POST forbidden and stores nothing; `budget-channels` lists 3 static factor sets.
- `tests/Feature/WebsiteModulePagesTest.php` (8): five module pages render persisted results for the owner (scores, insights, findings, headlines), honest "No completed analysis yet" empty state, cross-user 403 on all pages, unauthenticated redirect to login, budget calculator page renders working form + tabs, POST persists and renders the scenario + DELETE removes it, cross-user DELETE 403, website overview shows the module navigation with the honest Phase 5 advisor placeholder.

Live (executed during Phase 4, outside the test runner):

- `analysis:reprocess` on the real books.toscrape.com 20-page crawl: Campaign readiness 45, Technical 69, Conversion opportunity 40, Lead readiness 0 (site has no forms or contact links - correct for a scraped-books sandbox), 8 insights, 7 waste findings, 9 creative items; evidence spot-checked genuine (e.g. mobile_usability high because no viewport tag; `no_forms` because no submit-able form exists on the store).
- Idempotency re-verified live: a second identical reprocess produced identical scores and identical row counts (3 marketing scores, 10 predictive, 7 waste, 9 creative).

## Phase 5 additions

Laravel (`php artisan test`, all passing; 206 tests / 751 assertions total for the app: Feature 98/364, Unit 108/387):

- `tests/Unit/Analysis/MarketingAdvisorServiceTest.php` (7): honest engine/labels (engine `rule_based_v1`, engine_note "not a large language model...not a validated ML prediction", disclaimer "never forecast spend, CPA, ROAS or revenue", campaign confidence passthrough); Advisor Priority Score = 100 - mean of known module scores (45+69+40+0 -> 61) with null technical audit handled; null advisor score + honest no-results summary when no module rows exist; cross-module topic consolidation (waste `technical_viewport` + campaign mobile insight -> one `mobile_usability` recommendation with `module_source` = wasted_spend + campaign_readiness and rationale "2 cross-module finding(s)"); priority ordering high/medium/low with sequential positions 1..3 and exact priority counts; cap at 10 recommendations (12 distinct topics -> 10 emitted); summary contains the real score list and counts (`lead_capture` topic derived from finding key).
- `tests/Feature/AnalysisPipelineTest.php` - extended: pipeline now asserts 4 `marketing_scores` rows incl. `marketing_advisor`, `recommendations` rows persisted, `result['recommendations']` count > 0, re-run replaces recommendation rows, and the mock-error pipeline constructs with the 7th (advisor) service.
- `tests/Feature/ModuleIntelligenceApiTest.php` (+4, 16 total): `GET /api/websites/{id}/marketing-advisor` returns engine `rule_based_v1`, score, honest engine_note and ordered recommendations for the owner; honest `data: null` empty state without a completed job; 403 for other users; 401 unauthenticated.
- `tests/Feature/WebsiteModulePagesTest.php` (+3, 11 total): advisor page renders Executive summary/Priority score/Prioritised action plan plus the engine note; honest empty state without a completed analysis; 403 for other users. The previous "Phase 5 advisor placeholder" assertion now checks the working AI Advisor tab + homepage link instead.

Live (executed during Phase 5, outside the test runner):

- `analysis:reprocess` re-run on the stored books.toscrape.com 20-page crawl: previous scores unchanged (Campaign 45, Technical 69, Opportunity 40, Lead 0), Priority score 61 (100 - mean(45,69,40,0)), 32 module findings consolidated into 10 prioritised recommendations (#1 mobile_usability spanning wasted_spend+campaign_readiness+technical_audit+conversion_opportunity, #2 metadata_quality, #3 lead_capture, #4 cta_effectiveness, #5 trust_signals, #6 contact_paths, then medium/low topics); summary and disclaimers verified verbatim.
- Idempotency re-verified live: a second identical reprocess kept 4 scores + 10 recommendations with the same leading topic.
- Bug found by these tests: the advisor topic slug stripped digits from keys, collapsing distinct unrecognised keys (`finding_1`..`finding_12`) into one topic; the topic regex now keeps digits/underscores.

## Phase 6 additions

Laravel (`php artisan test`, all passing; 225 tests / 843 assertions total for the app: Feature 115/414, Unit 110/429):

- `tests/Unit/Reports/ReportGenerationServiceTest.php` (7): snapshot contains the real URL/job/pages, all five module scores, recommendations, findings, checks and the no-real-metrics disclaimer; PDF reports are real files (status ready, dompdf generator, > 1 KB, `%PDF-` magic, payload stored); CSV reports contain the section headers, real score rows and disclaimers; generation refuses a pending/failed job (`completed analysis required`) and unknown formats (`xlsx`); `ensureFile` deterministically regenerates a lost file from the stored payload and keeps status ready; history orders two completed jobs chronologically and returns all five module scores non-null.
- `tests/Feature/ReportApiTest.php` (7): owner generates a PDF (`201` with metadata incl. job uuid, DB row ready) and lists/shows metadata; PDF download streams with `application/pdf` + `%PDF-` content; CSV download streams with `text/csv; charset=UTF-8` + section content; generation without a completed analysis returns the honest 422 message and stores nothing; cross-user 403 on generate/show/download (nothing extra stored); 401 unauthenticated on store + download; format validation rejects `xlsx`.
- `tests/Feature/ReportPagesTest.php` (5): the Reports screen renders generate buttons, the Chart.js score-history card (vendored `chart.umd.min.js`, honest "heuristic crawl-evidence scores" label) and the stored-reports block with empty state; honest empty state without a completed analysis; owner generates via form (redirect + success message incl. job uuid) and downloads the CSV; page + download 403 for other users; unauthenticated redirect to login.

Live (executed during Phase 6, outside the test runner):

- `ReportGenerationService` executed against the real books.toscrape.com 20-page analysis: PDF 14,047 bytes with `%PDF-` magic; CSV 85 lines containing the real module scores (`technical_audit,69 / campaign_readiness,45 / conversion_opportunity,40 / lead_intelligence,0 / marketing_advisor,61`, methodology rows incl. `rule_based`), section headers and the closing heuristic/no-real-metrics disclaimer; `history()` returned all five scores with `technical_audit: 69` resolved from `technical_audits`.
- Fixed during testing: `history()` initially returned `technical_audit: null` because the audit score lives in `technical_audits`, not `marketing_scores`; it now reads `TechnicalAudit` rows directly (live history shows `technical_audit: 69`, and reports/CSV rows carry the audit methodology).
