# AdPilot AI

AI-powered performance marketing intelligence platform. You submit a website URL; AdPilot AI crawls it, evaluates conversion readiness and produces evidence-based marketing recommendations.

**Status: Phase 1 (Foundation) complete — see DEVELOPMENT_STATUS.md.**

## What works today (Phase 1)

- Registration, login, logout, profile management (Laravel Breeze)
- Projects (create, read, update, delete) with per-user ownership enforced by policies
- Websites (add URL to project, view, remove) with strict URL validation and normalization
- Analysis jobs (create, duplicate protection, status tracking across pending / dispatched / crawling / processing / completed / failed / cancelled)
- API endpoints secured with Sanctum tokens
- Dashboard with real database counts and honest empty states (no fabricated analytics)
- Audit logging of security-relevant actions
- Automated test suite (85 tests, 263 assertions, all passing)

The website crawler, seven intelligence modules, budget calculators and reporting are delivered in later phases. They are explicitly labelled as planned in the UI — no static mock screens.

## Tech stack

- PHP 8.3, Laravel 12, Blade, Tailwind CSS, Alpine.js
- MySQL-compatible database (SQLite for local development, TiDB Cloud Starter for the free cloud deployment)
- Sanctum-token REST API
- Node.js + Playwright crawler (Phase 2, executed via GitHub Actions)

## Local development (no cloud account needed)

Requirements: PHP 8.2+ with pdo_sqlite (or MySQL), Composer, Node.js 18+.

    composer install
    cp .env.example .env
    php artisan key:generate
    php artisan migrate
    npm install && npm run build     # or: npm run dev
    php artisan serve

Then open http://localhost:8000 and register.

Default local database is SQLite. To use TiDB Cloud Starter or any MySQL-compatible database, set the DB_* variables described in .env.example.

## Tests

    php artisan test

## Project layout

    app/
      Http/Controllers          web + Api controllers (thin)
      Http/Requests             form validation
      Models                    Eloquent models
      Policies                  ownership authorization
      Services/Analysis         analysis job orchestration
      Services/Security         submission-time URL validation (SSRF checks completed by the crawler)
      Support                   URL normalization helpers
    database/migrations         schema
    crawler/                    (Phase 2) Node.js Playwright crawler
    .github/workflows/          (Phase 2) crawler job workflow

## Documentation

- ARCHITECTURE.md - system design and data flow
- DATABASE_SCHEMA.md - tables, columns, indexes
- API_DOCUMENTATION.md - REST endpoints
- ALGORITHM_DOCUMENTATION.md - scoring methodology (as phases land)
- SECURITY.md - security model and controls
- TESTING.md - test strategy and current results
- DEPLOYMENT.md - local + free-hosting deployment
- FREE_HOSTING_LIMITATIONS.md - no-card hosting limits and risks
- DEVELOPMENT_STATUS.md - phase status, test results, known issues
- CHANGELOG.md - change log
