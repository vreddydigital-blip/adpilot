# CHANGELOG

## Phase 1 - Foundation (2026-11-21)

Added:
- Laravel 12 + Breeze authentication scaffold
- Migrations: projects, websites, analysis_jobs, audit_logs
- Models (User relations, Project, Website, AnalysisJob, AuditLog), policies, factories
- UrlValidator + UrlNormalizer services (submission-time SSRF-oriented checks)
- AnalysisJobService (duplicate protection, audit trail)
- Web UI: sidebar layout, dashboard with real counts, project CRUD, website analysis screen with job history
- REST API: projects CRUD, websites, analyze, job status (Sanctum), rate limiting
- Test suite: 85 tests / 263 assertions, all passing
- Documentation set (README, ARCHITECTURE, DATABASE_SCHEMA, API_DOCUMENTATION, ALGORITHM_DOCUMENTATION, SECURITY, TESTING, DEPLOYMENT, FREE_HOSTING_LIMITATIONS, DEVELOPMENT_STATUS, CHANGELOG)
