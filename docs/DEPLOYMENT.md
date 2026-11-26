# DEPLOYMENT

## Local (works with zero cloud accounts)

1. PHP 8.3+ (with pdo_sqlite), Composer 2, Node 18+.
2. `composer install`
3. `cp .env.example .env && php artisan key:generate`
4. `php artisan migrate` (creates SQLite database automatically)
5. `npm install && npm run build`
6. `php artisan serve` - open the URL shown in console.

Crawler locally: `php artisan analysis:run-local --all` (see `docs/ARCHITECTURE.md`).

## Phase 8 - free hosting (Render + TiDB Cloud Starter + GitHub Actions)

Deployment target (user decision in the master prompt): **Render free web service + TiDB Cloud Starter + GitHub Actions crawler**, Hostless documented as fallback. No payment information is ever entered; if any step demands a card, stop and fall back to local operation (see below).

Deployment artifacts committed in this repository (Phase 8):

| Artifact | Purpose |
|---|---|
| `Dockerfile` | PHP 8.3 production container: installs `pdo_mysql`/`bcmath`/`opcache`/`zip`, runs `composer install --no-dev`, boots with config/route/view cache + `migrate --force`, serves via `php artisan serve` on `$PORT` |
| `.dockerignore` | keeps secrets, dev files, tests, docs and local DBs out of the image |
| `render.yaml` | Render Blueprint: `runtime: docker`, `plan: free`, `healthCheckPath: /up`, production env (APP_DEBUG=false, SESSION/CACHE/QUEUE on database, SESSION_SECURE_COOKIE=true, TRUSTED_PROXIES=*), secrets via `sync: false` |
| `bootstrap/app.php` | trusts `TRUSTED_PROXIES` (set to `*` behind Render's TLS-terminating router) so audit logs record the real client IP and https scheme is honoured |
| `.github/workflows/crawler.yml` | Playwright crawler job (workflow_dispatch); the whole crawl+callback path runs inside Actions, the token never appears in logs |

Honest status: the artifacts were written and reviewed in the build sandbox but **Docker builds and actual cloud deploys cannot be executed here** (no Docker daemon, no browser/card). The first deployment must be performed by the repository owner following the steps below; report gaps back rather than assuming the runbook is wrong.

### Step-by-step runbook (no card needed at any step)

1. **TiDB Cloud Starter** (tidbcloud.com):
   - Create an account with the no-card Starter plan; create a project and a cluster named `adpilot`.
   - Note the connection details: host (e.g. `gateway01.<region>.prod.aws.tidbcloud.com`), port `4000`, user `<prefix>.root`, password you set.
   - Copy the platform's CA certificate chain (TiDB uses TLS by default; the container trust bundle `/etc/ssl/certs/ca-certificates.crt` matches `MYSQL_ATTR_SSL_CA`).
2. **Render** (render.com):
   - Sign up with GitHub; verify the free tier still requires no card. Use "New +" -> "Blueprint" and select the `vreddydigital-blip/adpilot` repository - Render reads `render.yaml`.
   - Fill the `sync: false` environment variables it asks for:
     - `APP_KEY` - generate with `php artisan key:generate --show` locally (value incl. `base64:` prefix).
     - `APP_URL` - your Render URL (`https://adpilot-ai.onrender.com` - set it to the actual value and note the trailing no-slash form).
     - TiDB: `DB_HOST`, `DB_PORT=4000`, `DB_DATABASE=adpilot`, `DB_USERNAME`, `DB_PASSWORD`.
     - `CRAWLER_CALLBACK_BASE_URL` - same https URL as `APP_URL`.
     - `CRAWLER_CALLBACK_SECRET` - a long random string (e.g. `openssl rand -hex 32`).
     - `GITHUB_TOKEN` - a fine-grained GitHub PAT with `actions:write` on this repository only (server-side, never logged).
   - First deploy: Render builds the Docker image, runs migrations at container start, and polls `GET /up` - the service should report "Live". The database/migrations output is visible in the Render logs.
3. **GitHub repository secrets** (Settings -> Secrets and variables -> Actions) - only one is required for the crawler:
   - `CRAWLER_CALLBACK_SECRET` - must match the Render variable exactly; the workflow's Node runner signs the callback with it (`X-AdPilot-Timestamp` + `X-AdPilot-Signature`). No other repository secret is needed: the workflow needs no GitHub token because it only runs the crawler and POSTs the HMAC callback, and the app-side `GITHUB_TOKEN` (dispatch PAT) exists alone as a Render env variable.
4. **First end-to-end check** (browser):
   - Register an account on the Render URL, create a project, add a public website, press "Analyze".
   - The job appears `dispatched` on the website page; the GitHub Actions run starts, the Node crawler fetches up to 20 pages and POSTs the HMAC-signed callback; the job becomes `completed` and all module screens, the advisor and the reports UI work against real crawl data.
   - If the job stays `pending`: check Render logs for "dispatch not configured" (missing `GITHUB_TOKEN`/`GITHUB_REPO`) and check the Actions tab for a failed run (callback secret mismatch produces a rejection in the Render logs).
5. **Sleep behaviour**: the free service sleeps after ~15 min idle; the first request afterwards takes ~1 min to wake. Scheduling the periodic crawls via cron is possible but not implemented (and would not keep the service awake for free).

### Environment variable matrix (production)

| Variable | Value | Notes |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | never enable on the public URL |
| `APP_KEY` | `base64:...` | required; also decrypts integration credentials |
| `APP_URL` / `CRAWLER_CALLBACK_BASE_URL` | `https://...` | exact public URL, no trailing slash |
| `TRUSTED_PROXIES` | `*` | only correct because Render's proxy is the sole ingress |
| `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | `database` | free instances have no shared disk or Redis |
| `SESSION_SECURE_COOKIE` | `true` | cookies only over https |
| `DB_CONNECTION` | `mysql` | TiDB is MySQL-compatible |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | TiDB values | port typically `4000` |
| `MYSQL_ATTR_SSL_CA` | `/etc/ssl/certs/ca-certificates.crt` | TLS-verify TiDB |
| `CRAWLER_CALLBACK_SECRET` | random hex | must match the Actions secret |
| `GITHUB_TOKEN` | PAT (actions:write on this repo) | app-side dispatch only |
| `GITHUB_REPO` | `vreddydigital-blip/adpilot` | image dispatch target |

### Backups and exports (free tiers)

- No managed backups on TiDB Starter or Render free. Periodic manual/`mysqldump`-style export via the TiDB console (or a scheduled Actions job connecting to TiDB) is the documented backup path; restore = re-import + `migrate --force`.
- Report PDFs/CSVs are regenerated deterministically from the persisted payload snapshot when the ephemeral disk has lost them (`ensureFile()`), so report downloads survive redeploys without a shared disk.

### Wallet-safe guardrails

- No payment data is ever entered at any provider; every paid-looking dialog is a stop-and-verify moment.
- Spend limit env/plan settings: TiDB Starter is fixed-quota (no spend possible), Render free disables paid features by plan, GitHub Actions minutes are capped (2,000/month on Free private repos) and the crawler is bounded (max 20 pages, bounded runtime).

### Fallback: no cloud at all ("Hostless")

- Run the app locally (`php artisan serve`) and crawl locally (`analysis:run-local`); nothing depends on Render/TiDB/Actions. The deployment above is a free-tier projection of this same codebase, so the fallback is always available with zero setup beyond `composer install` + `php artisan migrate`.

## Production-readiness statement

The no-card free deployment is a portfolio demonstration. It is NOT production-ready (no managed backups, no SLA, shared free-tier capacity, 512 MB single process). A production-readiness review (uptime, backups, disaster recovery, capacity, isolation, monitoring) is required before accepting business-critical workloads.
