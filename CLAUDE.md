# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with this repository.

---

## Project Overview

**mir-ville-backup-cloud** — Laravel 13 API for Mirville Couture backup/sync service. Receives and stores SQLite database backups from the Mirville mobile app (React Native/Expo).

**Stack:** PHP 8.4, Laravel 13, Tailwind v4, SQLite (dev) / PostgreSQL (prod), Pest v4

---

## Commands

```bash
composer run dev              # Start server, queue, logs, Vite (concurrent)
composer run test             # Run Pest tests
composer install              # Install dependencies
npm install && npm run build  # Install and build frontend assets

php artisan migrate           # Run database migrations
php artisan migrate:fresh     # Fresh migration
php artisan make:test --pest Name  # Create Pest test
php artisan test --filter=name     # Run specific test

vendor/bin/pint --format agent     # Format PHP files
```

---

## Architecture

**Database:** SQLite for development (`database/database.sqlite`), tests use `:memory:`. Session/cache/queue stored in database tables.

**Testing:** Pest v4 with feature tests in `tests/Feature/`. Tests run against in-memory SQLite. Use factories for model creation in tests.

**Frontend:** Tailwind v4 with Vite. Run `npm run dev` during development or `npm run build` for production assets.

---

## Skills

Activate these skills for relevant tasks:
- `laravel-best-practices` — Writing/modifying Laravel PHP code
- `pest-testing` — Writing/modifying Pest tests
- `tailwindcss-development` — Tailwind CSS styling

---

## Conventions

- Use `php artisan make:` commands for new files
- Use explicit return types and PHP 8+ constructor property promotion
- Use factories for test data; prefer feature tests over unit tests
- Format PHP files with `vendor/bin/pint --format agent` after changes
- Follow existing naming conventions (e.g., `isRegisteredForDiscounts`)

---

## Notes

- Laravel Boost (`laravel/boost`) is installed — provides AI coding guidelines
- Default route returns `welcome` view at `/`
- See `backupplan.md` for cloud backup system implementation context
