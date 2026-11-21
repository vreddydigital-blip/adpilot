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

## Current verification status

Not yet deployed (Phase 8). The verification checklist (signup requirements, quotas, minutes) will be performed with the live providers at that point and recorded here.
