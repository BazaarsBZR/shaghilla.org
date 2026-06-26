# Deployment Guide (cPanel, No SSH / No Terminal)

## Overview

This guide is for deploying a Laravel-style web app on cPanel when:
- You **cannot** run SSH/terminal commands (dependency installers, framework CLI tools, etc.)
- The document root **must remain** `public_html`
- You deploy by **uploading/replacing files only**

The workflow uses a two-folder layout:
- A **private app folder** outside `public_html` (framework code + vendor/dependencies + configs)
- The **public webroot** `public_html` (public assets + `index.php`)

## Folder Layout

Create / use two folders on the server:

- `<APP_DIR>` (private, outside webroot)
  - Contains: `app/`, `bootstrap/`, `config/`, `vendor/`, `resources/`, `routes/`, `storage/`, etc.
  - Contains your secrets file (example: `.env`)
- `<PUBLIC_DIR>` (public webroot; this is `public_html`)
  - Contains: `index.php`, `.htaccess`, `/build` or `/assets` (compiled front-end files), images, favicon, robots.txt, etc.
  - Must **not** contain: `.env`, `vendor/`, `storage/`, source code, database dumps

**Important:** Your `<PUBLIC_DIR>/index.php` must boot the app from `<APP_DIR>` and set the public path to `<PUBLIC_DIR>`.

Example `index.php` pattern (adjust `<APP_DIR>` path):

```php
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$appRoot = realpath(__DIR__ . '/../<APP_DIR_NAME>') ?: (__DIR__ . '/../<APP_DIR_NAME>');

require $appRoot . '/vendor/autoload.php';

$app = require $appRoot . '/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$app->handleRequest(Request::capture());
```

If your hosting points the domain to a subfolder like `<PUBLIC_DIR>/<SITE_FOLDER>/` (common for addon domains), then:
- Upload the public files into `<PUBLIC_DIR>/<SITE_FOLDER>/`
- Ensure `index.php` uses the correct relative path to `<APP_DIR>` (often `../../app` instead of `../app`), or use an auto-detect approach.

## First Install

### 1) Prepare your upload artifact (done by the developer)

You should receive a zip file from the developer, for example:
- `<ZIP_NAME>.zip`

It should contain **two folders**:
- `app/` → upload to `<APP_DIR>`
- `public_html/` → upload to `<PUBLIC_DIR>`

**Must be included in the artifact:**
- `vendor/` (because you cannot run `composer install` on the server)
- compiled front-end assets (because you may not be able to build on the server)

### 2) Upload the files (done by the operator)

In cPanel **File Manager**:

1) Create the private folder `<APP_DIR>` (outside `public_html`)
2) Upload `<ZIP_NAME>.zip` somewhere you can extract it (often your home directory)
3) Extract the zip
4) Move/Copy:
   - Extracted `app/` → `<APP_DIR>`
   - Extracted `public_html/` contents → `<PUBLIC_DIR>`

### 3) Create the secrets file (example: `.env`)

1) In `<APP_DIR>`, create a secrets file (often named `.env`)
2) Copy values from the provided `.env.example`
3) Set at minimum:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_URL=<SITE_URL>`
   - database connection values (`DB_*`)
   - a strong app key (`APP_KEY=base64:...`)

Generate an `APP_KEY` locally on your computer (any PHP 7.4+ works):

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32));"
```

### 4) Create the database and tables (phpMyAdmin)

1) In cPanel, create:
   - database `<DB_NAME>`
   - database user `<DB_USER>`
   - assign user to database with **ALL PRIVILEGES**
2) Open **phpMyAdmin**
3) Select `<DB_NAME>`
4) Import the SQL file provided by the developer (example: `install.sql`)
5) Confirm tables exist and default rows (if any) were inserted

### 5) Set permissions

Make sure the writable folders are writable (see **Permissions** below).

### 6) Verify install

- Open `<SITE_URL>/` and confirm the homepage loads
- Open the admin URL (example: `<SITE_URL>/admin`) and log in
- Submit the contact form (if present) and confirm it appears in admin

## Updates

### Standard update workflow

When the developer ships a new `<ZIP_NAME>.zip`:

1) **Backup**
   - Export the database from phpMyAdmin (Export → Quick)
   - In File Manager, rename:
     - `<APP_DIR>` → `<APP_DIR>_backup_YYYYMMDD`
     - (optional) create a zip backup of `<PUBLIC_DIR>` if you plan to replace many public files
2) Upload the new `<ZIP_NAME>.zip` and extract it
3) Replace files:
   - Replace app code in `<APP_DIR>` (copy new `app/` over old)
   - Replace public assets in `<PUBLIC_DIR>` (copy new `public_html/` contents over old)
4) **Do not overwrite** your secrets file
   - Keep `<APP_DIR>/.env` (or your secrets file) as-is
5) Apply any DB changes (see **DB Changes** below)
6) Clear caches (see **Cache Clearing** below)
7) Verify key pages and admin login

### Update Loop (Developer ↔ Operator)

**Developer (AI) responsibilities**
- Ship `<ZIP_NAME>.zip` with:
  - `app/` (includes `vendor/`)
  - `public_html/` (includes compiled assets)
