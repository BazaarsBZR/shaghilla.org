# Shaghilla.org — Lebanon News + Live Platform

Arabic-first (RTL) news website built with **Laravel 11 (PHP 8.3)**, **Blade + Tailwind + Alpine + Vite**, and a **Filament (English UI)** admin panel.

> 📐 **Full architecture & developer guide:** see [`ARCHITECTURE.md`](ARCHITECTURE.md) — domain model, request flow, the RSS/Al-Manar import pipeline, the Filament admin surface, the front-end, and the cPanel deployment model.

## Features

- RSS import from configured feed sources with deduplication
- Keyword rules (admin-editable):
  - Workers classification → assigns the `workers` category
  - Breaking detection → powers the Home ticker + Live breaking list
- Public pages (RTL): Home, Live, Membership, Contact, Article detail, Search
- Live page YouTube player (URL/ID configurable via admin)
- Contact form stores submissions; manageable in Filament

## Requirements

- PHP 8.3 + extensions (Filament requires `ext-intl`)
- MySQL/MariaDB
- Node.js + npm (for Vite assets)

## Local Setup

1) Install backend deps:
- `composer install`

2) Configure env:
- `cp .env.example .env`
- Set `DB_*` to your local database
- Optional: set `ADMIN_EMAIL` / `ADMIN_PASSWORD` (seeded admin login)

3) Generate key + migrate/seed:
- `php artisan key:generate`
- `php artisan migrate --seed`

4) Install/build assets:
- `npm ci`
- `npm run dev` (or `npm run build`)

5) Run:
- `php artisan serve`
- Admin: `http://127.0.0.1:8000/admin`

## Admin Panel (Filament)

- URL: `/admin`
- Admin user is created by seeder using:
  - `ADMIN_EMAIL` / `ADMIN_PASSWORD` (defaults to `admin@shaghilla.org` / `password` if not set)
- Key sections:
  - Feed sources, Articles, Keyword rules, Video items, Site settings
  - Service Requests (contact page settings) + Contact messages
  - Membership (membership page content) + Membership applications

## RSS Importer

- Run manually: `php artisan news:import-rss`
- Scheduled hourly via `php artisan schedule:run` (cPanel cron recommended every minute).

## Deployment (cPanel)

See `DEPLOYMENT.md`.

### cPanel (No SSH) workflow

- Build an upload package locally: `./scripts/build-cpanel-upload.sh`
- Upload:
  - `cpanel_upload/app/` → `/home/USER/app/`
  - `cpanel_upload/public_html/` → `/home/USER/public_html/`
- Create tables/seeds in phpMyAdmin: import `database/shaghilla_install.sql`

## No-SSH RSS Imports (cPanel-friendly)

If your hosting does **not** allow running `php artisan`, use the built-in URL trigger:

- URL: `GET|POST /tasks/import-rss/{secret}`
- Secret is stored in DB `site_settings.key = rss_import_secret`

Example cron (cPanel Cron Jobs):

`curl -fsS -X POST "https://shaghilla.org/tasks/import-rss/<secret>" -d "ts=$(date +\%s)" >/dev/null 2>&1`

You can also run imports manually from the admin panel: **Automation → RSS Import**.

## Tests

- `php artisan test`
