# DEPLOYMENT

## Local (works with zero cloud accounts)

1. PHP 8.2+ (with pdo_sqlite), Composer 2, Node 18+.
2. `composer install`
3. `cp .env.example .env && php artisan key:generate`
4. `php artisan migrate` (creates SQLite database automatically)
5. `npm install && npm run build`
6. `php artisan serve` - open the URL shown in console.

## Free-cloud deployment plan (Phase 8, not yet executed)

### Components and verification status

| Component | Provider | Card required | Status |
|---|---|---|---|
| Laravel app | Render - free web service | 750 free instance-hrs/month; verify at sign-up | planned Phase 8 |
| Database | TiDB Cloud Starter (tidbcloud.com) | Verify at sign-up | planned Phase 8 |
| Crawler | GitHub Actions (workflow_dispatch) | No | planned Phase 2/8 |

Sign-up requirements and free quotas must be verified against the providers' current documentation at deployment time; this project never enters payment data or enables paid usage.

### Application (Hostless)

- Dockerfile-based deployment from the `adpilot-ai` GitHub repository (private).
- HTTPS terminated by the platform; app logs and health checks provided by the platform.
- Filesystem is ephemeral: no reports, screenshots or uploads are stored locally; everything user-facing lives in the database or external storage.

### Database (TiDB Cloud Starter)

- Database name `adpilot`, MySQL-compatible, TLS enforced.
- Connection variables set through provider secret management (see `.env.example`, `MYSQL_ATTR_SSL_CA` on TiDB).
- Spending limit set to zero; when free quota is exhausted the app will show a clear service-unavailable message rather than attempt paid usage.

### Crawler (GitHub Actions)

- Phase 2 adds a `workflow_dispatch` Node.js/Playwright job triggered by a secure job identifier (`analysis_jobs.uuid`).
- Callback into the Laravel API uses HMAC signing, timestamps for replay protection, and strict job ownership checks.
- Private-repo Actions minutes within the free allowance; artifact retention kept short.

### Environment variables (production)

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`
- `SESSION_SECURE_COOKIE=true`
- DB: MySQL-compatible/TiDB settings per `.env.example`
- `CRAWLER_CALLBACK_SECRET` (Phase 2), GitHub token (server-side secret only).

### Migrations, health checks, backups

- Migrations run at deploy (`php artisan migrate --force`).
- Health check endpoint planned in Phase 8.
- Backup/export: periodic `mysqldump`/TiDum export documented in Phase 8; free tiers do not provide managed backups.

## Production-readiness statement

The no-card free deployment is a portfolio demonstration. It is NOT production-ready (no managed backups, no SLA, shared free-tier capacity). A production-readiness review (uptime, backups, disaster recovery, capacity, isolation, monitoring) is required before accepting business-critical workloads.

## Phase 8 deployment target (updated: Render)

The primary Phase 8 target is **Render + TiDB Cloud Starter + GitHub Actions** (user decision; Hostless remains documented as a fallback because its free allowance must be re-verified at sign-up).

### Application (Render - free web service)

- Deploy the Laravel monolith with a Dockerfile (PHP 8.3 + nginx, or `php artisan serve` behind Render's router) built from the GitHub repository.
- Free web service: 0.1 CPU / 512 MB RAM, sleeps after ~15 minutes idle (first request wakes it, about a minute), 750 instance-hours/month, 100 GB/month outbound bandwidth. Ephemeral filesystem - all durable data lives in TiDB.
- Required environment: `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `DB_*` (TiDB + `MYSQL_ATTR_SSL_CA`), `SESSION_DRIVER=database`, `CACHE_STORE=database` (free instances have no shared disk or Redis), `CRAWLER_CALLBACK_BASE_URL`, `CRAWLER_CALLBACK_SECRET`.
- Use Laravel's default `/up` route as the Render health check path.
- Run migrations in the build/start command (`php artisan migrate --force`); credentials come only from the environment.

### Crawler (GitHub Actions - unchanged)

- `.github/workflows/crawler.yml` is triggered by the app's dispatcher via the repository dispatches endpoint using a fine-grained PAT limited to `actions:write` on the private repository.
- Repository secret used by the workflow: `CRAWLER_CALLBACK_SECRET`. The token lives only in the app environment.
- Free minutes: private repositories get 2,000 Actions minutes/month on the Free plan; a bounded crawl (under 5 minutes, 20 pages) uses only a small slice.