- If DB changes are needed, ship:
  - `db/patch_YYYYMMDD.sql` (safe incremental SQL), and a note of what it changes
- Provide short release notes:
  - what changed
  - whether DB changes are required
  - whether caches must be cleared

**Operator (you) responsibilities**
- Extract `<ZIP_NAME>.zip`
- Upload/replace:
  - `app/` → `<APP_DIR>`
  - public assets → `<PUBLIC_DIR>`
- Run DB patch SQL (if provided)
- Clear caches
- Verify:
  - homepage, article/page routes, admin login, forms, scheduled tasks (if any)
- Report back any errors with:
  - screenshots
  - recent server error log excerpt (cPanel → Errors)

## DB Changes

Without terminal access, you have two safe options:

### Option A (Recommended): phpMyAdmin SQL patch files

1) Developer provides a patch SQL file (example: `db/patch_YYYYMMDD.sql`)
2) In phpMyAdmin:
   - select `<DB_NAME>`
   - Import the patch SQL
3) Confirm the expected new columns/tables exist

**Rules for safe patch files**
- Prefer additive changes (CREATE TABLE, ADD COLUMN) over destructive ones
- Avoid dropping columns/tables unless explicitly required and backed up
- Always backup your DB before importing patches

### Option B: Admin-only “Maintenance” page (web-based)

If the app includes an admin-only maintenance tool, it can provide buttons like:
- “Run database updates”
- “Clear caches”

**Security requirements**
- Must require admin authentication
- Must be protected by CSRF
- Must not be accessible to guests
- Should show a clear success/failure message

If your app doesn’t have this yet, ask the developer to add it.

## Cron / Automation

RSS imports do not happen automatically unless you set up a cron job on the server.

### Option A (preferred if PHP CLI is allowed): Laravel scheduler

Set a cPanel cron (every minute):

`* * * * * php /home/USER/<APP_DIR>/artisan schedule:run >/dev/null 2>&1`

This will run all scheduled tasks, including RSS import (hourly) and any other automation configured in `routes/console.php`.

### Option B (no SSH / no php in cron): URL trigger

Set a cPanel cron (every hour):

`0 * * * * curl -fsS -X POST "https://shaghilla.org/tasks/import-rss/<rss_import_secret>" -d "ts=$(date +\%s)" >/dev/null 2>&1`

Where to get `<rss_import_secret>`:
- Filament admin → `Automation → RSS Import` (Cron URL)
- Or DB table `site_settings` key `rss_import_secret`

If scheduled tasks stop running (RSS stays on yesterday’s news):
- Filament admin → `Automation → Maintenance → Clear scheduler locks`

## Cache Clearing

Without terminal, use one (or both) of these:

### Option A: Use an admin “Clear caches” action

If the app provides an admin-only action, run it after every update.

### Option B: Delete cache files via File Manager

Common locations in Laravel-style apps (yours may vary):

- `<APP_DIR>/bootstrap/cache/` (delete files inside)
- `<APP_DIR>/storage/framework/cache/` (delete files inside)
- `<APP_DIR>/storage/framework/views/` (delete compiled template files)
- `<APP_DIR>/storage/framework/sessions/` (optional; only if sessions are broken)

**Tip:** Delete the contents, not the folders themselves (keep the folders present).

## Permissions

### What must be writable

At minimum, the app usually needs write access to:
- `<APP_DIR>/storage/` (all subfolders)
- `<APP_DIR>/bootstrap/cache/`

If your app allows uploads, also ensure:
- `<APP_DIR>/storage/app/` (or the app’s uploads directory)

### How to verify in cPanel

1) Open cPanel → **File Manager**
2) Find the folder (example: `<APP_DIR>/storage`)
3) Right click → **Change Permissions**
4) Typical settings:
   - folders: `755` or `775`
   - files: `644`

If you see “permission denied” errors in the app:
- Increase write permissions for the required folders
- Confirm the folder owner matches the PHP process user (hosting support can confirm this)

## Security Rules

- Never upload `.env` (or any secrets file) into `<PUBLIC_DIR>`
- Keep secrets only in `<APP_DIR>` (outside webroot)
- Do not place `vendor/`, `storage/`, or source code in `<PUBLIC_DIR>`
- Treat secret URLs (webhooks / cron URLs) like passwords:
  - long random strings
  - never share publicly
  - rotate if leaked
- Remove or disable any one-time install scripts after setup
- Prefer HTTPS; avoid exposing debug output in production (`APP_DEBUG=false`)

## Operator Checklist

- [ ] Backup database (phpMyAdmin Export)
- [ ] Backup `<APP_DIR>` (rename or zip in File Manager)
- [ ] Upload and extract `<ZIP_NAME>.zip`
- [ ] Copy `app/` → `<APP_DIR>` (do not overwrite secrets file)
- [ ] Copy public assets → `<PUBLIC_DIR>`
- [ ] Apply DB patch SQL (if provided)
- [ ] Clear caches (admin tool or delete cache folders)
- [ ] Verify permissions on writable folders
- [ ] Verify key pages (home, a detail page, admin login, forms)
- [ ] Check cPanel error logs for new errors after deploy
