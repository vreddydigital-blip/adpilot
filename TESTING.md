# TESTING (Phase 1)

Last executed: Phase 1 completion. Suite: `php artisan test` (SQLite in-memory).

## Results

| Suite | Tests | Assertions | Result |
|---|---|---|---|
| Feature | 48 | 154 | all passing |
| Unit | 37 | 109 | all passing |
| **Total** | **85** | **263** | **85 passed, 0 failed** |

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
