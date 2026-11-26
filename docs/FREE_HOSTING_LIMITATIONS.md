# FREE_HOSTING_LIMITATIONS

Honest limitations of the planned no-card deployment. Nothing here is production-grade.

1. **Hostless free tier** - current allowance must be verified at sign-up; resource usage must stay within it. No assurance of uptime, no SLA. If Hostless changes its free plan, the documented fallback is local/self-hosted execution.
2. **TiDB Cloud Starter** - MySQL-compatible starter tier; verify current no-card eligibility and quota at sign-up. Row/storage limits may be reached quickly; the app must then show a service-unavailable message (planned behavior, no silent paid overage).
3. **GitHub Actions minutes** - private-repo free-minute allowance is limited. The crawler is intentionally bounded (max 20 pages, hard timeout) and jobs are on-demand only. If the allowance is insufficient, crawler use will degrade gracefully (jobs stay `pending` and the UI reports reality).
4. **Ephemeral filesystem** - anything written to local disk on Hostless can disappear on redeploy/restart. Permanent data lives only in TiDB.
5. **No card, no paid resources** - deployment must never enter payment data, enable paid tiers, or trigger billable usage.
6. **HTTPS** - provided by the platform; certificate management is theirs.
7. **Backups** - not included in free tiers; export procedures are documented instead.
8. **Crawler inside the Laravel container** - explicitly avoided; Playwright cannot run reliably in small app containers.
9. **Provider terms can change** - if a provider stops offering a no-card tier, deployment stops and the change is documented here rather than silently upgrading to a paid plan.

## Current verification status (Phase 8)

Deployment artifacts are committed (`Dockerfile`, `.dockerignore`, `render.yaml` Blueprint, trusted-proxy middleware setup); the full runbook is in [DEPLOYMENT.md](DEPLOYMENT.md). The build sandbox has no Docker daemon and no browser/payment ability, so the **first real deployment has not been executed** - the repository owner performs it with the runbook and records the results here:

- [ ] Render sign-up genuinely card-free; Blueprint reads `render.yaml`; first build succeeds (PHP 8.3 + pdo_mysql image)
- [ ] `migrate --force` creates all tables on TiDB Starter from the Render logs
- [ ] `GET /up` health check passes and the service reports "Live"
- [ ] Register -> project -> website -> Analyze -> Actions run -> callback -> `completed` works end to end
- [ ] TiDB Starter quota behaviour verified (row/limits); app behaviour at quota exhaustion checked
- [ ] Sleep/wake timing on the free instance observed (~15 min idle / ~1 min wake)

If any provider's no-card terms have changed, record it here and stop rather than paying - the Hostless/local fallback remains fully functional.

## Render specifics (Phase 8 primary target)

- Free web services run on 0.1 CPU / 512 MB RAM and sleep after ~15 minutes without traffic; the first request after a sleep takes about a minute. Fine for a portfolio demo, not for production latency expectations.
- 750 instance-hours per month (one always-on service, or several services sharing the pool); 100 GB/month outbound bandwidth.
- The filesystem is wiped on deploy/restart: logs and temp files vanish; all durable data must live in TiDB.
- Render's free PostgreSQL expires 30 days after creation - therefore the database remains TiDB Cloud Starter (MySQL-compatible, no card required).
- Free instances have no persistent disk and no Redis: sessions/cache must use the database (or cookie store), which DEPLOYMENT.md configures.
- Never add a payment card or enable paid add-ons; if Render's free tier changes, fall back to documented local operation or re-verify Hostless.
