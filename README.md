# AdPilot AI

AI-powered performance marketing intelligence platform. Submit a website URL and AdPilot AI crawls it (SSRF-hardened), evaluates conversion readiness across seven intelligence modules, and produces evidence-based marketing recommendations, reports, and budget simulations.

## What is implemented (all 8 phases complete)

- **Accounts & projects** — registration/login (Laravel Breeze), projects and websites with per-user ownership policies, Sanctum-token REST API
- **Crawler** — Node.js + Playwright crawler executed via GitHub Actions (workflow_dispatch), SSRF guard on every submission, HMAC-signed callback API with replay protection
- **Seven intelligence modules** — technical audit, campaign readiness, conversion opportunity, lead intelligence, wasted-spend analysis, creative intelligence (ad copy suggestions), budget scenario simulator, plus an evidence-based recommendation set
- **Reports** — PDF (dompdf) and CSV export, Chart.js score history
- **Honesty guarantees** — no fabricated advertising metrics; heuristics are labelled as heuristics, unavailable metrics are marked "requires integration", heuristic scores are never presented as validated ML

## Tech stack

- PHP 8.3, Laravel 12, Blade, Tailwind CSS, Alpine.js, Chart.js
- MySQL-compatible database (SQLite for local development, TiDB Cloud Starter in production)
- Node.js 22 + Playwright crawler via GitHub Actions
- Docker deploy target (Render Blueprint included)

## Local development

Requirements: PHP 8.3 (pdo_sqlite), Composer 2, Node.js 18+.

    composer install
    cp .env.example .env
    php artisan key:generate
    php artisan migrate
    npm install && npm run build     # or: npm run dev
    php artisan serve

Then open the serving URL and register.

Run the analysis pipeline locally without GitHub Actions:

    php artisan analysis:run-local --all

Run the test suite:

    php artisan test
    node --test crawler/test/crawler.test.js

## Deployment (no payment card, ever)

The free hosting stack is **Render (free web service) + TiDB Cloud Starter + GitHub Actions**. The full no-card runbook lives in [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) and covers TiDB setup, the Render Blueprint (`render.yaml`), the environment variable matrix, the single required repository secret, and the Hostless fallback if any provider ever demands a card.

## Project layout

    app/
      Http/Controllers          web + API controllers (thin)
      Http/Requests             form validation
      Models                    Eloquent models
      Policies                  ownership authorization
      Services/Analysis         intelligence modules + pipeline orchestration
      Services/Reports          PDF/CSV report generation
      Services/Security         submission-time URL validation
      Support                   URL normalization helpers
    database/migrations         schema (28 tables)
    crawler/                    Node.js + Playwright crawler package
    .github/workflows/          crawler workflow (workflow_dispatch)
    docs/DEPLOYMENT.md          free hosting runbook
