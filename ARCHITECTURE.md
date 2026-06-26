# Shaghilla.org — Architecture & Developer Guide

> **رابطة الشغيلة** — an Arabic-first (RTL) Lebanon news + live-stream platform.
> Laravel 11 / PHP 8.3 application with a Filament admin panel, automated RSS &
> Al-Manar "urgent" news ingestion, a no-code homepage/header builder, and a
> cPanel (no-SSH) deployment model.

This document explains **how the system works end to end**: the domain model and
database, the public HTTP layer, the import/automation services, the Filament
admin surface, the front-end, and how it is configured and deployed. It is
generated from a close reading of the source and is intended as the primary
onboarding reference for the codebase.

> Each subsystem section below was produced by reading the actual source files
> and then independently fact-checked against that source; claims are cited as
> `path:line`.

---

## 1. What the platform does

Shaghilla is a labour-movement ("workers' league") news portal for a Lebanese
audience. Its core loop:

1. **Ingest** — A scheduler (or a cron-triggered secret URL) pulls configured
   **RSS feed sources** and an **Al-Manar urgent** feed, de-duplicates items,
   downloads images, and creates **Articles**.
2. **Classify** — Admin-editable **keyword rules** tag incoming articles as
   *workers* (assigns the Workers category) and detect *breaking* news (which
   powers the homepage ticker and the Live page's breaking list). Per-feed
   **destination** (`home` / `breaking` / `both`) and per-article **lock flags**
   let editors override automation.
3. **Present** — Arabic RTL public pages: **Home** (admin-composed sections),
   **Live** (YouTube + self-hosted video + breaking feed), **Article**,
   **Search**, **Membership** (volunteer application form), **Contact**, and
   CMS-style **Pages**.
4. **Administer** — A Filament (English UI) admin panel at `/admin` provides CRUD
   over all content plus bespoke tools: a **Homepage/Header/Appearance builder**,
   **RSS automation**, a **breaking-news scraper**, **maintenance tools**, and an
   in-browser **deploy ("Updates")** page.

## 2. Technology stack

| Layer | Choice |
|---|---|
| Language / Runtime | PHP 8.2+ (targets 8.3) |
| Framework | Laravel 11 (`laravel/framework ^11.31`) |
| Admin panel | Filament 3.2 (`filament/filament`) + `pxlrbt/filament-excel`, `saade/filament-laravel-log` |
| UI kit | `mkocansey/bladewind` (Blade components) |
| Front-end | Blade + Tailwind CSS 3.4 + Alpine.js 3 + Vue 3 (`v-breaking-news-ticker`), bundled by Vite 6 |
| Database | MySQL / MariaDB |
| Cache / Queue / Session | `file` / `sync` / `file` (shared-hosting friendly; no Redis or queue worker) |
| Locale | `ar` (Arabic, RTL); timezone `Asia/Beirut` |
| Deployment | cPanel shared hosting, no SSH (private `app/` + public `public_html/` split) |
| Dev tooling | Pint, Pail, Sail, PHPUnit 11, Collision, Faker |

## 3. Repository layout

```
app/
  Console/Commands/      news:import-rss, news:import-almanar-urgent
  Http/Controllers/      public-facing controllers + import webhooks
  Http/Middleware/       SetLocaleToEnglish (forces admin panel to English)
  Models/                12 Eloquent models (Article, Category, FeedSource, …)
  Services/              RssImporter, AlManarUrgentImporter, ArticleScraper,
                         BreakingNewsLimiter, DashboardUpdater
  Filament/
    Resources/           12 CRUD resources (11 visible + 1 legacy stub)
    Pages/               9 custom admin pages (builders, automation, updates)
    Widgets/             newsroom stats + quick actions
  Providers/             AppServiceProvider, Filament/AdminPanelProvider
config/                  app, services, filesystems (public_uploads disk), …
database/
  migrations/            16 migrations
  seeders/               admin user, categories, feeds, keyword rules, settings
  *.sql                  shaghilla_install.sql + 7 idempotent patch files
resources/views/         44 Blade views (layouts, components, pages, partials)
routes/web.php           public routes + secret import webhooks
routes/console.php       scheduler (RSS heartbeat + Al-Manar urgent)
scripts/                 build-cpanel-upload.sh (no-SSH deploy packager)
public/                  webroot; compiled assets + published vendor assets
lang/                    ar/en translations (+ published bladewind locales)
```

## 4. Request lifecycle (public site)

```
Browser ─▶ public_html/index.php (cPanel-aware bootstrap)
        ─▶ Laravel kernel ─▶ routes/web.php
        ─▶ Controller (Home / Article / Live / Search / Membership / Contact / …)
        ─▶ Eloquent models + cached SiteSetting key/values
        ─▶ Blade view (RTL layout + components) ─▶ HTML
```

Automation runs out-of-band: `php artisan schedule:run` (every-minute cron) or a
`POST /tasks/import-rss/{secret}` webhook invokes the importer services, which
write new `Article` rows that the public controllers then render.

## 5. Table of contents

- [Domain Models & Database Schema](#domain-models--database-schema)
- [Public HTTP Layer (routes & controllers)](#public-http-layer-routes--controllers)
- [Services, Console Commands & Scheduling](#services-console-commands--scheduling)
- [Filament Admin — Resources (CRUD)](#filament-admin--resources-crud)
- [Filament Admin — Custom Pages, Widgets & Panel](#filament-admin--custom-pages-widgets--panel)
- [Frontend: Views, Layouts, Assets, i18n](#frontend-views-layouts-assets-i18n)
- [Configuration, Deployment & Operations](#configuration-deployment--operations)

---


---

## Domain Models & Database Schema

### Entity-Relationship Overview

The application is an Arabic-language labour-news portal. The core content graph is:

```
FeedSource ──< Article >── Category
                 │
                 └── (show_on_home, is_breaking flags drive homepage & ticker)

VideoItem          (YouTube-linked, typed: episode / report / home)
HostedVideo        (self-hosted video on public_uploads disk)
SitePage           (header/footer nav entries; type: route | page | external)
SiteSetting        (key/value config store, fully cached per request)
KeywordRule        (Arabic keyword lists; type: worker | breaking)
SharePlatform      (social sharing buttons with URL templates)
MembershipApplication (volunteer/membership intake form submissions)
ContactMessage     (contact/service-request form submissions)
User               (admin-only, gates Filament panel via ADMIN_EMAIL env)
```

---

### Eloquent Models

#### `Article` — `app/Models/Article.php`

| Property | Detail |
|---|---|
| Table | `articles` (implicit) |
| Fillable | `feed_source_id`, `category_id`, `title`, `slug`, `excerpt`, `content`, `canonical_url`, `canonical_url_hash`, `guid`, `guid_hash`, `image_url`, `published_at`, `imported_at`, `is_breaking`, `is_breaking_locked`, `show_on_home`, `show_on_home_locked`, `language`, `status` |
| Casts | `published_at`, `imported_at` → datetime; `is_breaking`, `is_breaking_locked`, `show_on_home`, `show_on_home_locked` → boolean |
| Relationships | `feedSource()` belongsTo `FeedSource`; `category()` belongsTo `Category` |

**Saving hook** (`Article.php:46`): auto-computes `canonical_url_hash` as SHA-256 of `canonical_url` if missing; computes `guid_hash` as SHA-256 of `"{feed_source_id|manual}|{guid??canonical_url??slug??title}"`. Defaults `imported_at` and `published_at` to `now()`, `language` to `'ar'`, `status` to `'published'`, and `show_on_home` to `true` when null (only when the column exists).

**`hasShowOnHomeColumn()`** (`Article.php:67`): static helper that memoizes a `Schema::hasColumn('articles','show_on_home')` call to guard code paths that run before the later alter migration.

---

#### `Category` — `app/Models/Category.php`

| Property | Detail |
|---|---|
| Table | `categories` |
| Fillable | `slug`, `name_ar`, `name_en`, `sort_order`, `is_system` |
| Casts | `sort_order` → integer; `is_system` → boolean |
| Relationships | `articles()` hasMany `Article` |
| Accessor | `getNameAttribute()` (`Category.php:28`): returns `name_en ?: name_ar` |

`lebanon` and `workers` are seeded with `is_system=true`, but — unlike `SitePage` — `Category` has no model-level deletion guard, so the flag is advisory only.

---

#### `ContactMessage` — `app/Models/ContactMessage.php`

| Property | Detail |
|---|---|
| Table | `contact_messages` |
| Fillable | `name`, `email`, `subject`, `message` |
| `UPDATED_AT` | `null` — only `created_at` is written (`ContactMessage.php:9`) |
| Relationships | none |

---

#### `FeedSource` — `app/Models/FeedSource.php`

| Property | Detail |
|---|---|
| Table | `feed_sources` |
| Fillable | `name`, `url`, `destination`, `is_active`, `last_fetched_at`, `etag`, `last_modified`, `default_category_id` |
| Casts | `is_active` → boolean; `last_fetched_at` → datetime |
| Constants | `DESTINATION_HOME='home'`, `DESTINATION_BOTH='both'`, `DESTINATION_BREAKING='breaking'` (`FeedSource.php:12–14`) |
| Relationships | `defaultCategory()` belongsTo `Category` (FK `default_category_id`); `articles()` hasMany `Article` |

**`destination` Attribute** (`FeedSource.php:32`): getter and setter both normalize to lowercase (and trim), validate against the three allowed values, and fall back to `'both'` for any invalid input.

---

#### `HostedVideo` — `app/Models/HostedVideo.php`

| Property | Detail |
|---|---|
| Table | `hosted_videos` |
| Fillable | `title`, `slug`, `video_path`, `poster_path`, `published_at`, `sort_order`, `is_active` |
| Casts | `published_at` → datetime; `sort_order` → integer; `is_active` → boolean |
| Relationships | none |

**`videoUrl()`** / **`posterUrl()`** (`HostedVideo.php:47–59`): resolve paths via `Storage::disk('public_uploads')`.

**Saving hook** (`HostedVideo.php:30`): slugifies from `title` via `Str::slug`, falls back to `Str::random(8)`, then ensures uniqueness with an integer suffix loop.

**Cache**: flushes `'home.hosted_videos'` on saved and deleted (`HostedVideo.php:39–44`).

---

#### `KeywordRule` — `app/Models/KeywordRule.php`

| Property | Detail |
|---|---|
| Table | `keyword_rules` |
| Fillable | `type`, `keyword`, `is_active` |
| Casts | `is_active` → boolean |
| Constants | `TYPE_WORKER='worker'`, `TYPE_BREAKING='breaking'` (`KeywordRule.php:9–10`) |
| Relationships | none |

---

#### `MembershipApplication` — `app/Models/MembershipApplication.php`

| Property | Detail |
|---|---|
| Table | `membership_applications` |
| Fillable | `full_name`, `mother_name`, `birth_date`, `registry_number`, `registration_place`, `phone`, `emergency_phone`, `email`, `address`, `profession`, `marital_status`, `children_count`, `blood_type`, `volunteer_areas`, `volunteer_other`, `has_volunteer_experience`, `volunteer_experience_details`, `id_document_path`, `signature_name`, `status`, `admin_notes`, `ip_address`, `user_agent` |
| Casts | `birth_date` → date; `children_count` → integer; `volunteer_areas` → array; `has_volunteer_experience` → boolean |
| Constants | `STATUS_PENDING='pending'`, `STATUS_APPROVED='approved'`, `STATUS_REJECTED='rejected'`; `MARITAL_MARRIED='married'`, `MARITAL_SINGLE='single'` |
| Relationships | none |

---

#### `SharePlatform` — `app/Models/SharePlatform.php`

| Property | Detail |
|---|---|
| Table | `share_platforms` |
| Fillable | `name`, `slug`, `icon`, `share_url_template`, `use_native_share`, `is_active`, `sort_order` |
| Casts | `use_native_share`, `is_active` → boolean; `sort_order` → integer |
| Relationships | none |

**`activeCached()`** (`SharePlatform.php:61`): returns `Collection<SharePlatform>` from a forever cache key `'share_platforms.active.v1'`, ordered `sort_order` → `name`. Guards against missing table via `hasTable()` static memoization.

**`buildShareUrl(string $url, string $title)`** (`SharePlatform.php:80`): replaces `{url}` and `{title}` placeholders in `share_url_template` with `rawurlencode`-d values; returns null when template is empty.

**`iconOptions()`** (`SharePlatform.php:103`): returns `[fa-icon => 'Label']` map of 13 Font Awesome brand icons.

Cache is flushed on saved and deleted events.

---

#### `SitePage` — `app/Models/SitePage.php`

| Property | Detail |
|---|---|
| Table | `site_pages` |
| Fillable | `type`, `route_name`, `slug`, `external_url`, `title_ar`, `title_en`, `content_html_ar`, `content_html_en`, `open_in_new_tab`, `show_in_header`, `show_in_footer`, `sort_order`, `is_system`, `is_active` |
| Casts | `open_in_new_tab`, `show_in_header`, `show_in_footer`, `is_system`, `is_active` → boolean; `sort_order` → integer |
| Constants | `TYPE_ROUTE='route'`, `TYPE_PAGE='page'`, `TYPE_EXTERNAL='external'` |
| Relationships | none |

**`headerMenu()`** / **`footerMenu()`** (`SitePage.php:64`/`85`): cached for 1 day under keys `'site.menu.header'` / `'site.menu.footer'`; each guards against missing table.

**`displayTitle()`** (`SitePage.php:106`): locale-aware, returns `title_en` when app locale is `'en'` and it is non-empty, otherwise `title_ar`.

**`url()`** (`SitePage.php:117`): match on type — `TYPE_ROUTE` uses named route (guarded by `Route::has`), `TYPE_EXTERNAL` uses `external_url`, the default branch (`TYPE_PAGE`) uses `route('pages.show', ['slug' => ...])`, all fall back to `'#'`.

**Saving hook** (`SitePage.php:140`, in `boot()`): nullifies out-of-scope fields per type (slug/content cleared for non-PAGE, route_name cleared for non-ROUTE, external_url cleared for non-EXTERNAL). Slugifies `slug` with `Str::slug(slug, '-')` for TYPE_PAGE.

**Deletion guard** (`SitePage.php:49`, in `booted()`): throws `ValidationException` if `is_system` is true.

---

#### `SiteSetting` — `app/Models/SiteSetting.php`

| Property | Detail |
|---|---|
| Table | `site_settings` |
| Fillable | `key`, `value` |
| Relationships | none |

Primary static accessors:

- **`getValue(string $key, ?string $default = null)`** (`SiteSetting.php:74`): loads all rows once per request into a static `$memo` array via `loadMemo()`, populated from a forever cache (`'site_settings.all.v1'`, the `rememberForever` call at `SiteSetting.php:57`). Falls back to a per-key DB query if the memo lacks the key or the cache load fails.
- **`getBool(string $key, bool $default = false)`** (`SiteSetting.php:99`): interprets `'1'`, `'true'`, `'yes'`, `'on'` as true.
- **`getInt(string $key, int $default = 0)`** (`SiteSetting.php:116`): validates with `/^-?\d+$/` regex before casting.
- **`setValues(array $values)`** (`SiteSetting.php:135`): bulk upsert with type coercion (bool→'1'/'0', int/float→string), updates in-memory memo, flushes cache.

---

#### `User` — `app/Models/User.php`

| Property | Detail |
|---|---|
| Table | `users` |
| Fillable | `name`, `email`, `password` |
| Hidden | `password`, `remember_token` |
| Casts | `email_verified_at` → datetime; `password` → hashed |
| Implements | `FilamentUser` |

**`canAccessPanel(Panel $panel)`** (`User.php:51`): gates Filament admin access. If `ADMIN_EMAIL` env is set (non-empty), only that exact email is allowed; otherwise all users may access the panel.

---

#### `VideoItem` — `app/Models/VideoItem.php`

| Property | Detail |
|---|---|
| Table | `video_items` |
| Fillable | `type`, `title`, `youtube_url`, `thumbnail_url`, `published_at`, `sort_order`, `is_active` |
| Casts | `published_at` → datetime; `sort_order` → integer; `is_active` → boolean |
| Constants | `TYPE_EPISODE='episode'`, `TYPE_REPORT='report'`, `TYPE_HOME='home'` |
| Relationships | none |

**Cache**: flushes `'home.videos'`, `'live.episodes'`, `'live.reports'` on saved and deleted (`VideoItem.php:33`).

---

### Data Dictionary — All Tables

#### Infrastructure / Framework tables

| Table | Key columns | Notes |
|---|---|---|
| `users` | `id`, `name`, `email` (unique), `email_verified_at`, `password`, `remember_token`, `timestamps` | Created by `0001_01_01_000000` |
| `password_reset_tokens` | `email` (PK), `token`, `created_at` | Same migration |
| `sessions` | `id` (PK, string), `user_id` (index), `ip_address(45)`, `user_agent` (text), `payload` (longText), `last_activity` (index) | Same migration |
| `cache` | `key` (PK), `value` (mediumText), `expiration` | `0001_01_01_000001` |
| `cache_locks` | `key` (PK), `owner`, `expiration` | Same migration |
| `jobs` | `id`, `queue` (index), `payload` (longText), `attempts` (tinyint unsigned), `reserved_at`, `available_at`, `created_at` | `0001_01_01_000002` |
| `job_batches` | `id` (PK, string), `name`, `total_jobs`, `pending_jobs`, `failed_jobs`, `failed_job_ids` (longText), `options` (mediumText nullable), `cancelled_at`, `created_at`, `finished_at` | Same migration |
| `failed_jobs` | `id`, `uuid` (unique), `connection` (text), `queue` (text), `payload` (longText), `exception` (longText), `failed_at` (timestamp useCurrent) | Same migration |

#### Domain tables

**`categories`** — `2026_01_13_152255_create_categories_table.php`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK |
| `slug` | varchar | UNIQUE |
| `name_ar` | varchar | NOT NULL |
| `name_en` | varchar | nullable |
| `sort_order` | unsigned int | default 0 |
| `is_system` | boolean | default false |
| `created_at`, `updated_at` | timestamp | |

Indexes: `sort_order`.

---

**`feed_sources`** — `2026_01_13_152256_create_feed_sources_table.php` + altered by `2026_01_22_150000_add_feed_destination_and_article_home_flag.php`

| Column | Type | Constraints | Added by |
|---|---|---|---|
| `id` | bigint unsigned | PK | initial |
| `name` | varchar | NOT NULL | initial |
| `url` | text | NOT NULL | initial |
| `is_active` | boolean | default true | initial |
| `last_fetched_at` | timestamp | nullable | initial |
| `etag` | varchar | nullable | initial |
| `last_modified` | varchar | nullable | initial |
| `default_category_id` | bigint unsigned | nullable, FK → categories, nullOnDelete | initial |
| `created_at`, `updated_at` | timestamp | | initial |
| `destination` | varchar(20) | default `'both'`, indexed | alter migration |

Indexes: `is_active`, `destination`.
Foreign keys: `default_category_id` → `categories.id` (nullOnDelete).

---

**`contact_messages`** — `2026_01_13_152257_create_contact_messages_table.php`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK |
| `name` | varchar | NOT NULL |
| `email` | varchar | NOT NULL |
| `subject` | varchar | NOT NULL |
| `message` | text | NOT NULL |
| `created_at` | timestamp | useCurrent, indexed |

No `updated_at` column (matches `ContactMessage::UPDATED_AT = null`).

---

**`keyword_rules`** — `2026_01_13_152257_create_keyword_rules_table.php`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK |
| `type` | varchar | NOT NULL |
| `keyword` | varchar | NOT NULL |
| `is_active` | boolean | default true |
| `created_at`, `updated_at` | timestamp | |

Indexes: composite UNIQUE `(type, keyword)`; composite index `(type, is_active)`.

---

**`site_settings`** — `2026_01_13_152257_create_site_settings_table.php`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK |
| `key` | varchar | UNIQUE |
| `value` | text | nullable |
| `created_at`, `updated_at` | timestamp | |

---

**`video_items`** — `2026_01_13_152257_create_video_items_table.php`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK |
| `type` | varchar | NOT NULL |
| `title` | varchar | NOT NULL |
| `youtube_url` | text | NOT NULL |
| `thumbnail_url` | text | nullable |
| `published_at` | timestamp | nullable, indexed |
| `sort_order` | int | default 0 |
| `is_active` | boolean | default true |
| `created_at`, `updated_at` | timestamp | |

Indexes: `published_at`; composite `(type, is_active, sort_order)`.

---

**`articles`** — `2026_01_13_152259_create_articles_table.php` + altered by `2026_01_22_150000_add_feed_destination_and_article_home_flag.php`

| Column | Type | Constraints | Added by |
|---|---|---|---|
| `id` | bigint unsigned | PK | initial |
| `feed_source_id` | bigint unsigned | nullable, FK → feed_sources, nullOnDelete | initial |
| `category_id` | bigint unsigned | NOT NULL, FK → categories, restrictOnDelete | initial |
| `title` | text | NOT NULL | initial |
| `slug` | varchar | UNIQUE | initial |
| `excerpt` | text | nullable | initial |
| `content` | longText | nullable | initial |
| `canonical_url` | text | nullable | initial |
| `canonical_url_hash` | char(64) | nullable, UNIQUE | initial |
| `guid` | text | nullable | initial |
| `guid_hash` | char(64) | NOT NULL | initial |
| `image_url` | text | nullable | initial |
| `published_at` | timestamp | nullable, indexed | initial |
| `imported_at` | timestamp | useCurrent, indexed | initial |
| `is_breaking` | boolean | default false, indexed | initial |
| `language` | varchar(5) | default `'ar'` | initial |
| `status` | varchar | default `'published'`, indexed | initial |
| `created_at`, `updated_at` | timestamp | | initial |
| `show_on_home` | boolean | default true, indexed | alter migration |
| `is_breaking_locked` | boolean | default false, indexed | alter migration |
| `show_on_home_locked` | boolean | default false, indexed | alter migration |

Unique indexes: `slug`, `canonical_url_hash`, composite `(feed_source_id, guid_hash)`.
Composite index: `(category_id, published_at)`.
Foreign keys: `feed_source_id` → `feed_sources.id` (nullOnDelete); `category_id` → `categories.id` (restrictOnDelete).

---

**`site_pages`** — `2026_01_14_020000_create_site_pages_table.php`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK |
| `type` | varchar | NOT NULL, indexed |
| `route_name` | varchar | nullable, UNIQUE |
| `slug` | varchar | nullable, UNIQUE |
| `external_url` | text | nullable |
| `title_ar` | varchar | NOT NULL |
| `title_en` | varchar | nullable |
| `content_html_ar` | longText | nullable |
| `content_html_en` | longText | nullable |
| `open_in_new_tab` | boolean | default false |
| `show_in_header` | boolean | default false, indexed |
| `show_in_footer` | boolean | default false, indexed |
| `sort_order` | int | default 0, indexed |
| `is_system` | boolean | default false |
| `is_active` | boolean | default true, indexed |
| `created_at`, `updated_at` | timestamp | |

---

**`hosted_videos`** — `2026_01_16_100000_create_hosted_videos_table.php`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK |
| `title` | varchar | NOT NULL |
| `slug` | varchar | UNIQUE |
| `video_path` | varchar | NOT NULL |
| `poster_path` | varchar | nullable |
| `published_at` | timestamp | nullable, indexed |
| `sort_order` | unsigned int | default 0 |
| `is_active` | boolean | default true, indexed |
| `created_at`, `updated_at` | timestamp | |

---

**`membership_applications`** — `2026_01_19_140000_create_membership_applications_table.php`; volunteer columns back-filled idempotently by `2026_01_19_150000_add_volunteer_fields_to_membership_applications_table.php`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK |
| `full_name` | varchar | NOT NULL |
| `mother_name` | varchar | nullable |
| `birth_date` | date | nullable, indexed |
| `registry_number` | varchar | nullable |
| `registration_place` | varchar | nullable |
| `phone` | varchar | NOT NULL |
| `emergency_phone` | varchar | nullable |
| `email` | varchar | nullable |
| `address` | varchar | nullable |
| `profession` | varchar | nullable |
| `marital_status` | varchar | nullable, indexed (`'married'` / `'single'`) |
| `children_count` | unsigned int | nullable |
| `blood_type` | varchar | nullable |
| `volunteer_areas` | json | nullable |
| `volunteer_other` | varchar | nullable |
| `has_volunteer_experience` | boolean | nullable |
| `volunteer_experience_details` | text | nullable |
| `id_document_path` | varchar | nullable |
| `signature_name` | varchar | nullable |
| `status` | varchar | default `'pending'`, indexed (`pending` / `approved` / `rejected`) |
| `admin_notes` | text | nullable |
| `ip_address` | varchar(45) | nullable |
| `user_agent` | text | nullable |
| `created_at`, `updated_at` | timestamp | |

Note: the second volunteer-fields migration uses per-column `hasColumn` guards so it is safe to run on databases that already had these columns from the original migration.

---

**`share_platforms`** — `2026_02_05_000000_create_share_platforms_table.php`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK |
| `name` | varchar | NOT NULL |
| `slug` | varchar | UNIQUE |
| `icon` | varchar | nullable |
| `share_url_template` | text | nullable |
| `use_native_share` | boolean | default false |
| `is_active` | boolean | default true, indexed |
| `sort_order` | unsigned int | default 0 |
| `created_at`, `updated_at` | timestamp | |

---

### Seeders

`DatabaseSeeder` runs all seeders in this order: `CategorySeeder` → `KeywordRuleSeeder` → `FeedSourceSeeder` → `SiteSettingSeeder` → `SitePageSeeder` → `SharePlatformSeeder` → `AdminUserSeeder`. All seeders use `updateOrCreate` so they are safe to re-run.

| Seeder | What it inserts |
|---|---|
| `AdminUserSeeder` | Single admin user: email from `ADMIN_EMAIL` env (default `admin@shaghilla.org`), password from `ADMIN_PASSWORD` env. **No default password** — if `ADMIN_PASSWORD` is unset the seeder skips with a warning, so no admin is ever created with a committed/guessable credential. `email_verified_at = now()`. |
| `CategorySeeder` | 2 system categories: `lebanon` (لبنان, sort 1) and `workers` (عمال, sort 2). Both flagged `is_system=true`. |
| `FeedSourceSeeder` | 3 RSS feed sources keyed on URL: Lebanon24 Lebanon news, Lebanon24 Breaking news, NNA English — all active, all defaulting to the `lebanon` category. |
| `KeywordRuleSeeder` | 30 Arabic `TYPE_WORKER` keywords (labour rights, unions, wages, social security, strikes, protests, ministry of labour, etc.) and 10 Arabic `TYPE_BREAKING` keywords (urgent, now, Beirut, Lebanon, strike, sit-in, protest, roadblock, escalation, clashes). |
| `SharePlatformSeeder` | 4 social platforms: WhatsApp (sort 10, URL template), X/Twitter (sort 20, URL template), Instagram (sort 30, `use_native_share=true`, no template), Facebook (sort 40, URL template). |
| `SitePageSeeder` | 4 system `TYPE_ROUTE` nav entries: `home` (الرئيسية, sort 1), `live` (مباشر, sort 2), `membership` (انتساب, sort 3), `contact` (طلب الخدمة, sort 4). All shown in header and footer, all `is_system=true`. |
| `SiteSettingSeeder` | 62 key/value config rows covering: live YouTube URL and playlist limit, ticker display limits, RSS fetch cron config (`rss_cron_enabled`, `rss_cron_interval_minutes`), Al-Manar urgent news API toggle/secret/cron config, breaking ticker behaviour (engine `'js'`, direction `'left'`, speed 90 px/s, gap 20 px), home hosted-video section config (layout, limits, placeholders), Arabic UI copy for contact and membership pages, header weather widget (Beirut lat 33.8938 / lon 35.5018), RSS import secret (`'change-me'`), and null last-run audit trail keys (`last_rss_import_at`, `last_rss_cron_hit_at`, etc.). |

### Alter Migrations Summary

| Migration | Change |
|---|---|
| `2026_01_19_150000_add_volunteer_fields_to_membership_applications_table.php` | Back-fills `registry_number`, `registration_place`, `volunteer_areas`, `volunteer_other`, `has_volunteer_experience`, `volunteer_experience_details` on `membership_applications` using per-column `hasColumn` guards. |
| `2026_01_22_150000_add_feed_destination_and_article_home_flag.php` | Adds `destination` (varchar 20, default `'both'`) to `feed_sources`; adds `show_on_home` (bool, default true), `is_breaking_locked` (bool, default false), `show_on_home_locked` (bool, default false) to `articles`. All new columns are indexed. |

---

## Public HTTP Layer (routes & controllers)

### Route Table

All routes are registered in `routes/web.php`. There are no route groups, prefixes, or named middleware groups applied globally in this file — middleware is attached per-route where needed. CSRF token validation is disabled for both webhook paths via `bootstrap/app.php:16–19`.

| Method | URI | Route name | Controller | Middleware |
|--------|-----|------------|------------|------------|
| GET | `/` | `home` | `HomeController` | (none) |
| GET | `/home/latest-news` | `home.latest` | `HomeController@latest` | (none) |
| GET | `/live` | `live` | `LiveController` | (none) |
| GET | `/search` | `search` | `SearchController` | (none) |
| GET | `/membership` | `membership` | `MembershipController@show` | (none) |
| POST | `/membership` | `membership.store` | `MembershipController@store` | `throttle:membership` (5/min/IP) |
| GET | `/contact` | `contact` | `ContactController@create` | (none) |
| POST | `/contact` | `contact.store` | `ContactController@store` | `throttle:contact` (10/min/IP) |
| GET | `/pages/{slug}` | `pages.show` | `SitePageController@show` | (none) |
| GET | `/news/{slug}` | `news.show` | `ArticleController@show` | (none) |
| GET | `/videos/{videoItem}` | `videos.show` | `VideoController@show` | (none) |
| GET | `/videos/hosted/{hostedVideo:slug}` | `hosted-videos.show` | `HostedVideoController@show` | (none) |
| GET | `/api/weather` | `api.weather` | `WeatherController` | (none) |
| GET | `/api/breaking` | `api.breaking` | `BreakingApiController` | `throttle:api-breaking` (120/min/IP) |
| GET | `/admin/membership-applications/{membershipApplication}/id-document` | `admin.membership-applications.id-document` | `Admin\MembershipApplicationDocumentController` | `auth` |
| GET\|POST | `/tasks/import-rss/{secret}` | `tasks.import-rss` | `RssImportWebhookController` | `throttle:rss-import` (5/min/IP), CSRF-exempt |
| GET\|POST | `/tasks/import-almanar-urgent/{secret}` | `tasks.import-almanar-urgent` | `AlManarUrgentImportWebhookController` | `throttle:almanar-urgent-import` (10/min/IP), CSRF-exempt |

---

### Rate Limiters

All five named limiters are defined in `AppServiceProvider::boot()` (`app/Providers/AppServiceProvider.php:25–43`). All key by caller IP address.

| Limiter name | Limit | Applied to |
|---|---|---|
| `membership` | 5 requests/minute | POST `/membership` |
| `contact` | 10 requests/minute | POST `/contact` |
| `api-breaking` | 120 requests/minute | GET `/api/breaking` |
| `rss-import` | 5 requests/minute | GET\|POST `/tasks/import-rss/{secret}` |
| `almanar-urgent-import` | 10 requests/minute | GET\|POST `/tasks/import-almanar-urgent/{secret}` |

---

### Controller Reference

#### `HomeController` — `app/Http/Controllers/HomeController.php`

Two public methods.

**`__invoke(Request $request): View`** — serves `GET /`

Loads a large set of `SiteSetting` values governing ticker size, news layout (`mosaic` or `grid`), and the video section. Then builds the page data through a hierarchy of caches:

| Cache key | TTL | Contents |
|---|---|---|
| `news.breaking.ticker` | 5 min | Published `Article` records where `is_breaking = true`, ordered by `imported_at` / `published_at` / `id` desc. Count clamped to `ticker_limit` setting (1–50, default 10). |
| `news.home.hero` | 5 min | First record from `baseHomeArticlesQuery()` (published, optionally filtered by `show_on_home` when the column exists). Suppressed (set to null after caching) when layout is `grid`. |
| `news.home.latest` | 5 min | Up to 120 records from `baseHomeArticlesQuery()` used as the in-memory pool for pagination. |
| `home.hosted_videos` | 10 min | `HostedVideo` records where `is_active = true`, ordered by `sort_order` then `published_at` desc (limit 30). |
| `home.videos` | 10 min | `VideoItem` records where `is_active = true` and `type = TYPE_HOME`, same ordering (limit 30). Shared with `LiveController`. |

In `mosaic` layout the hero is separated from the top-grid (`topSmallCount` items, clamped 0–12) and the remainder fills a paginated latest section. In `grid` layout there is no hero. Pagination is performed entirely in memory against the cached collection via `paginateLatestCollection()` — the pool is capped at `HOME_LATEST_TOTAL_LIMIT = 40` items; the DB fetch cap is `HOME_LATEST_CACHE_LIMIT = 120`.

If `home_hosted_videos_enabled` is true and the combined hosted + YouTube video collection comes back empty, placeholder `VideoItem` objects are synthesised from settings (`home_hosted_videos_placeholder_*`) — but only when `home_hosted_videos_placeholders_enabled` is true (default) and `home_hosted_videos_placeholder_count > 0` (default 8, clamped 0–12). (YouTube items are only fetched when `home_hosted_videos_include_youtube` is true.)

Membership and contact CTA sections are built from `SiteSetting` values. When either button mode is `custom`, the stored URL is validated with `filter_var(…, FILTER_VALIDATE_URL)` before use; invalid or empty values fall back to the named route.

Returns `pages.home`.

**`latest(Request $request): JsonResponse`** — serves `GET /home/latest-news`

Calls the same `buildHomeNewsData()` method. Returns JSON:
```json
{ "html": "<rendered partial>", "page": 1, "last_page": N, "total": N, "per_page": N }
```
The `html` value is the rendered `partials.home.latest-news-results` blade partial. Used for AJAX pagination on the home page.

---

#### `ArticleController` — `app/Http/Controllers/ArticleController.php`

**`show(string $slug): View`** — serves `GET /news/{slug}`

Queries `Article` by `slug` with `feedSource` and `category` eager-loaded. Returns HTTP 404 via `firstOrFail()` if not found. Returns `pages.article`. No caching; no status check — any article with a matching slug is served regardless of `status`.

---

#### `LiveController` — `app/Http/Controllers/LiveController.php`

**`__invoke(Request $request): View`** — serves `GET /live`

Reads `live_youtube_url` from `SiteSetting` and extracts the YouTube video ID via `App\Support\YouTube::extractId()`. Loads `VideoItem` playlist (shared `home.videos` cache, 10 min). Playlist limit is taken from `live_youtube_playlist_limit` (falling back to `home_video_grid_limit`), clamped 1–12.

Accepts a `?v=` query parameter whose value is also run through `YouTube::extractId()`. The selected YouTube ID priority is: query param → `live_youtube_url` → first playlist item.

Returns `pages.live` with `liveYoutubeUrl`, `liveYoutubeId`, `playlistVideos`, and `selectedYoutubeId`.

---

#### `SearchController` — `app/Http/Controllers/SearchController.php`

**`__invoke(Request $request): View`** — serves `GET /search`

Reads `?q=` query param, trims whitespace, truncates to 120 UTF-8 characters. When `$query` is non-empty, applies a `WHERE` with three `LIKE '%…%'` clauses on `title`, `excerpt`, and `content` (all combined with `OR`). Filters to `status = published`. Orders by `published_at` desc. Paginates at 18 per page with `withQueryString()`. Returns `pages.search`.

No full-text index is used — this is a plain `LIKE` scan.

---

#### `MembershipController` — `app/Http/Controllers/MembershipController.php`

**`show(Request $request): Response`** — serves `GET /membership`

Regenerates the CSRF token (`$request->session()->regenerateToken()`) and sets `Cache-Control: no-store` / `Pragma: no-cache` / `Expires: 1970-01-01` headers to prevent any proxy or browser from caching the form page. Loads page title, intro text, `letterHtml` (raw HTML rendered inline), success message, submit label, and ID upload label from `SiteSetting`. Returns `pages.membership`.

**`store(Request $request): RedirectResponse`** — serves `POST /membership` (throttle: 5/min/IP)

Validates 22 fields (21 input fields plus the `volunteer_areas.*` element rule). Notable rules:

| Field | Rules |
|---|---|
| `full_name` | required, string, max 255 |
| `birth_date` | required, date |
| `registry_number` | required, string, max 50 |
| `registration_place` | required, string, max 255 |
| `phone` | required, string, max 50 |
| `volunteer_areas` | nullable array, max 15 items; each in: `organizational,social,relief,health,media,education,logistics,administrative,field,other` |
| `agree` | accepted (checkbox) |
| `id_document` | nullable file, max 10 240 KB (10 MB), mimes: jpg,jpeg,png,webp,gif,pdf,heic,heif |
| `website` | nullable, string, max 200 — **honeypot** |

**Honeypot**: if `website` is non-empty the controller silently redirects to `route('membership')` with a success flash and never touches the database.

**Cross-field validation** (after `validate()`):
- If `other` is in `volunteer_areas` and `volunteer_other` is blank → back with Arabic error.
- If `has_volunteer_experience` is `true` and `volunteer_experience_details` is blank → back with Arabic error.

If `id_document` is present, the file is stored to `membership/id-documents` on the `local` disk (not publicly accessible). The path is saved in `MembershipApplication.id_document_path`. The record is created with `status = pending` (`STATUS_PENDING`), IP address, and User-Agent. Redirects back to `route('membership')` with a success flash.

---

#### `ContactController` — `app/Http/Controllers/ContactController.php`

**`create(): View`** — serves `GET /contact`

Loads page title, intro text, and five form labels from `SiteSetting`. Notably the `email` label defaults to `'رقم الهاتف'` (Arabic: "phone number"), indicating the field labelled `email` in the HTML is actually used for the caller's phone/contact detail. Returns `pages.contact`.

**`store(Request $request): RedirectResponse`** — serves `POST /contact` (throttle: 10/min/IP)

Validates: `name` (required, max 255), `email` (required string, max 255 — no email format check), `subject` (required, max 255), `message` (required, max 5 000 chars), `website` (nullable honeypot).

Same honeypot pattern as `MembershipController`. Creates a `ContactMessage` record with name, email (phone), subject, message. Redirects with success flash.

---

#### `SitePageController` — `app/Http/Controllers/SitePageController.php`

**`show(Request $request, string $slug): View`** — serves `GET /pages/{slug}`

Queries `SitePage` where `type = SitePage::TYPE_PAGE`, `slug = {slug}`, `is_active = true`. Returns 404 via `firstOrFail()` if not found or inactive. Returns `pages.page` with the page record and its `displayTitle()`.

---

#### `VideoController` — `app/Http/Controllers/VideoController.php`

**`show(VideoItem $videoItem): View`** — serves `GET /videos/{videoItem}`

Uses implicit route model binding on the `VideoItem` primary key. Calls `abort_unless($videoItem->is_active, 404)` — records in the DB but with `is_active = false` return a 404. Returns `pages.video`.

---

#### `HostedVideoController` — `app/Http/Controllers/HostedVideoController.php`

**`show(HostedVideo $hostedVideo): View`** — serves `GET /videos/hosted/{hostedVideo:slug}`

Route binding uses the `slug` column explicitly (`{hostedVideo:slug}`). Same `abort_unless($hostedVideo->is_active, 404)` guard. Returns `pages.hosted-video`.

---

#### `WeatherController` — `app/Http/Controllers/WeatherController.php`

**`__invoke(): JsonResponse`** — serves `GET /api/weather`

Reads `header_weather_label_ar`, `header_weather_lat` (default `33.8938`), `header_weather_lon` (default `35.5018`) from `SiteSetting`. Coordinates are range-validated (lat: −90 to 90, lon: −180 to 180) and reset to the Beirut defaults if out of range.

Cache key: `weather.header.v1.{md5(lat,lon)}`, TTL 15 minutes. Fetches the Open-Meteo free API (`https://api.open-meteo.com/v1/forecast`) with a 6-second timeout. No API key required. Falls back to `null` values on any network or HTTP error without propagating the exception.

Response JSON:
```json
{ "label": "لبنان", "temperature_c": 28.4, "weather_code": 1, "updated_at": "ISO8601" }
```

---

#### `BreakingApiController` — `app/Http/Controllers/BreakingApiController.php`

**`__invoke(Request $request): JsonResponse`** — serves `GET /api/breaking` (throttle: 120/min/IP)

Accepts `?limit=` (integer, clamped 1–50; defaults to `ticker_limit` setting). Cache key `api.breaking.v1.limit_{N}`, TTL **30 seconds**. Selects only `slug`, `title`, `published_at` from published breaking articles. Response JSON:
```json
{
  "ok": true,
  "generated_at": "ISO8601",
  "limit": 10,
  "items": [{ "slug": "…", "title": "…", "published_at": "ISO8601", "time": "HH:MM" }]
}
```

---

#### `RssImportWebhookController` — `app/Http/Controllers/RssImportWebhookController.php`

**`__invoke(Request $request, string $secret): JsonResponse`** — serves `GET|POST /tasks/import-rss/{secret}` (throttle: 5/min/IP, CSRF-exempt)

**Authentication**: compares the `{secret}` URI segment against `SiteSetting::getValue('rss_import_secret')` (falling back to `env('RSS_IMPORT_SECRET')`). Uses `hash_equals()` for timing-safe comparison. Returns **HTTP 404** (not 401) on mismatch — or when the expected secret is empty — to avoid revealing that the endpoint exists.

Records hit metadata (timestamp, IP, method, UA) to `SiteSettings` on every valid request.

**Operating modes** (via query params):

| Param | Behaviour |
|---|---|
| `?test=1` | Returns JSON confirming reachability; import is not executed. |
| `rss_cron_enabled = false` | Returns JSON indicating disabled; import is not executed. |
| `?force=1` | Passes `force = true` to `RssImporter::importAll()`. |
| `?require_image=1` | Passes `requireImage = true` to `RssImporter::importAll()`. |
| `?behavior=…` | Passes a behavior override string to `RssImporter::importAll()`. |

All JSON responses set `Cache-Control: no-store` (and `Pragma: no-cache`); the 404 mismatch path aborts before those headers are attached.

---

#### `AlManarUrgentImportWebhookController` — `app/Http/Controllers/AlManarUrgentImportWebhookController.php`

**`__invoke(Request $request, string $secret): JsonResponse`** — serves `GET|POST /tasks/import-almanar-urgent/{secret}` (throttle: 10/min/IP, CSRF-exempt)

Structurally identical to `RssImportWebhookController` with these differences:

- Secret read from `SiteSetting::getValue('almanar_urgent_secret')` → `env('ALMANAR_URGENT_SECRET')` (both trimmed).
- Guard flag: `almanar_urgent_cron_enabled`.
- No `force`, `require_image`, or `behavior` params — delegates directly to `AlManarUrgentImporter::importNow()` with no arguments.
- Metadata keys prefixed `last_almanar_urgent_*`.

---

#### `Admin\MembershipApplicationDocumentController` — `app/Http/Controllers/Admin/MembershipApplicationDocumentController.php`

**`__invoke(MembershipApplication $membershipApplication): Response`** — serves `GET /admin/membership-applications/{membershipApplication}/id-document` (middleware: `auth`)

Double-checks authorization beyond the `auth` middleware: aborts 403 if there is no authenticated user, then verifies `$user->canAccessPanel(Filament::getPanel('admin'))`, aborting 403 if the call to `Filament::getPanel('admin')` throws (panel not registered) or if the user lacks panel access. This allows only Filament admin users to retrieve documents.

Reads `$membershipApplication->id_document_path`. Returns 404 if the path is empty or the file does not exist on the `local` disk. Otherwise streams the file inline with `response()->file()`, which sets the `Content-Type` header server-side from the file's guessed MIME type.

---

### `SetLocaleToEnglish` Middleware — `app/Http/Middleware/SetLocaleToEnglish.php`

```php
app()->setLocale('en');
```

A single-statement middleware that forces the application locale to `en`. It is **not** applied to any public web route. It is registered exclusively in the Filament admin panel middleware stack (`app/Providers/Filament/AdminPanelProvider.php:60`), positioned between `StartSession` and `AuthenticateSession`.

Purpose: the site's primary language is Arabic (many `SiteSetting` keys end in `_ar`; `.env` sets `APP_LOCALE=ar`, while `config/app.php` defaults `locale` to `'en'`). Filament's built-in UI strings, validation messages, and form labels are rendered in the application locale. This middleware ensures the admin panel always displays in English regardless of what `APP_LOCALE` or `app.locale` is configured to.

---

## Services, Console Commands & Scheduling

### Overview

The import automation is composed of two independent ingestion pipelines — standard RSS feeds and Al-Manar urgent scraping — plus a general article scraper, a breaking-news cap enforcer, and an in-admin deployment tool. Each pipeline has two identical trigger surfaces: the Laravel scheduler (via `artisan schedule:run`) and a secret-URL HTTP webhook for shared-hosting environments that cannot run CLI cron jobs.

---

### RssImporter (`app/Services/RssImporter.php`)

`RssImporter` is the core ingestion engine. It is constructed with `ArticleScraper` and `BreakingNewsLimiter` injected.

#### `importAll()` — top-level orchestration

Reads all `FeedSource` rows where `is_active = true` (ordered by id). Before processing feeds it resolves two required category slugs from the database:

| Slug | Purpose |
|------|---------|
| `lebanon` | Default category for new articles |
| `workers` | Overrides category when worker keywords match |

If either category is missing, it throws `\RuntimeException`. It then loads all active `KeywordRule` rows grouped by `type` (`TYPE_WORKER` = `worker`, `TYPE_BREAKING` = `breaking`). All keyword matching is done on normalized (lowercased, HTML-stripped, whitespace-collapsed) combined text of `title + excerpt + content`.

Runtime settings read from `SiteSetting` at the start of each run:

| Setting key | Default | Clamp | Effect |
|-------------|---------|-------|--------|
| `rss_item_limit` | 10 | 1–50 | Max items fetched per feed per run |
| `rss_force_no_skip` | false | — | Forces `overwrite` behavior, bypasses ETag/Last-Modified |
| `rss_require_image_for_home` | false | — | Drops new home articles without a real image |
| `rss_existing_item_behavior` | `fill_missing` | — | One of four update modes (see below) |

After all feeds, it calls `BreakingNewsLimiter::keepLatest()`, flushes four cache keys (`news.breaking.ticker`, `news.breaking.live`, `news.home.hero`, `news.home.latest`), and persists `last_rss_import_at` and `last_rss_import_result` (JSON) to `SiteSetting`. Return shape: `{imported, updated, skipped, filtered_no_image, failed_feeds, duration_ms}`.

#### `importFeed()` — per-feed logic

**HTTP fetch.** User-Agent: `ShaghillaRSSImporter/1.0 (+https://shaghilla.org)`. Timeout 20 s. Retries 2× on 429 or 5xx. Sends `If-None-Match` / `If-Modified-Since` from `FeedSource.etag` / `FeedSource.last_modified` unless `forceNoSkip = true`. HTTP 304 → updates `last_fetched_at` and returns zero counts.

**XML parsing.** Uses `simplexml_load_string()` with `LIBXML_NOCDATA`. Supports both RSS 2.0 (`channel/item`) and Atom (`entry`) elements.

**Item ordering.** Items are sorted newest-first by `pubDate` / Atom `updated` timestamp, then sliced to `$itemLimit`. This ensures the most recent N items are always processed regardless of feed ordering.

**Item skip logic (`shouldSkipItem()`).**
- Skip if title is empty or is itself a URL.
- Skip never if `forceNoSkip = true`.
- `DESTINATION_BREAKING` feeds: never skip (host validation relaxed; dedup relies on `guid_hash`).
- All other feeds: skip if `canonical_url` is absent/invalid, or if the canonical host does not match the feed host (subdomain differences like `m.example.com` ↔ `example.com` are allowed).

**`parseRssItem()` — field extraction.**

| Field | Source |
|-------|--------|
| `title` | `<title>`, HTML-decoded, stripped, whitespace-collapsed |
| `canonical_url` | `<link>` first; falls back to `<guid>` if it validates as a URL |
| `canonical_url_hash` | `sha256(canonical_url)` |
| `guid_hash` | `sha256("{feed_source_id}\|{basis}")` where `basis` = `<guid>`, falling back to `canonical_url`, then `title` — scoped to the feed |
| `published_at` | `<pubDate>` first; fallback to Atom `updated` namespace |
| Content | `content:encoded` (`http://purl.org/rss/1.0/modules/content/`); falls back to `<description>` |
| `excerpt` | Plain text of description/content, `Str::limit(..., 240)` |
| `image_url` | Priority: `<enclosure url>` → `media:content` → `media:thumbnail` (Yahoo Media RSS) → first `<img>` in content HTML → first `<img>` in description |

**Deduplication.** First checks `canonical_url_hash`, then falls back to `(feed_source_id, guid_hash)`. MySQL duplicate key exceptions (SQLSTATE 23000, error 1062) are caught and handled gracefully rather than surfaced as failures.

**Enrichment.** Before creating or updating an article, if both `content` and `excerpt` are under 30 characters OR the image is absent/placeholder, `enrichFromWebPageIfNeeded()` calls `ArticleScraper::scrape(canonical_url)` to pull content and image from the live page. Disabled in `testing` environment.

**Image download (`downloadImageIfPossible()`).**
- Skips if the article already has a locally-stored upload URL.
- Downloads remote image: timeout 25 s, User-Agent `ShaghillaImageFetcher/1.0`, sends article URL as `Referer`, retry on 429/5xx.
- Rejects responses over 8 MB and any response whose first 200 bytes look like HTML.
- Extension detected from `Content-Type` header first; falls back to byte sniffing (`RIFF…WEBP`, `\x89PNG`, `\xFF\xD8\xFF`); last resort is URL path extension.
- Stored on the `public_uploads` disk at `news/{YYYY/MM}/{sha256_first24}.{ext}`. Idempotent: if the hash file already exists, returns the existing URL immediately.

**Placeholder image detection.** URLs containing `default-document-thumbnail`, `default-document-picture`, or `placeholder` are treated as placeholders and nulled out. Also computes sha256 hashes of known Lebanon24 purple-template URLs and matches against local filenames to detect locally-cached placeholders.

#### Existing-item update modes

| Constant | Value | Behavior |
|----------|-------|----------|
| `EXISTING_ITEM_BEHAVIOR_FILL_MISSING` | `fill_missing` | Fill only null/empty fields (default) |
| `EXISTING_ITEM_BEHAVIOR_OVERWRITE_IF_TITLE_MATCH` | `overwrite_if_title_match` | Overwrite title/excerpt/content if RSS title equals stored title |
| `EXISTING_ITEM_BEHAVIOR_OVERWRITE` | `overwrite` | Always overwrite changed fields |
| `EXISTING_ITEM_BEHAVIOR_SKIP` | `skip` | Skip existing articles entirely |

#### Destination / breaking / home placement

`FeedSource.destination` drives placement, overriding keyword results where set.

| `destination` | `is_breaking` | `show_on_home` | Locks set? |
|---------------|--------------|----------------|------------|
| `breaking` | always `true` | always `false` | `is_breaking_locked`, `show_on_home_locked` = true |
| `home` | always `false` | `true` | no |
| `both` (default) | keyword match | `true` | no |

For `DESTINATION_BREAKING` feeds, after each batch the importer un-flags (`is_breaking = false`) any published breaking article from the same feed whose `guid_hash` was not in the latest fetched set. This makes the ticker authoritative to the current feed content.

If a `TYPE_WORKER` keyword matches the combined text, `category_id` is set to the `workers` category regardless of the feed's `default_category_id`.

Language detection: `preg_match('/\p{Arabic}/u', $text)` → `'ar'`, else `'en'`.

After each feed, `FeedSource` is updated with `last_fetched_at`, `etag` (from `ETag` response header), and `last_modified` (from `Last-Modified` response header).

---

### ArticleScraper (`app/Services/ArticleScraper.php`)

`scrape(string $url): array{content?, excerpt?, image_url?}`

Called by `RssImporter` when an article lacks sufficient content or an image. It is never called during tests (the `RssImporter` enrichment call site is gated on `! app()->environment('testing')`).

1. HTTP GET with User-Agent `ShaghillaArticleScraper/1.0`, timeout 25 s, retry on 429/5xx.
2. **Image:** tries `og:image` meta tag, then `twitter:image`; falls back to first `<img>` in the article body. Placeholder URLs (containing `default-document-thumbnail`, `default-document-picture`, `placeholder`) are discarded.
3. **Content extraction:** loads HTML into `DOMDocument`, XPath-queries `[itemprop="articleBody"]` first, falls back to `<article>`. If neither exists, the full raw HTML is used as a fallback.
4. **Text conversion:** strips `<script>` and `<style>`, converts `<br>` → `\n`, `</p>` → `\n\n`, `</div>` → `\n`, then HTML entity decode, `strip_tags()`, whitespace collapse.
5. Returns `content` (plain text), `excerpt` (240-char limit of content), and `image_url`.

---

### AlManarUrgentImporter (`app/Services/AlManarUrgentImporter.php`)

`importNow(): array{imported, updated, skipped, cleared, failed, duration_ms}`

Scrapes Al-Manar's AJAX endpoint for "urgent" (red-flagged) items and creates/refreshes them as breaking-only articles. Constructed with `BreakingNewsLimiter` injected.

#### Gate checks

1. `SiteSetting::getBool('almanar_urgent_enabled', false)` — disabled by default; returns zero counts immediately if off.
2. `limit` from `almanar_urgent_limit` (default 10, clamped 1–25).
3. Acquires `Cache::lock('tasks.almanar_urgent_import', 120)` — concurrent invocations return immediately without importing.

#### Data fetch

`POST https://almanar.com.lb/ajaxify` with form body:
```
action=manar_get_latest_news
urgent_only=true
post_count={limit}
```
User-Agent `ShaghillaUrgentImporter/1.0 (+https://shaghilla.org)`, timeout 20 s, retry 2× on 429/5xx.

#### HTML parsing (`parseUrgentListHtml()`)

XPath query for `<li>` elements carrying both classes `urgent-news` and `text-danger`. Within each `<li>`:
- `<a class="urgent-news">` → `href` (URL) and `textContent` (title)
- `<time class="timeago">` → `datetime` attribute parsed as `Asia/Beirut` timezone

Diagnostics written to `SiteSetting` after fetch: `last_almanar_urgent_fetch_at`, `last_almanar_urgent_fetch_count`, `last_almanar_urgent_fetch_sample` (JSON, up to 5 items). These allow admins to confirm the scraper is working without running a full import.

#### Per-item processing

Dedup key: `canonical_url_hash = sha256(source_url)`. **`canonical_url` is stored as `null`** intentionally — this prevents the source Al-Manar URL from appearing in public article pages or meta tags. Only the hash is kept for dedup. `guid` is set to the full URL for internal reconciliation.

For each item, the importer fetches the article page (`fetchArticleHtml()`) with a 3-minute cache keyed on `almanar.article_html.v1.{sha256(url)}` to avoid redundant fetches across overlapping cron runs. Article body is extracted from the `.mnr-article-content` CSS class.

New articles are created with: `feed_source_id = null`, `category_id = lebanon` (falling back to the first category by id if the `lebanon` slug is missing), `language = 'ar'`, `is_breaking = true`, `show_on_home = false`, `image_url = null`. Both `is_breaking_locked` and `show_on_home_locked` are set to `true` so subsequent RSS keyword passes cannot override the placement.

Existing articles always get overwritten (title, excerpt, content, published_at, imported_at, is_breaking, status).

#### Breaking list reconciliation

After the loop, un-flags older Al-Manar items as breaking: articles where `feed_source_id IS NULL`, `status = 'published'`, `is_breaking = true`, `guid LIKE 'https://almanar.com.lb/%'`, `canonical_url_hash NOT IN {keepHashes}`, and (when the column exists) `is_breaking_locked = true`. The `cleared` count in the return value reflects how many were un-flagged.

Post-import: calls `BreakingNewsLimiter::keepLatest()`, forgets `news.breaking.ticker`, `news.home.hero`, `news.home.latest`, persists `last_almanar_urgent_import_at` and `last_almanar_urgent_import_result` to `SiteSetting`.

---

### BreakingNewsLimiter (`app/Services/BreakingNewsLimiter.php`)

`keepLatest(?int $limitOverride = null): int`

Enforces a global cap on the number of breaking articles. Called at the end of both `RssImporter::importAll()` and `AlManarUrgentImporter::importNow()`.

- Reads `ticker_limit` from `SiteSetting` (default 10, clamped 1–50).
- Selects the top-N published breaking articles ordered by `imported_at DESC`, `published_at DESC`, `id DESC`.
- Bulk-sets `is_breaking = false` on all other breaking articles.
- Forgets cache keys: `news.breaking.ticker`, `news.breaking.live`, `api.breaking.v1.limit_{limit}`.
- Returns the count of articles un-flagged.

The lock columns (`is_breaking_locked`) are **not consulted here** — `BreakingNewsLimiter` operates purely on recency regardless of per-article locks. Items locked by a `DESTINATION_BREAKING` feed may therefore be un-flagged if they fall outside the top-N limit. This is a noteworthy interaction: a `DESTINATION_BREAKING` feed that imports 10 items will have all 10 protected from `BreakingNewsLimiter` only if `ticker_limit >= 10`.

---

### DashboardUpdater (`app/Services/DashboardUpdater.php`)

`deploy(string $packagePath, string $token): array{build_id, app_dir, webroot}`

Invoked exclusively from the Filament admin page `app/Filament/Pages/Updates.php` ("Updates" in the "Automation" navigation group). The page is always listed in the admin navigation; only the deploy header action (file upload + token form) is rendered when `DASHBOARD_UPDATE_TOKEN` is set in `.env` — otherwise `getHeaderActions()` returns an empty array.

#### Purpose

Allows deploying a new version of the entire application from the admin UI — no SSH required. The package is a zip built by `scripts/build-cpanel-upload.sh` and uploaded via the Filament file upload (`maxSize(256000)` ≈ 256 MB, stored on the Laravel `local` disk in the `updates/packages/` directory — i.e. `storage/app/private/updates/packages/`, since the `local` disk root is `storage/app/private`).

#### Token validation

- Reads `DASHBOARD_UPDATE_TOKEN` directly from `.env` via `env()` — not from `SiteSetting`.
- If the env var is an empty string, `deploy()` throws immediately (updater is disabled).
- Compared using `hash_equals()` for timing-safe equality.

#### Package validation (`assertZipLooksLikeShaghillaPackage()`)

Zip must contain:
- Top-level `app/` directory
- Top-level `public_html/` directory
- `app/vendor/autoload.php` (vendor must be pre-built into the zip)
- `app/bootstrap/app.php`

#### Path-traversal protection (`safeExtract()`)

- Rejects any zip entry whose name starts with `/` (absolute path).
- Rejects any entry containing `..`.
- Only extracts entries prefixed with `app/` or `public_html/` — all other zip members are silently skipped.

#### Deployment sequence

1. Extracts zip to `storage/app/private/updates/tmp/{uuid}/` (the `local` disk root is `storage/app/private`).
2. Reads build ID from `{extracted}/app/.shaghilla-build-id` (format `YYYYMMDD_HHMMSS`); falls back to `now()->format('Ymd_His')`.
3. Locates `public_html` by walking up to 6 directory levels from `public_path()`.
4. Creates new app directory at `{homeDir}/shaghilla_app_{buildId}` (appends `_1`, `_2`, etc. if already exists), where `homeDir` is the parent of the located `public_html`.
5. Moves extracted `app/` into the new app directory.
6. Copies the currently running `.env` into the new app directory verbatim.
7. Asserts `vendor/autoload.php`, `bootstrap/app.php`, and `.env` exist in the new directory.
8. Syncs `extracted/public_html/` → webroot, skipping `uploads/` and `.app-root` (runtime files preserved).
9. Writes `{webroot}/.app-root` containing the absolute path of the new app directory.
10. Deletes the temp extraction directory; the uploaded zip is preserved.

Post-deploy, `Updates.php` writes `last_dashboard_update_at` and `last_dashboard_update_build_id` to `SiteSetting`.

#### Security implications

- The feature is opt-in: `DASHBOARD_UPDATE_TOKEN` must be explicitly set in `.env`.
- The token is checked with `hash_equals()` before any filesystem operation.
- The zip is never publicly accessible (stored on the `local` disk, whose root is `storage/app/private`, not the web-served `public` disk).
- Path-traversal guards in `safeExtract()` prevent zip-slip attacks.
- The `.env` is copied from the currently running app — no new secrets are injected from the zip.
- The `.app-root` pointer file is what makes the new app directory live; the mechanism by which the PHP bootstrap reads this file is outside `DashboardUpdater` itself (it is presumed to be a shared-hosting entry-point wrapper).
- The web server user must have write access to `homeDir` and `webroot`; this is intentional for cPanel environments but represents a significant privilege level.

---

### Artisan Commands

#### `news:import-rss` (`app/Console/Commands/ImportRssNews.php`)

```
php artisan news:import-rss
    {--limit=}           # Max items per feed (1-50), overrides SiteSetting
    {--behavior=}        # fill_missing | overwrite_if_title_match | overwrite | skip
    {--force}            # Overwrite all, bypass ETag/Last-Modified
    {--require-image}    # Filter new home articles lacking a real image
```

- Checks `rss_cron_enabled` (SiteSetting, default true); exits with warning if disabled.
- Writes audit trail to SiteSetting: `last_rss_cron_hit_at`, `last_rss_cron_hit_ip = 'cli'`, `last_rss_cron_hit_method = 'CLI'`, `last_rss_cron_hit_ua = 'artisan news:import-rss'`, `last_rss_cron_hit_mode`, `last_rss_import_source = 'scheduler'`.
- Delegates to `RssImporter::importAll()`.
- Outputs: `Imported: N | Updated: N | Skipped: N | No-image filtered: N | Failed feeds: N | Nms`
- Always exits `SUCCESS` (failures are counted and returned, not thrown).

#### `news:import-almanar-urgent` (`app/Console/Commands/ImportAlManarUrgent.php`)

```
php artisan news:import-almanar-urgent
```
No options. Signature has no parameters.

- Checks `almanar_urgent_cron_enabled` (SiteSetting, default true); exits with warning if disabled.
- Writes audit trail: `last_almanar_urgent_cron_hit_at`, `last_almanar_urgent_cron_hit_ip = 'cli'`, `last_almanar_urgent_cron_hit_method = 'CLI'`, `last_almanar_urgent_cron_hit_ua = 'artisan news:import-almanar-urgent'`, `last_almanar_urgent_cron_hit_mode`, `last_almanar_urgent_import_source = 'scheduler'`.
- Delegates to `AlManarUrgentImporter::importNow()`.
- Outputs: `Imported: N | Updated: N | Cleared: N | Skipped: N | Failed: N | Nms`

---

### Scheduling (`routes/console.php`)

```php
// Task 1 — RSS heartbeat
Schedule::call(function (): void { ... })
    ->name('news:import-rss')
    ->everyMinute()
    ->withoutOverlapping(55);

// Task 2 — Al-Manar urgent
Schedule::command('news:import-almanar-urgent')
    ->everyMinute()
    ->withoutOverlapping(2);
```

**Task 1 — RSS heartbeat.** The scheduler fires every minute, but the closure itself applies an application-level interval gate:

1. Checks `rss_cron_enabled` (SiteSetting); returns immediately if false.
2. Reads `rss_cron_interval_minutes` (SiteSetting, default 60, clamped 1–1440).
3. Reads `last_rss_import_at` (SiteSetting ISO 8601 string).
4. If `now - last_rss_import_at < interval_minutes`, skips without importing.
5. Otherwise calls `Artisan::call('news:import-rss')`.
6. All exceptions are swallowed to prevent scheduler disruption.
7. `withoutOverlapping(55)` means Laravel holds a mutex lock for up to 55 minutes, preventing stacked runs if an import takes longer than one minute.

**Task 2 — Al-Manar urgent.** Runs `news:import-almanar-urgent` unconditionally every minute (subject to its own internal `almanar_urgent_enabled` gate and the 120-second cache lock inside `AlManarUrgentImporter::importNow()`). `withoutOverlapping(2)` holds the mutex for 2 minutes.

---

### Dual Trigger Model

Each pipeline can be activated by either trigger independently:

| Trigger | RSS | Al-Manar |
|---------|-----|----------|
| **Scheduler** | `schedule:run` → closure → `Artisan::call('news:import-rss')` | `schedule:run` → `news:import-almanar-urgent` |
| **Webhook URL** | `GET\|POST /tasks/import-rss/{secret}` | `GET\|POST /tasks/import-almanar-urgent/{secret}` |

**Webhook routes** (`routes/web.php:41-47`):

| Route | Controller | Throttle |
|-------|-----------|---------|
| `GET\|POST /tasks/import-rss/{secret}` | `RssImportWebhookController` | `rss-import` (5 req/min per IP) |
| `GET\|POST /tasks/import-almanar-urgent/{secret}` | `AlManarUrgentImportWebhookController` | `almanar-urgent-import` (10 req/min per IP) |

Rate limiters are defined in `AppServiceProvider::boot()`.

Both webhook controllers:
- Compare `{secret}` path segment against `SiteSetting::getValue('rss_import_secret')` / `SiteSetting::getValue('almanar_urgent_secret')` (with env fallback `RSS_IMPORT_SECRET` / `ALMANAR_URGENT_SECRET`) using `hash_equals()`. An incorrect or empty secret returns `abort(404)` — not 403 — to prevent secret-existence discovery via HTTP status code.
- Support `?test=1` to verify URL reachability without running the import (useful for cPanel cron job validation).
- Record the same audit trail fields as the CLI commands (IP address, HTTP method, user-agent) so admins can see which trigger fired and from where.
- RSS webhook also accepts `?force=1`, `?require_image=1`, `?behavior=...` query params forwarded to `RssImporter::importAll()` (the Al-Manar webhook accepts no such params).
- Return `Cache-Control: no-store, no-cache, must-revalidate, max-age=0` (and `Pragma: no-cache`) to prevent CDN or browser caching of the JSON response.
- Return JSON shape (actual-import path): `{ok: true, ran_at, cron_enabled, result: {...}}`.

The `last_rss_import_source` / `last_almanar_urgent_import_source` SiteSetting values record whether the last run came from `'scheduler'` (CLI) or `'cron_url'` (webhook), enabling admin-panel diagnostics to distinguish which trigger fired.

---

## Filament Admin — Resources (CRUD)

The admin panel defines **12 Filament Resource classes** under `app/Filament/Resources/` — 11 user-visible resources plus one hidden legacy stub (`MembershipStatusResource`) — spread across six navigation groups. An optional Excel export button (powered by `pxlrbt/filament-excel` ^2.3) is conditionally appended to the header of each of the 11 active list pages via `App\Support\Filament\OptionalFilamentActions::exportAction()` — the button appears only when the package class `pxlrbt\FilamentExcel\Actions\Tables\ExportAction` is resolvable at runtime (`app/Support/Filament/OptionalFilamentActions.php`).

A `TelegramUserResource/` directory exists under `app/Filament/Resources/` but contains only an (empty) `Pages/` sub-folder with no parent resource `.php` file — it is an incomplete stub and is not registered in the panel.

---

### Navigation Group: News

#### ArticleResource
`app/Filament/Resources/ArticleResource.php` | model: `App\Models\Article` | icon: `heroicon-o-newspaper`

Manages the main news article content. Default table sort: `published_at DESC`.

**Form fields:**

| Field | Type | Notes |
|---|---|---|
| `display_destination` | Select (virtual) | Composite control: "Home page only", "Use on both (home + breaking)", "Breaking ticker only". Writes derived values into `show_on_home` (hidden) and `is_breaking` after state update. Visible only when the `show_on_home` column exists on the `articles` table (Schema::hasColumn guard). |
| `show_on_home` | Hidden | Derived from `display_destination`. |
| `title` | TextInput | required, max 500 chars, full width |
| `slug` | TextInput | required, unique (ignoring self), max 255 |
| `category_id` | Select | relationship `category.name_ar`, searchable, preload, required |
| `feed_source_id` | Select | relationship `feedSource.name`, searchable, preload |
| `is_breaking` | Toggle | Visible only when `show_on_home` column is absent (older schema). |
| `status` | Select | `published` / `draft`, default `published` |
| `language` | TextInput | max 5, default `ar` |
| `published_at` | DateTimePicker | required |
| `imported_at` | DateTimePicker | disabled (read-only metadata) |
| `excerpt` | Textarea | 3 rows |
| `content` | Textarea | 10 rows |
| `canonical_url` | Textarea | 2 rows |
| `image_url` | Textarea | 2 rows |

**Table columns:** `published_at` (sortable), `show_on_home` icon (toggleable, hidden by default, schema-guarded), `title` (searchable, sortable, wrapped), `category.name_ar`, `feedSource.name` (toggleable), `is_breaking` icon (sortable), `status` badge (sortable), `language` (sortable, toggleable, hidden by default).

**Filters:** TernaryFilter on `is_breaking`; TernaryFilter on `show_on_home` (schema-guarded); SelectFilter on `status`; SelectFilter on `category_id` (relationship).

**Actions:** Edit (row); Delete bulk. Export header button (conditional, pxlrbt/filament-excel).

**Pages:** List `/`, Create `/create`, Edit `/{record}/edit`.

---

#### CategoryResource
`app/Filament/Resources/CategoryResource.php` | model: `App\Models\Category` | icon: `heroicon-o-tag`

Manages article categories. Default sort: `sort_order ASC`.

**Form fields:** `slug` (required, unique), `name_en` (optional), `name_ar` (required), `sort_order` (numeric, default 0), `is_system` toggle.

**Table columns:** `sort_order` (sortable), `slug` (searchable, sortable), `name_en` (toggleable), `name_ar` (searchable, toggleable), `is_system` icon (sortable), `updated_at` (relative, toggleable hidden by default).

**Filters:** TernaryFilter on `is_system`.

**Actions:** Edit (row); Delete bulk. Export header button (conditional).

**Pages:** List, Create, Edit.

---

#### FeedSourceResource
`app/Filament/Resources/FeedSourceResource.php` | model: `App\Models\FeedSource` | icon: `heroicon-o-rss`

Manages RSS/Atom feed sources that the import worker fetches from.

**Form fields:**

| Field | Type | Notes |
|---|---|---|
| `name` | TextInput | required, max 255 |
| `url` | TextInput | required, URL validated, max 2048 |
| `destination` | Select | `home` / `both` / `breaking` (FeedSource::DESTINATION_* constants). Visible only when `destination` column exists (Schema::hasColumn guard). Default `both`. |
| `is_active` | Toggle | default true |
| `default_category_id` | Select | relationship `defaultCategory.name_ar`, searchable, preload |
| `last_fetched_at` | DateTimePicker | disabled (read-only) |
| `etag` | TextInput | disabled |
| `last_modified` | TextInput | disabled |

**Table columns:** `name` (searchable, sortable), `url` (wrapped, toggleable, searchable), `is_active` icon (sortable), `destination` (formatted label, toggleable hidden by default, schema-guarded), `defaultCategory.name_ar` (sortable), `last_fetched_at` (dateTime, sortable).

**Filters:** TernaryFilter on `is_active`.

**Actions:** Edit (row); Delete bulk. Export header button (conditional).

**Pages:** List, Create, Edit.

---

#### KeywordRuleResource
`app/Filament/Resources/KeywordRuleResource.php` | model: `App\Models\KeywordRule` | icon: `heroicon-o-magnifying-glass`

Manages keyword matching rules used by the import worker (type `worker`) and the breaking-news classifier (type `breaking`). Default sort: `type`.

**Form fields:** `type` Select (`worker` / `breaking`, constants `KeywordRule::TYPE_WORKER` / `TYPE_BREAKING`), `keyword` TextInput (required, max 255), `is_active` Toggle (default true).

**Table columns:** `type` badge (sortable), `keyword` (searchable, sortable), `is_active` icon (sortable), `updated_at` (relative, toggleable hidden by default).

**Filters:** SelectFilter on `type`; TernaryFilter on `is_active`.

**Actions:** Edit (row); Delete bulk. Export header button (conditional).

**Pages:** List, Create, Edit.

---

### Navigation Group: Media

#### VideoItemResource
`app/Filament/Resources/VideoItemResource.php` | model: `App\Models\VideoItem` | icon: `heroicon-o-video-camera`

Manages YouTube-linked video items (episodes, reports, home-page featured videos). Default sort: `sort_order`.

**Form fields:** `type` Select (`episode` / `report` / `home`, `VideoItem::TYPE_*` constants); `title` TextInput (required, max 255, full width); `youtube_url` Textarea (required, 2 rows); `thumbnail_url` Textarea (optional, 2 rows); `published_at` DateTimePicker; `sort_order` numeric (default 0); `is_active` Toggle (default true).

**Table columns:** `type` badge (sortable), `title` (searchable, sortable, wrapped), `published_at` (dateTime, sortable), `sort_order` (sortable), `is_active` icon (sortable).

**Filters:** SelectFilter on `type`; TernaryFilter on `is_active`.

**Actions:** Edit (row); Delete bulk. Export header button (conditional).

**Pages:** List, Create, Edit.

---

#### HostedVideoResource
`app/Filament/Resources/HostedVideoResource.php` | model: `App\Models\HostedVideo` | nav label: **Hosted Videos** | icon: `heroicon-o-film`

Manages self-hosted video files uploaded directly to the server. Default sort: `sort_order`.

**Form fields:**

| Field | Type | Notes |
|---|---|---|
| `title` | TextInput | required, max 255, full width |
| `slug` | TextInput | optional, auto-generated if blank, max 255, full width |
| `video_path` | FileUpload | disk `public_uploads`, directory `videos/hosted`, accepts mp4/webm/ogg/quicktime, max 500 MB (512000 KB). No filename preservation. |
| `poster_path` | FileUpload | disk `public_uploads`, directory `videos/posters`, image only, max 10 MB (10240 KB), optional. No filename preservation. |
| `published_at` | DateTimePicker | optional |
| `sort_order` | TextInput | numeric, default 0 |
| `is_active` | Toggle | default true |

**Table columns:** `title` (searchable, sortable, wrapped), `published_at` (dateTime, sortable), `sort_order` (sortable), `is_active` icon (sortable), `updated_at` (relative, toggleable hidden by default).

**Filters:** TernaryFilter on `is_active`.

**Actions:** Edit (row); Delete bulk. Export header button (conditional).

**Pages:** List, Create, Edit. No `getRelations()` defined.

---

### Navigation Group: Contact

#### ContactMessageResource
`app/Filament/Resources/ContactMessageResource.php` | model: `App\Models\ContactMessage` | icon: `heroicon-o-inbox`

Read-only inbox for contact form submissions. **Creation is disabled** (`canCreate()` returns `false`). No edit page — the record detail is shown as an Infolist (view page).

**Table columns:** `created_at` as "Received" (dateTime, sortable), `name` (searchable, sortable), `email` labelled "Phone" (searchable, toggleable), `subject` (searchable, wrapped).

Note: the `email` column is labelled "Phone" because the public contact form repurposes the `email` field to capture a phone number — `ContactController` validates it under a phone label (default `رقم الهاتف`) and writes the value into `ContactMessage.email`.

**Filters:** none.

**Row actions:** View (opens Infolist), Delete.

**Bulk actions:** Delete bulk. Export header button (conditional).

**Infolist fields:** `name`, `email` (label: "Phone"), `subject`, `message`, `created_at` (dateTime). Two-column layout.

**Pages:** List `/`, View `/{record}`. No create or edit page.

---

### Navigation Group: Membership

#### MembershipApplicationResource
`app/Filament/Resources/MembershipApplicationResource.php` | model: `App\Models\MembershipApplication` | nav label: **Applications** | icon: `heroicon-o-document-check`

Manages membership application submissions. **Creation is disabled** (`canCreate()` returns `false`). The resource only registers in navigation when the `membership_applications` table exists (`shouldRegisterNavigation()` does a `Schema::hasTable` check). If the table is missing, the list page redirects the admin to the Membership Settings page with a danger notification.

The edit form is intentionally minimal — admins only review applications, not create them from scratch.

**Edit form fields:** `status` Select (`pending` / `approved` / `rejected`), `admin_notes` Textarea (6 rows).

**Table columns:** `created_at` as "Received" (dateTime, sortable), `full_name` (searchable, sortable, wrapped), `phone` (searchable, toggleable), `status` badge (sortable), `updated_at` (relative, toggleable hidden by default).

**Filters:** SelectFilter on `status` (`pending` / `approved` / `rejected`). Default sort: `created_at DESC`.

**Row actions:**
- **ID** — custom action that opens `route('admin.membership-applications.id-document', $record)` in a new tab. Visible only when `id_document_path` is non-empty.
- View (opens Infolist)
- Edit

**Bulk actions:** Delete bulk. Export header button (conditional).

**Infolist (View page) fields** — full Arabic-labelled form of the application:
`full_name`, `mother_name`, `birth_date`, `registry_number`, `registration_place`, `phone`, `emergency_phone`, `email`, `address`, `profession`, `marital_status` (formatted: `married`→"متزوّج", `single`→"غير متزوّج"), `children_count`, `blood_type`, `volunteer_areas` (JSON array, formatted with Arabic labels including organizational/social/relief/health/media/education/logistics/administrative/field/other), `has_volunteer_experience`, `volunteer_experience_details`, `signature_name`, `status` badge, `admin_notes`, `ip_address` (toggleable hidden), `user_agent` (toggleable hidden), `created_at`.

**Pages:** List, View `/{record}`, Edit `/{record}/edit`. No create page.

---

#### MembershipStatusResource _(hidden legacy stub)_
`app/Filament/Resources/MembershipStatusResource.php` | model: `App\Models\MembershipApplication` | nav label: **Membership Statuses (Legacy)** | icon: `heroicon-o-archive-box-x-mark` | sort: 99

`shouldRegisterNavigation()` unconditionally returns `false` — this resource is never visible in the admin panel. Its form and table both delegate to `MembershipApplicationResource::form()` and `MembershipApplicationResource::table()`. It is a historical artifact kept for its Pages classes (List/Create/Edit) but is effectively dead.

---

### Navigation Group: Content

#### SitePageResource
`app/Filament/Resources/SitePageResource.php` | model: `App\Models\SitePage` | nav label: **Menu & Pages** | icon: `heroicon-o-document-text`

Manages the site's navigation menu entries and custom static pages. Supports three page types: `route` (pointer to a built-in route), `page` (custom HTML content), `external` (off-site link). Default sort: `sort_order`.

**Form fields (type-conditional):**

| Field | Visible when | Notes |
|---|---|---|
| `type` | always | Select: `route` / `page` / `external`. Disabled on system records. |
| `route_name` | `type = route` | Select: `home` / `live` / `membership` / `contact` / `search`. Disabled on system records. Unique. |
| `slug` | `type = page` | max 255, unique, URL will be `/pages/{slug}` |
| `external_url` | `type = external` | URL validated, max 2048 |
| `title_ar` | always | required, max 255 |
| `title_en` | always | optional, max 255 |
| `show_in_header` | always | Toggle (default false) |
| `show_in_footer` | always | Toggle (default false) |
| `open_in_new_tab` | always | Toggle (default false) |
| `is_active` | always | Toggle (default true) |
| `sort_order` | always | numeric, default 0 |
| `content_html_ar` | `type = page` | RichEditor (custom toolbar; file attachments to `public_uploads/pages`) |
| `content_html_en` | `type = page` | RichEditor (same config) |

When `type = route`, a "Built-in page content" section appears with action buttons linking to the relevant admin settings page: HomepageBuilder (home), AppearanceSettings (live), MembershipSettings (membership), ServiceRequestSettings (contact).

**Table columns:** `title_ar` (searchable, sortable), `type` badge (sortable), `route_name` (toggleable), `slug` (toggleable), `url` (computed via `$record->url()`, copyable, toggleable hidden by default), `show_in_header` icon, `show_in_footer` icon, `is_active` icon, `sort_order` (sortable).

**Filters:** none.

**Row actions:**
- **View** — opens `$record->url()` in a new tab
- **Edit content** — context-aware (via `getContentEditorUrl()`): routes to the matching admin settings page (HomepageBuilder, AppearanceSettings, etc.) for `type=route` records; routes to the standard Edit page for `type=page` records; hidden when no editor URL resolves
- **Edit** (standard Filament edit)

**Bulk actions:** Delete bulk. Export header button (conditional, via `ListSitePages::getHeaderActions()`, alongside the standard Create action).

**Pages:** List, Create, Edit.

---

### Navigation Group: Settings

#### SiteSettingResource
`app/Filament/Resources/SiteSettingResource.php` | model: `App\Models\SiteSetting` | icon: `heroicon-o-cog-6-tooth`

Raw key/value store for site configuration. No filters.

**Form fields:** `key` TextInput (required, unique, max 255), `value` Textarea (3 rows).

**Table columns:** `key` (searchable, sortable), `value` (limited to 80 chars, toggleable), `updated_at` (relative, sortable).

**Actions:** Edit (row); Delete bulk. Export header button (conditional).

**Pages:** List, Create, Edit.

---

#### SharePlatformResource
`app/Filament/Resources/SharePlatformResource.php` | model: `App\Models\SharePlatform` | icon: `heroicon-o-share` | sort: 30

Manages the social-sharing buttons shown on article pages. The table is **reorderable** via drag-and-drop on `sort_order`. Default sort: `sort_order`.

**Form fields:**

| Field | Type | Notes |
|---|---|---|
| `name` | TextInput | required, max 100. Auto-populates `slug` on blur if slug is blank. |
| `slug` | TextInput | required, unique, max 100 |
| `icon` | Select | Font Awesome class name. Options from `SharePlatform::iconOptions()`: WhatsApp, X (Twitter), Facebook, Facebook (f), Instagram, LinkedIn, YouTube, TikTok, Snapchat, Reddit, Pinterest, Skype, Discord (13 options). Searchable. |
| `icon_preview` | Placeholder | Live preview rendered via `filament.forms.share-platform-icon-preview` view |
| `sort_order` | TextInput | numeric, default 0 |
| `is_active` | Toggle | default true |
| `use_native_share` | Toggle | Activates Web Share API instead of a URL template (for platforms like Instagram) |
| `share_url_template` | Textarea | 3 rows. Supports `{url}` and `{title}` placeholders. Leave empty for native share / copy only. |

**Table columns:** `name` (searchable, sortable), `slug` (toggleable hidden), `icon` (toggleable hidden), `is_active` icon (sortable), `use_native_share` icon (toggleable), `sort_order` (sortable), `updated_at` (relative, toggleable hidden by default).

**Table is reorderable** via `->reorderable('sort_order')`.

**Filters:** none.

**Actions:** Edit (row); Delete bulk. Export header button (conditional).

**Pages:** List, Create, Edit.

---

### Summary Table

| Resource | Model | Nav Group | Nav Label | Icon | Create | Edit | View | Delete | Export |
|---|---|---|---|---|---|---|---|---|---|
| ArticleResource | Article | News | Articles | newspaper | Yes | Yes | — | bulk | conditional |
| CategoryResource | Category | News | Categories | tag | Yes | Yes | — | bulk | conditional |
| FeedSourceResource | FeedSource | News | Feed Sources | rss | Yes | Yes | — | bulk | conditional |
| KeywordRuleResource | KeywordRule | News | Keyword Rules | magnifying-glass | Yes | Yes | — | bulk | conditional |
| VideoItemResource | VideoItem | Media | Video Items | video-camera | Yes | Yes | — | bulk | conditional |
| HostedVideoResource | HostedVideo | Media | Hosted Videos | film | Yes | Yes | — | bulk | conditional |
| ContactMessageResource | ContactMessage | Contact | Contact Messages | inbox | **No** | — | Yes (infolist) | row + bulk | conditional |
| MembershipApplicationResource | MembershipApplication | Membership | Applications | document-check | **No** | Yes | Yes (infolist) | bulk | conditional |
| MembershipStatusResource | MembershipApplication | Membership | Membership Statuses (Legacy) — hidden | archive-box-x-mark | — | — | — | — | — |
| SitePageResource | SitePage | Content | Menu & Pages | document-text | Yes | Yes | — | bulk | conditional |
| SiteSettingResource | SiteSetting | Settings | Site Settings | cog-6-tooth | Yes | Yes | — | bulk | conditional |
| SharePlatformResource | SharePlatform | Settings | Share Platforms | share | Yes | Yes | — | bulk | conditional |

> "conditional" export means the `Export` header button renders only when `pxlrbt\FilamentExcel\Actions\Tables\ExportAction` class exists at runtime (`composer.json` declares `pxlrbt/filament-excel: ^2.3`).

---

## Filament Admin — Custom Pages, Widgets & Panel

### Panel Configuration (`app/Providers/Filament/AdminPanelProvider.php`)

The single Filament panel is registered with id `admin` and path `/admin`. It uses the built-in login page (`->login()`), Amber as the primary colour palette, and auto-discovery for all three extension points:

| Extension point | Discovery root | Namespace prefix |
|---|---|---|
| Resources | `app/Filament/Resources` | `App\Filament\Resources` |
| Pages | `app/Filament/Pages` | `App\Filament\Pages` |
| Widgets | `app/Filament/Widgets` | `App\Filament\Widgets` |

In addition to auto-discovered widgets, four widgets are registered explicitly: `NewsroomStatsOverview`, `NewsroomQuickActions`, `Widgets\AccountWidget`, `Widgets\FilamentInfoWidget`. The built-in `Filament\Pages\Dashboard` page is also explicitly registered (`->pages([Pages\Dashboard::class])`).

**Optional plugin:** `Saade\FilamentLaravelLog\FilamentLaravelLogPlugin` is loaded when the class exists (package not bundled; the check at `AdminPanelProvider.php:29-33` avoids a hard dependency).

**Custom middleware:** `App\Http\Middleware\SetLocaleToEnglish` is injected into the panel middleware stack (position 4, after `StartSession`, before `AuthenticateSession`). It forces `app()->setLocale('en')` so Filament's UI renders in English regardless of the user's browser locale — important because the public site defaults to Arabic.

The full middleware stack (in order): `EncryptCookies`, `AddQueuedCookiesToResponse`, `StartSession`, `SetLocaleToEnglish`, `AuthenticateSession`, `ShareErrorsFromSession`, `VerifyCsrfToken`, `SubstituteBindings`, `DisableBladeIconComponents`, `DispatchServingFilamentEvent`. Auth middleware: `Filament\Http\Middleware\Authenticate`.

---

### Auto-Discovered Resources (12)

All resources live under `app/Filament/Resources` and are registered automatically.

| Resource class | Model | Notes |
|---|---|---|
| `ArticleResource` | `Article` | Core CMS entity |
| `CategoryResource` | `Category` | Article taxonomy |
| `ContactMessageResource` | `ContactMessage` | Service-request inbox |
| `FeedSourceResource` | `FeedSource` | RSS feed configuration |
| `HostedVideoResource` | `HostedVideo` | Self-hosted video uploads |
| `KeywordRuleResource` | `KeywordRule` | RSS keyword filter rules |
| `MembershipApplicationResource` | `MembershipApplication` | Union membership forms |
| `MembershipStatusResource` | `MembershipApplication` | Hidden legacy resource (label "Membership Statuses (Legacy)", `shouldRegisterNavigation()` → false); reuses `MembershipApplicationResource`'s form/table. There is no `MembershipStatus` model or table. |
| `SharePlatformResource` | `SharePlatform` | Social share buttons |
| `SitePageResource` | `SitePage` | Header/footer menu pages |
| `SiteSettingResource` | `SiteSetting` | Raw key-value table access |
| `VideoItemResource` | `VideoItem` | YouTube/playlist items |

---

### Custom Pages (9)

All nine pages extend `Filament\Pages\Page` and persist via `SiteSetting::setValues()` (writing to the `site_settings` table). None wrap a model resource form — they are bespoke single-page config and operations tools.

Navigation is organised into three groups:

| Group | Pages |
|---|---|
| **Pages** | HomepageBuilder, HeaderBuilder, MembershipSettings, ServiceRequestSettings |
| **Settings** | AppearanceSettings |
| **Automation** | RssAutomation, BreakingScraper, MaintenanceTools, Updates |

---

#### `AppearanceSettings` (`app/Filament/Pages/AppearanceSettings.php`)

Navigation label: *Appearance* / icon: `heroicon-o-paint-brush`.

Manages ~22 `SiteSetting` keys across five UI sections:

| Section | Notable settings written |
|---|---|
| Brand | `site_brand_name` |
| Live page (YouTube) | `live_youtube_url`, `live_youtube_playlist_limit` (1–12) |
| Breaking ticker | `breaking_ticker_enabled`, `breaking_ticker_engine` (`js`/`legacy`), `breaking_ticker_direction`, `breaking_ticker_speed_px_per_sec`, `breaking_ticker_gap_px`, `breaking_ticker_speed_seconds`, `breaking_ticker_start_offset_seconds`, `breaking_ticker_pause_on_hover`, `breaking_ticker_divider_logo_enabled`, `breaking_ticker_divider_logo_url`, `breaking_ticker_poll_enabled`, `breaking_ticker_poll_interval_seconds` |
| Header | `header_show_live_button`, `header_logo_enabled`, `header_logo_url` |
| Footer | `footer_show_socials`, `footer_facebook_url`, `footer_x_url`, `footer_instagram_url` |

The JS ticker engine fields are conditionally visible (`->visible(fn ($get) => ...)`) to show the correct speed controls per engine choice. The header action `editMenus` links directly to `SitePageResource::getUrl('index')`.

---

#### `HeaderBuilder` (`app/Filament/Pages/HeaderBuilder.php`)

Navigation label: *Header* / icon: `heroicon-o-bars-3`.

A drag-and-drop layout composer for the public site header. Uses Filament's `Builder` form component to arrange typed blocks across a two-row, three-zone grid:

**Blocks available:** `logo`, `menu`, `hamburger`, `live`, `weather`, `search`, `language_chips`, `spacer`.

**Layout:** an optional *top row* (utility bar, toggled by `row1_enabled`) plus a *main navigation row*, each divided into `right` / `center` / `left` zones. Default state places `[logo, menu]` in `row2_right` and `[live, hamburger]` in `row2_left`.

On save, the block configuration is serialised as `header_layout_json` (versioned JSON, `version: 1`) alongside individual toggle/text settings (`header_logo_enabled`, `header_search_enabled`, `header_weather_enabled`, `header_weather_lat`, `header_weather_lon`, `header_weather_label_ar`, etc.). The `decodeLayout()` method (`HeaderBuilder.php:218`) is defensive — any decode failure returns an empty array, so `mount()` falls back to the default block layout via `array_merge($defaults, $decoded, …)`.

---

#### `HomepageBuilder` (`app/Filament/Pages/HomepageBuilder.php`)

Navigation label: *Home page* / icon: `heroicon-o-home`.

Controls four distinct visual sections on the public home page. The News section is configured via a layout selector (no on/off toggle); the Membership hero, Contact hero, and Hosted videos sections each have an enable toggle (`home_membership_enabled`, `home_contact_enabled`, `home_hosted_videos_enabled`):

| Section | Key settings |
|---|---|
| **News layout** | `home_news_layout` (`mosaic`/`classic`/`grid`), `home_news_top_small_count` (mosaic cards, 0–12), `home_news_latest_limit` (6–40) |
| **Membership hero** | `home_membership_enabled`, `home_membership_style` (`light`/`dark`/`red`), `home_membership_title_ar`, `home_membership_body_ar`, `home_membership_button_label_ar`, `home_membership_button_mode` (`membership`→`/membership` or `custom`→URL), `home_membership_image_url` |
| **Contact hero** | `home_contact_enabled`, `home_contact_style`, `home_contact_title_ar`, `home_contact_body_ar`, `home_contact_button_mode` (`contact` or `custom`), `home_contact_image_url` |
| **Hosted videos** | `home_hosted_videos_enabled`, `home_hosted_videos_title_ar`, `home_hosted_videos_limit` (1–12), `home_hosted_videos_layout` (`grid`/`carousel`), `home_hosted_videos_include_youtube`, `home_hosted_videos_placeholders_enabled`, placeholder title/url/count |

Header actions include quick links to `HostedVideoResource` and `VideoItemResource`.

---

#### `MembershipSettings` (`app/Filament/Pages/MembershipSettings.php`)

Navigation label: *Membership* / icon: `heroicon-o-identification`.

Edits the Arabic-language copy for the public `/membership` page:

| Setting key | Field type |
|---|---|
| `membership_page_title_ar` | TextInput (max 160) |
| `membership_page_intro_ar` | Textarea (5 rows) |
| `membership_page_letter_html_ar` | RichEditor (stored as HTML; file attachments to `public_uploads` disk, `pages/` directory) |
| `membership_form_success_ar` | Textarea |
| `membership_form_submit_label_ar` | TextInput |
| `membership_form_id_label_ar` | TextInput |

The *View membership forms* action links to `MembershipApplicationResource` and is only shown when `Schema::hasTable('membership_applications')` returns true (`MembershipSettings.php:120-126`).

---

#### `ServiceRequestSettings` (`app/Filament/Pages/ServiceRequestSettings.php`)

Navigation label: *Service Requests* / icon: `heroicon-o-inbox`.

Edits the Arabic-language copy for the public `/contact` service-request page:

| Setting key | Controls |
|---|---|
| `contact_page_title_ar` | Page heading |
| `contact_page_intro_ar` | Intro paragraph |
| `contact_page_success_ar` | Post-submit success message |
| `contact_form_name_label_ar` | Name field label |
| `contact_form_email_label_ar` | Phone field label (key named "email" but its label/default is the phone number) |
| `contact_form_subject_label_ar` | Subject label |
| `contact_form_message_label_ar` | Message label |
| `contact_form_submit_label_ar` | Submit button label |

Header action *View messages* links to `ContactMessageResource::getUrl('index')`.

---

#### `RssAutomation` (`app/Filament/Pages/RssAutomation.php`)

Navigation label: *RSS Import* / icon: `heroicon-o-arrow-path`.

Comprehensive operations panel for the RSS import pipeline. Reads/writes `SiteSetting` keys and calls `App\Services\RssImporter`.

**Cron status dashboard** (read-only placeholders): displays the full cron URL (`{APP_URL}/tasks/import-rss/{rss_import_secret}`), three ready-to-paste cPanel commands, last cron hit (timestamp, mode, IP), health check (OK / STALE based on 2× interval threshold), next expected run countdown, and last import result summary (`imported`, `updated`, `skipped`, `filtered_no_image`, `failed_feeds`, `duration_ms`).

**Feed health table:** queries `FeedSource::query()->orderBy('id')->get(['name','url','is_active','last_fetched_at'])` and renders each as `ON|OFF | name | last_fetched_at=…`.

**Configurable settings:**

| Setting key | Range / options |
|---|---|
| `rss_item_limit` | 1–50 items per feed per run |
| `rss_existing_item_behavior` | `fill_missing` / `overwrite_if_title_match` / `overwrite` / `skip` |
| `rss_force_no_skip` | Bool — bypasses ETag/dedup checks |
| `rss_require_image_for_home` | Bool — filters no-image items from home display |
| `rss_cron_enabled` | Bool — cron URL guard |
| `rss_cron_interval_minutes` | 1–1440 (monitoring only) |

**Header actions:** Save settings, Test cron URL (opens `?test=1`), *Run import now* (calls `RssImporter::importAll()` with current form state), *Feed sources* (→ `FeedSourceResource`), *Keyword rules* (→ `KeywordRuleResource`).

---

#### `BreakingScraper` (`app/Filament/Pages/BreakingScraper.php`)

Navigation label: *Breaking Scraper* / icon: `heroicon-o-bolt`.

Scraping UI for `App\Services\AlManarUrgentImporter`, which fetches `https://almanar.com.lb/24-hour-news/` and imports only the red "الأخبار العاجلة" (urgent) items into the breaking ticker.

The secret for the cron URL (`{APP_URL}/tasks/import-almanar-urgent/{secret}`) is resolved from `SiteSetting` → fallback to `ALMANAR_URGENT_SECRET` env var → auto-generated (`bin2hex(random_bytes(16))`) and persisted on first access (`BreakingScraper.php:99-117`).

**Status placeholders shown:** cron URL, three cPanel command alternatives, last cron hit (timestamp/mode/IP), cron health (OK / STALE / DISABLED), next expected run, last import result (`imported`, `updated`, `cleared`, `skipped`, `failed`, `duration_ms`), and last fetch diagnostics (`last_almanar_urgent_fetch_at`, `fetch_count`, `fetch_sample`).

**Configurable settings:**

| Setting key | Notes |
|---|---|
| `almanar_urgent_enabled` | Master toggle for importer |
| `almanar_urgent_limit` | 1–25 items per run (clamped in `saveSettings`) |
| `almanar_urgent_secret` | Cron URL secret |
| `almanar_urgent_cron_enabled` | Allows hits but skips import if false |
| `almanar_urgent_cron_interval_minutes` | 1–1440 (monitoring) |

**Header actions:** Save, *Test cron URL* (appends `?test=1`), *Run now* (calls `AlManarUrgentImporter::importNow()` synchronously).

---

#### `MaintenanceTools` (`app/Filament/Pages/MaintenanceTools.php`)

Navigation label: *Maintenance* / icon: `heroicon-o-wrench-screwdriver`.

Server operations panel with six header actions, all requiring explicit confirmation (the first four via `->requiresConfirmation()`; the last two via a typed `CLEAR`/`DELETE` confirmation field):

| Action | What it does |
|---|---|
| **Run migrations** | Calls `Artisan::call('migrate', ['--force' => true])` after auto-baselining legacy migrations (see below). Stores result in `SiteSetting` under `last_migrate_at` / `last_migrate_output`. |
| **Seed share platforms** | Migrates `share_platforms` table if missing, then calls `SharePlatformSeeder` via `db:seed`. Safe to run multiple times. |
| **Clear caches** | Calls `Artisan::call('optimize:clear')`. Updates `last_cache_clear_at`. |
| **Clear scheduler locks** | Calls `Artisan::call('schedule:clear-cache')` to unblock stuck mutex-locked jobs. Updates `last_schedule_clear_at`. |
| **Clear placeholder images** | Modal form: `days` (default 30) + `only_rss` toggle + `CLEAR` confirmation. NULLs `image_url` on articles matching known Lebanon24 template URL substrings (`Default-Document-Thumbnail`, `Default-Document-Picture`, both cases) and their SHA-256 hash prefixes. Also flushes four named cache keys. |
| **Prune old RSS articles** | Modal form: `days` + `keep_breaking` toggle + `DELETE` confirmation. Hard-deletes `Article` rows where `feed_source_id IS NOT NULL` and the article is older than the cutoff (`published_at`, or `imported_at` when `published_at` is null). Flushes same four cache keys. |

**Shared-hosting migration baselining** (`MaintenanceTools.php:324-398`): before running `migrate`, `baselineLegacyMigrations()` scans every migration file. Any migration not yet in the `migrations` table is inspected: if it contains `telegram` (anywhere in name or body), it is inserted as already-run (Telegram was removed). If it calls `Schema::create()` for tables that already exist on the server (SQL-imported schemas), it is also marked as run. This prevents "table already exists" errors on shared hosts where the DB schema was set up manually.

---

#### `Updates` (`app/Filament/Pages/Updates.php`)

Navigation label: *Updates* / icon: `heroicon-o-arrow-up-tray`.

Dashboard-based deployment tool. The *Deploy update zip* action is only rendered when `DASHBOARD_UPDATE_TOKEN` is non-empty (`Updates.php:33`). When enabled, it accepts a zip file (up to 256 MB, MIME types `application/zip` / `application/x-zip-compressed` / `application/octet-stream`) uploaded to local disk directory `updates/packages`.

The modal requires the token to be re-entered (password field) as a second factor, then calls `App\Services\DashboardUpdater::deploy($packagePath, $token)`. The expected zip format is produced by `scripts/build-cpanel-upload.sh` and must contain `app/` and `public_html/` directory trees. On success, `last_dashboard_update_at` and `last_dashboard_update_build_id` are written to `SiteSetting`.

The page reads (in `mount()`) the current build ID (`DashboardUpdater::readBuildId(base_path())`), the app-root override (`DashboardUpdater::readAppRootOverride(public_path())`), and the last update timestamp, regardless of whether the deploy action is enabled.

---

### Widgets

#### `NewsroomStatsOverview` (`app/Filament/Widgets/NewsroomStatsOverview.php`)

Extends `Filament\Widgets\StatsOverviewWidget`. Renders five stat cards on the dashboard:

| Card label | Query |
|---|---|
| Published Today | `articles` where `status='published'` AND `published_at >= today midnight` |
| Breaking (24h) | `articles` where `is_breaking=true` AND `published_at >= now()-24h` |
| Active Feeds | `feed_sources` where `is_active=true` |
| Pending Memberships | `membership_applications` where `status = MembershipApplication::STATUS_PENDING` |
| Service Requests (7d) | `contact_messages` where `created_at >= now()-7d` |

Every query is guarded by a `tableExists()` helper wrapping `Schema::hasTable()` (returns 0 on missing table) to survive fresh installs or missing migrations gracefully (`NewsroomStatsOverview.php:71-78`).

#### `NewsroomQuickActions` (`app/Filament/Widgets/NewsroomQuickActions.php`)

Extends `Filament\Widgets\Widget`. Renders at full column span (`$columnSpan = 'full'`), sort order 2. Delegates rendering to Blade view `filament.widgets.newsroom-quick-actions`. Exposes `getActions()` returning seven labelled action cards with icon, colour, and URL:

| Label | Destination |
|---|---|
| Create Article | `ArticleResource::getUrl('create')` |
| Create Video Item | `VideoItemResource::getUrl('create')` |
| Manage Feed Sources | `FeedSourceResource::getUrl('index')` |
| Review Memberships | `MembershipApplicationResource::getUrl('index')` |
| Open Service Requests | `ContactMessageResource::getUrl('index')` |
| RSS Automation | `RssAutomation::getUrl()` |
| Maintenance Tools | `MaintenanceTools::getUrl()` |

---

### `app/Support/Filament/OptionalFilamentActions.php`

A single static helper class with one method: `exportAction()`. It checks for the presence of `pxlrbt\FilamentExcel\Actions\Tables\ExportAction` at runtime and returns an instantiated export action (labelled "Export") if the package is installed, or `null` if not. Resources that offer an export button call this method and filter out the null, making the Excel export feature entirely optional with no hard Composer dependency.

---

### `app/Providers/AppServiceProvider.php` — Rate Limiters

`AppServiceProvider::boot()` registers five named rate limiters, all keyed by IP address:

| Limiter name | Limit |
|---|---|
| `contact` | 10 / minute |
| `rss-import` | 5 / minute |
| `almanar-urgent-import` | 10 / minute |
| `api-breaking` | 120 / minute |
| `membership` | 5 / minute |

No service container bindings (`register()` is empty).

---

## Frontend: Views, Layouts, Assets, i18n

### Blade Layout System

The entire public site shares one root layout: `resources/views/components/layouts/site.blade.php`. Every public page wraps its content with `<x-layouts.site :title="...">`.

**`<html>` tag** (`site.blade.php:2`): `dir="rtl"` is hardcoded; `lang` is emitted dynamically from `app()->getLocale()`. RTL is unconditional — there is no postcss-rtl or tailwindcss-rtl plugin (`tailwind.config.js:57`: `plugins: []`). RTL is handled entirely via static `dir` attributes on elements.

**`<body>` base classes** (`site.blade.php:31`): `min-h-screen overflow-x-hidden bg-canvas font-sans text-ink antialiased`. `font-sans` resolves to Tajawal (set in `tailwind.config.js:15`).

**Content width**: `<main class="mx-auto w-full max-w-6xl px-4 pb-8 pt-5">` (`site.blade.php:38`).

**Render order inside `<body>`**:
1. `<x-site.header />` — configurable multi-row header
2. `<x-news.breaking-ticker />` — animated breaking-news bar
3. `<main>{{ $slot }}</main>` — page content
4. `<x-site.footer />` — footer

**Third-party assets** (`site.blade.php:25-44`):
- `vendor/bladewind/css/animate.min.css` and `bladewind-ui.min.css` (linked in `<head>`)
- `@vite(['resources/css/app.css', 'resources/js/app.js'])` (Vite manifest)
- `vendor/bladewind/js/helpers.js` (end of `<body>`)

A deploy-stamp file `.shaghilla-build-id` is read at render time and emitted as `<meta name="x-shaghilla-build">` (`site.blade.php:21-23`) plus an HTML comment (`site.blade.php:32-34`).

`@stack('head')` is available; used by `article.blade.php`, `video.blade.php`, and `hosted-video.blade.php` for canonical links. OG/Twitter card meta is pushed only by `article.blade.php`; `video.blade.php` and `hosted-video.blade.php` push only a canonical link.

---

### Header (`resources/views/components/site/header.blade.php`)

The header is JSON-layout-driven. On every request it reads the `header_layout_json` SiteSetting, decodes it, and renders blocks into two rows:

| Row | Purpose | Default blocks (when no JSON) |
|-----|---------|-------------------------------|
| row1 | Optional top bar (enabled by `row1_enabled` key) | empty |
| row2 | Main nav row (always rendered) | right: logo + menu; left: live button + hamburger |

Each row has three zones: `right`, `center`, `left`. Each zone holds an ordered array of **block objects** with a `type` string. The `$renderBlock` closure (`header.blade.php:86-189`) switches on `type`:

| Block type | Rendered element | Conditional guard |
|------------|-----------------|-------------------|
| `logo` | `<a>` with logo image + brand name | `header_logo_enabled` (hides only the `<img>`; the `<a>` + brand text always render) |
| `menu` | `<nav>` with desktop links from `SitePage::headerMenu()` | — |
| `live` | Red accent `<a href="route('live')">` button | `header_show_live_button` SiteSetting |
| `hamburger` | `<button @click="open = !open">` (mobile only, `md:hidden`) | always injected if absent |
| `search` | Inline `<form>` pointing to `route('search')` | `header_search_enabled` SiteSetting |
| `language_chips` | EN / ES chip links (hardcoded, placeholder) | `header_language_chips_enabled` SiteSetting |
| `weather` | Renders `components.site.weather-widget` inline | `header_weather_enabled` SiteSetting |
| `spacer` | Fixed-width `<span>` | — |

If `header_layout_json` is absent or invalid JSON, the header falls back to the hardcoded default (`header.blade.php:42-57`).

Alpine.js drives the mobile menu: `x-data="{ open: false }"` on `<header>`, `x-show="open"` on `#mobile-menu` (`header.blade.php:1, 239`). The hamburger is guaranteed present: if the JSON layout has no `hamburger` block, one is appended to `$row2Left` (`header.blade.php:72-75`). Likewise, `weather` is prepended to `$row2Left` if `header_weather_enabled` and absent from the layout (`header.blade.php:80-82`).

---

### Footer (`resources/views/components/site/footer.blade.php`)

A two- or three-column grid. Columns:
1. Brand name + tagline (hardcoded Arabic: "منصة أخبار مباشرة وسريعة بواجهة عربية واضحة.")
2. Footer links from `SitePage::footerMenu()`, or hardcoded fallback nav
3. Social icons (conditionally rendered when `footer_show_socials` is true and at least one URL is set)

Social platforms read from SiteSettings: `footer_facebook_url`, `footer_x_url`, `footer_instagram_url`. Icons are rendered as inline text (f / X / IG), not SVG, in the footer. Copyright year is `now()->year` (`footer.blade.php:95`).

---

### Breaking Ticker (`resources/views/components/news/breaking-ticker.blade.php`)

The ticker is rendered immediately after the header in the root layout. It is controlled entirely by SiteSettings — the component reads ~11 settings at render time:

| SiteSetting key | Default | Purpose |
|-----------------|---------|---------|
| `breaking_ticker_enabled` | `true` | On/off switch |
| `breaking_ticker_engine` | `'js'` | `js` (requestAnimationFrame) or `legacy` |
| `breaking_ticker_direction` | `'left'` | Scroll direction (`left` or `right`) |
| `breaking_ticker_speed_px_per_sec` | `90` | Speed (clamped 20–600) |
| `breaking_ticker_gap_px` | `20` | Gap between items (clamped 0–80) |
| `breaking_ticker_pause_on_hover` | `true` | Pause animation on hover |
| `breaking_ticker_poll_enabled` | `false` | Live polling toggle |
| `breaking_ticker_poll_interval_seconds` | `45` | Poll interval (clamped 15–300) |
| `breaking_ticker_divider_logo_enabled` | `true` | Show logo divider between items |
| `breaking_ticker_divider_logo_url` | `asset('logo-fav.png')` | Divider image |
| `ticker_limit` | `10` | Max articles (clamped 1–50) |

Articles are queried: `Article::where('status', 'published')->where('is_breaking', true)->orderByDesc('imported_at')->orderByDesc('published_at')->orderByDesc('id')->limit($tickerLimit)` and cached for 5 minutes under `news.breaking.ticker` (`breaking-ticker.blade.php:36-49`).

The Blade template embeds initial items as a JSON blob (`<script type="application/json" data-breaking-ticker-items>`) and renders all configuration as `data-*` attributes on a `[data-breaking-ticker]` container. A `<noscript>` fallback renders plain links (`breaking-ticker.blade.php:100-108`).

**`resources/js/breaking-ticker.js`** (258 lines) implements the animation:
- `mountBreakingTicker(container)` reads all `data-*` attributes, then builds DOM nodes for each item using `buildItemNode()` and `buildDividerNode()`.
- Uses a stable marquee strategy: measures `baseWidth` of one sequence, duplicates it up to 10 times to fill the viewport, then animates a single `translate3d` offset via `requestAnimationFrame`, wrapping at `baseWidth` (`breaking-ticker.js:121-145`).
- When `poll_enabled`, immediately calls `refresh()` then sets a `setInterval` (`breaking-ticker.js:241-245`). `refresh()` fetches `route('api.breaking')` and only re-renders if the slug list changed (`breaking-ticker.js:201-218`).
- Exposes a debug API on `container.__shTicker` (`breaking-ticker.js:248`).
- Auto-initialises on `DOMContentLoaded` by scanning `[data-breaking-ticker]` (`breaking-ticker.js:252-258`).

---

### Asset Pipeline

**Vite config** (`vite.config.js`): single entry pair — `resources/css/app.css` and `resources/js/app.js`. `refresh: true` triggers a full page live-reload when Blade/view files change.

**`resources/js/app.js`** (8 lines):
```
import './bootstrap';      // axios + CSRF header
import './breaking-ticker'; // custom marquee ticker
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();
```

**`resources/js/bootstrap.js`**: Sets up axios globally with `X-Requested-With: XMLHttpRequest` and `X-CSRF-TOKEN` from the meta tag.

**Vue 3 and v-breaking-news-ticker**: Both are runtime dependencies in `package.json` (`vue ^3.5.27`, `v-breaking-news-ticker ^2.0.0`) but are **not imported anywhere** in `app.js` or any Blade template. They are installed but unused in the current public frontend.

**Alpine.js** (`alpinejs ^3.15.3`) is the only active JS framework. It is used on:
- Header mobile menu (`x-data="{ open: false }"`)
- Home page latest-news section (AJAX pagination component, ~50 lines of inline Alpine, `home.blade.php:47-99`)
- Live page (video selection that updates the iframe `src` and calls `history.replaceState`, `live.blade.php:8-22`)
- Membership form (file preview, volunteer area visibility, `x-model`, `x-show`)
- Share menu (native share, clipboard copy, platform dropdowns)
- Weather widget (fetch + localStorage cache)

**PostCSS config** (`postcss.config.js`): `tailwindcss` + `autoprefixer` only.

---

### Tailwind Configuration (`tailwind.config.js`)

All design tokens are CSS custom properties in `resources/css/app.css:8-25` (`:root` block) and referenced via Tailwind's color opacity shorthand:

**Custom color palette**:

| Token | CSS variable | Default value (RGB) | Semantic use |
|-------|-------------|---------------------|--------------|
| `canvas` | `--sh-bg` | `248 250 252` | Page background |
| `surface` | `--sh-surface` | `255 255 255` | Cards, header, footer |
| `surface-soft` | `--sh-surface-soft` | `241 245 249` | Secondary backgrounds |
| `ink` | `--sh-ink` | `15 23 42` | Primary text |
| `ink-muted` | `--sh-ink-muted` | `71 85 105` | Secondary text |
| `ink-faint` | `--sh-ink-faint` | `148 163 184` | Disabled/tertiary text |
| `line` | `--sh-line` | `226 232 240` | Borders, dividers |
| `accent` | `--sh-accent` | `220 38 38` | Primary action (red) |
| `accent-strong` | `--sh-accent-strong` | `185 28 28` | Hover state of accent |
| `accent-soft` | `--sh-accent-soft` | `254 226 226` | Light accent bg |
| `success` | `--sh-success` | `22 163 74` | Success alerts |
| `warning` | `--sh-warning` | `202 138 4` | Warning state |
| `danger` | `--sh-danger` | `220 38 38` | Error/danger (= accent) |
| `night` | `--sh-night` | `2 6 23` | Live page dark background |
| `night-surface` | `--sh-night-surface` | `17 24 39` | Live page card surface |
| `night-line` | `--sh-night-line` | `71 85 105` | Live page borders |

**Custom border-radius** (`tailwind.config.js:36-39`): `control` (0.625 rem), `card` (0.75 rem), `pill` (9999 px).

**Custom box-shadow**: `surface` (subtle 1 px lift), `overlay` (dropdown shadow, `tailwind.config.js:41-43`).

**Custom spacing**: `grid-1` through `grid-12` aliases (0.25 rem – 3 rem; defined for 1,2,3,4,5,6,8,10,12).

**Font**: `Tajawal` (Arabic-optimized, loaded from Google Fonts in `app.css:1`) prepended to the default sans stack (`tailwind.config.js:14-16`). Weights 400, 500, 700, 800 are requested.

No RTL plugin is used — all RTL layout is achieved via hardcoded `dir="rtl"` attributes and `text-right` utilities.

---

### CSS Component Layer (`resources/css/app.css`)

Defined with `@layer components`:

| Class | Applied to |
|-------|-----------|
| `.sh-form-shell` | Form container (rounded-2xl, surface bg, ring-1 ring-line) |
| `.sh-form-label` | `<label>` elements |
| `.sh-form-field` | `<input>`, `<select>`, `<textarea>` |
| `.sh-form-help` | Helper text below fields |
| `.sh-form-error` | Validation error messages |
| `.sh-btn-primary` | Submit / primary action buttons (ink bg, white text) |
| `.sh-btn-secondary` | Secondary buttons (surface bg, border) |
| `.sh-alert-success` | Session success banners |
| `.sh-alert-error` | Session error banners |

Breaking ticker CSS classes: `.sh-breaking-track` (flex, `will-change: transform`, `white-space: nowrap`), `.sh-breaking-item`, `.sh-breaking-time`, `.sh-breaking-title`, `.sh-breaking-divider` (`app.css:70-108`).

`[x-cloak] { display: none !important; }` is set globally (`app.css:66-68`).

---

### i18n (`lang/`)

There are exactly two application locale files:

| File | Locale | Status |
|------|--------|--------|
| `lang/ar/ui.php` | Arabic | Primary locale (`APP_LOCALE=ar`) |
| `lang/en/ui.php` | English | Secondary / fallback (`APP_FALLBACK_LOCALE` defaults to `en`) |

Both files are structurally identical with the same key hierarchy (verified — identical top-level and nested key sets):

```
ui.nav.*          — navigation labels (home, live, membership, contact)
ui.pages.*        — page titles (home, live, membership, contact, search)
ui.actions.*      — button/action labels (search, share, copy_link, toggle_menu, native_share, previous, next, …)
ui.footer.*       — footer section labels and social platform names
ui.labels.*       — inline labels (breaking, latest_news, source, published_at)
ui.sections.*     — section headings (breaking_news, latest_episodes, news_reports)
ui.messages.*     — user-facing status/error messages
ui.contact.*      — contact form labels and intro text
ui.membership.*   — membership form intro text
ui.search.*       — search placeholder, results text, no-results text
```

All views call `__('ui.key')`. The `APP_LOCALE=ar` environment variable (`.env:8`; `config/app.php:85` defaults to `en`) sets the active locale globally; there is no runtime locale-switching logic in the public frontend (the `language_chips` header block is a placeholder with hardcoded EN/ES links to `#`).

`lang/vendor/bladewind/` contains translations for the BladeWind UI component library in 11 languages (ar, de, en, es, fr, id, it, nl, pt_BR, tr, zh_CN), delivered by the vendor.

---

### Public Pages

| View file | Route (name) | Layout usage | Key variables received |
|-----------|-------|-------------|----------------------|
| `pages/home.blade.php` | `home` | `<x-layouts.site>` | `$hero`, `$topGridArticles`, `$latestArticles`, `$latestPaginator`, `$newsLayout` (mosaic/classic/grid), `$newsTopSmallCount`, `$membershipSection`, `$contactSection`, `$homeVideosEnabled`, `$homeVideos`, `$homeVideosLayout`, `$homeVideosTitle` |
| `pages/live.blade.php` | `live` | `<x-layouts.site>` | `$liveYoutubeId`, `$selectedYoutubeId`, `$playlistVideos` (collection of `VideoItem`) |
| `pages/article.blade.php` | `news.show` | `<x-layouts.site>` | `$article` (Article model) |
| `pages/membership.blade.php` | `membership` | `<x-layouts.site>` | `$pageTitle`, `$intro`, `$letterHtml`, `$idLabel`, `$submitLabel` |
| `pages/contact.blade.php` | `contact` | `<x-layouts.site>` | `$title`, `$intro`, `$formLabels` |
| `pages/search.blade.php` | `search` | `<x-layouts.site>` | `$query`, `$articles` (paginator) |
| `pages/video.blade.php` | `videos.show` (`/videos/{videoItem}`) | `<x-layouts.site>` | `$video` (VideoItem model) |
| `pages/hosted-video.blade.php` | `hosted-videos.show` | `<x-layouts.site>` | `$video` (HostedVideo model) |
| `pages/page.blade.php` | `pages.show` (`/pages/{slug}`) | `<x-layouts.site>` | `$page` (SitePage model), `$title` |

#### Home page behaviour

The home page supports three **news layout modes** (`home.blade.php:4`):
- `mosaic` (default): hero card (7/12 width) beside a grid of up to `$newsTopSmallCount` small cards (5/12), then latest news
- `classic`: hero card full-width, then latest news  
- `grid`: no hero card, latest news only

The latest-news section uses AJAX pagination (`home.blade.php:47-99`): an Alpine component fetches `route('home.latest')` with `?latest_page=N`, receives JSON with `{ html, page }`, replaces `$refs.latestResults` innerHTML, and `replaceState`s the URL. The HTML fragment is rendered by `partials/home/latest-news-results.blade.php`, which uses custom `data-latest-page` attributes on pagination links (not standard Laravel pagination).

Conditional home sections (all off by default unless `enabled` key is set):
- `$membershipSection` — renders `<x-home.cta-hero>` with style dark/light/red
- `$contactSection` — same component, typically style light
- `$homeVideos` — renders `<x-home.media-section>` in grid or carousel layout

#### Live page behaviour

A dark-themed card (`bg-night`) (`live.blade.php:9`). Alpine `x-data` holds `selectedId` and `embedUrl`; clicking a playlist item calls `select(id)` which updates the iframe `src` to `https://www.youtube.com/embed/${id}?rel=0&autoplay=1` and calls `history.replaceState` with `?v=id` (`live.blade.php:11-22`). The sidebar and iframe are built inline in this view (it does not use the `x-live.*` components).

#### Article page

Pushes OG meta and Twitter card tags via `@push('head')`. Canonical URL from `$article->canonical_url` or `request()->url()`. Content rendered as `nl2br(e(trim(strip_tags($article->content ?: $article->excerpt ?: ''))))` — HTML is stripped before display (`article.blade.php:50`).

#### Membership page

Heavy Alpine usage: file input preview (image files get `URL.createObjectURL`), volunteer area checkboxes (`x-model="volunteerAreas"`), conditional "other" text field and experience detail textarea driven by `x-show` (`membership.blade.php:39-72`). Contains a honeypot field (`name="website"`) hidden with `.hidden` class (`membership.blade.php:494-496`). An optional `$letterHtml` section renders sanitized HTML via `str($letterHtml)->sanitizeHtml()` (`membership.blade.php:29`).

#### Static page

`pages/page.blade.php` renders bilingual: uses `$page->content_html_en` when locale is `en` and the field is populated, otherwise falls back to `$page->content_html_ar` (`page.blade.php:12-16`). Content is printed as raw HTML (pre-sanitized at save time).

---

### Blade Component Library

#### `components/news/`

| Component | Props | Purpose |
|-----------|-------|---------|
| `article-card` | `$article` | 16:9 thumbnail + title + time + share menu; links to `route('news.show', $article->slug)` |
| `hero-card` | `$article`, `$fill` | Full-bleed image with gradient overlay, large title overlay, share menu; `$fill=true` makes height 100% for mosaic layout |
| `breaking-ticker` | `$items` (optional) | Breaking news marquee (see above) |
| `share-menu` | `$article` or (`$url` + `$title`), `$variant` | Alpine dropdown with native share, per-platform URLs from `SharePlatform::activeCached()`, clipboard copy |

#### `components/home/`

| Component | Props | Purpose |
|-----------|-------|---------|
| `cta-hero` | `$title`, `$body`, `$buttonLabel`, `$buttonUrl`, `$imageUrl`, `$style` (light/dark/red) | Full-width CTA banner; style switches bg/text colors |
| `media-section` | `$title`, `$items`, `$layout` (grid/carousel) | Section wrapper rendering `<x-home.media-card>` |
| `media-card` | `$item` | Polymorphic card: detects `HostedVideo` vs `VideoItem`, links to `hosted-videos.show` or `route('live', ['v' => ...])` respectively |
| `video-card` | `$item` | `VideoItem`-only card, links to live page with YouTube ID |
| `hosted-video-card` | `$video` | `HostedVideo`-only card with `posterUrl()`, links to `hosted-videos.show` |
| `video-carousel` | `$title`, `$items` | Horizontal scroll carousel of `<x-home.video-card>` (older component, not the primary path) |
| `video-grid` | `$title`, `$items` | 3-column grid of `<x-home.video-card>` (older component) |
| `hosted-video-grid` | `$title`, `$videos` | 3-column grid of `<x-home.hosted-video-card>` |

#### `components/live/`

These appear to be legacy/unused: across `resources/views`, `x-live.*` is referenced only inside `carousel.blade.php` (which renders `x-live.video-card`); none of these are wired into the current live page, which inlines its own sidebar, cards, and iframe.

| Component | Props | Purpose |
|-----------|-------|---------|
| `player` | `$youtubeUrl` | YouTube iframe embed with `App\Support\YouTube::embedUrl()` |
| `breaking-list` | `$items` | Dark-themed scrollable list of breaking articles (heading `ui.sections.breaking_news`) |
| `carousel` | `$items` | Horizontal scroll of `<x-live.video-card>`, labelled `ui.sections.news_reports` |
| `video-card` | `$item` | Dark-themed thumbnail card for `VideoItem` playlist entries |

#### `components/site/`

| Component | Purpose |
|-----------|---------|
| `header` | JSON-configurable multi-row, multi-zone header (see above) |
| `footer` | Brand, footer nav, optional social links |
| `weather-widget` | Alpine widget: fetches `route('api.weather')`, caches in `localStorage` (key `shaghilla_weather_header_v1`, TTL 10 min), maps WMO codes to emoji, displays `tempText` + `label` |

#### `components/icons/`

| Component | Props | SVG icons |
|-----------|-------|-----------|
| `share` | `$class`, `$title` | Inline SVG share icon (3 circles + 2 lines) |
| `social` | `$name`, `$class`, `$title` | Switch on `$name`: `fa-whatsapp`, `fa-x-twitter`, `fa-facebook-f` / `fa-facebook`, `fa-instagram`; generic person icon fallback |

---

### SiteSettings Flow into Public Views

There are no view composers or shared view data for settings. Every component queries the database directly via static calls at Blade render time:

```
\App\Models\SiteSetting::getValue('key', 'default')
\App\Models\SiteSetting::getBool('key', true|false)
\App\Models\SiteSetting::getInt('key', default_int)
```

Settings consumed by public views:

| SiteSetting key | Consumer | Effect |
|-----------------|---------|--------|
| `site_brand_name` | header, footer | Displayed brand name |
| `header_logo_enabled` | header | Show/hide `<img>` beside brand name |
| `header_logo_url` | header | Logo image URL (falls back to `asset('website-logo.png')`) |
| `header_show_live_button` | header | Show/hide red LIVE button; also suppresses live from nav menu |
| `header_search_enabled` | header | Show/hide inline search form in header |
| `header_weather_enabled` | header | Show/hide weather widget |
| `header_weather_label_ar` | header | Location label shown in weather widget (default 'لبنان') |
| `header_language_chips_enabled` | header | Show/hide EN/ES language chip placeholders |
| `header_layout_json` | header | Full header layout as JSON (row1/row2 zones) |
| `footer_show_socials` | footer | Activate social link section |
| `footer_facebook_url` | footer | Facebook profile URL |
| `footer_x_url` | footer | X (Twitter) profile URL |
| `footer_instagram_url` | footer | Instagram profile URL |
| `breaking_ticker_*` | breaking-ticker | All ticker behaviour (see ticker table above) |
| `ticker_limit` | breaking-ticker | Max breaking articles loaded |

Admin pages for these settings are thin Filament wrappers at `resources/views/filament/pages/`: `header-builder.blade.php`, `homepage-builder.blade.php`, `appearance-settings.blade.php`, `membership-settings.blade.php`, `service-request-settings.blade.php`. These render `{{ $this->form }}` inside `<x-filament-panels::page>` (header-builder also shows an RTL placement tip box above the form) — the actual form schema is defined in the corresponding PHP Filament Page classes.

---

### Filament Admin Views

| View | Purpose | Body |
|------|---------|------|
| `filament/pages/header-builder.blade.php` | Header layout builder (includes RTL tip: "Place Logo and Menu in the Right zone for RTL") | RTL tip box + `{{ $this->form }}` |
| `filament/pages/homepage-builder.blade.php` | Homepage sections builder | `{{ $this->form }}` |
| `filament/pages/appearance-settings.blade.php` | Appearance/theme settings | `{{ $this->form }}` |
| `filament/pages/membership-settings.blade.php` | Membership form settings | `{{ $this->form }}` |
| `filament/pages/service-request-settings.blade.php` | Contact/service form settings | `{{ $this->form }}` |
| `filament/pages/breaking-scraper.blade.php` | Breaking news scraper tools | `{{ $this->form }}` (with `wire:poll.15s`) |
| `filament/pages/rss-automation.blade.php` | RSS automation controls | `{{ $this->form }}` (with `wire:poll.15s`) |
| `filament/pages/maintenance-tools.blade.php` | Maintenance utilities | Custom `<x-filament::section>` content (no form) |
| `filament/pages/updates.blade.php` | Update management (no-SSH deploy) | Custom `<x-filament::section>` content (no form) |
| `filament/widgets/newsroom-quick-actions.blade.php` | Dashboard widget | — |
| `filament/forms/share-platform-icon-preview.blade.php` | Inline icon preview for SharePlatform admin form (renders `<x-icons.social>`) | — |

---

## Configuration, Deployment & Operations

### Key Configuration & Notable Non-Default Settings

Shaghilla is an Arabic-first RTL news site targeting Lebanese content. Several configuration defaults differ from stock Laravel to match the shared-hosting, no-Redis, no-queue-worker deployment target.

#### Runtime defaults (what the app actually runs with)

The `.env.example` and the build script's generated `.env` both establish the following non-default values across config files:

| Config file | Key | Code default | Actual value | Effect |
|---|---|---|---|---|
| `config/app.php` | `APP_LOCALE` | `en` | `ar` | Arabic locale; RTL UI |
| `config/app.php` | `APP_TIMEZONE` | `UTC` | `Asia/Beirut` | Timestamps in Lebanon time |
| `config/app.php` | `APP_URL` | `http://localhost` | `https://shaghilla.org`; or auto-detected from `HTTP_HOST` if env absent (`config/app.php:55–59`) | Canonical URL; fallback makes local dev work without editing .env |
| `config/database.php` | `DB_CONNECTION` | `sqlite` | `mysql` | Production database |
| `config/cache.php` | `CACHE_STORE` | `database` | `file` | File cache (no DB table needed, shared-hosting friendly) |
| `config/queue.php` | `QUEUE_CONNECTION` | `database` | `sync` | Synchronous queue; no queue worker process required |
| `config/session.php` | `SESSION_DRIVER` | `database` | `file` | File sessions (the `sessions` table is still created in install.sql for when the driver is `database`) |
| `config/mail.php` | `MAIL_MAILER` | `log` | `log` | Mail is logged, not sent; no SMTP configured out of the box |
| `config/logging.php` | `LOG_LEVEL` | `debug` | `debug` (dev) / `info` (prod build) | Build script sets `info` in generated prod .env |

`config/services.php` contains stubs for Postmark, SES, Resend, and Slack notifications, but none are populated in `.env.example` — they are unused unless explicitly configured.

BladewindUI component defaults are tuned in `config/bladewind.php` (RTL-friendly: datepicker `yyyy-mm-dd`, no table striping, blurred modal backdrops).

#### Important `.env` variables

| Variable | Purpose |
|---|---|
| `APP_NAME` | Application display name (`رابطة الشغيلة`) |
| `APP_ENV` | `local` in dev / `production` in deployed builds |
| `APP_KEY` | AES-256-CBC encryption key; auto-generated by build script, persisted in `.cpanel-secrets.local` |
| `APP_DEBUG` | Set `false` in production |
| `APP_TIMEZONE` | `Asia/Beirut` — affects all PHP date functions and timestamps |
| `APP_LOCALE` | `ar` — switches frontend locale and RTL mode |
| `DB_CONNECTION` | `mysql` — production database driver |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | MySQL/MariaDB connection; `localhost` on cPanel |
| `CACHE_STORE` | `file` — no Redis or DB cache table needed |
| `QUEUE_CONNECTION` | `sync` — no queue worker; jobs run inline |
| `SESSION_DRIVER` | `file` — sessions stored on disk |
| `RSS_IMPORT_SECRET` | URL token for the `/tasks/import-rss/{secret}` cron webhook; auto-generated by build script, persisted in `.cpanel-secrets.local` |
| `ALMANAR_URGENT_SECRET` | URL token for the Al-Manar urgent import webhook; **not** auto-generated — set manually |
| `ADMIN_EMAIL` | Filament admin login email (`admin@shaghilla.org`) |
| `ADMIN_PASSWORD` | Admin password; blank in `.env.example` (set via SQL seed / first install) |
| `DASHBOARD_UPDATE_TOKEN` | When non-empty, enables the in-admin deploy mechanism (Filament → Automation → Updates); leave empty to disable |
| `SLACK_BOT_USER_OAUTH_TOKEN` / `SLACK_BOT_USER_DEFAULT_CHANNEL` | Optional Slack notifications; not configured in example |
| `LOG_LEVEL` | `debug` in dev, overridden to `info` by build script in generated prod `.env` |

---

### Custom Filesystem Disks

`config/filesystems.php` defines one non-standard disk beyond Laravel's built-in `local`, `public`, and `s3`:

**`public_uploads`** (`config/filesystems.php:50–57`)
- Driver: `local`
- Root: `public_path('uploads')` → `public_html/uploads/` on the server
- URL: `APP_URL/uploads`, visibility `public`
- Used for: RSS-downloaded article images and self-hosted video files/posters

Because this disk roots inside `public_html/`, files are served directly by the web server without going through PHP. The build script and the `DashboardUpdater` service both explicitly preserve `uploads/` during deployments (`syncPublicHtml` skips the `uploads` item — `DashboardUpdater.php:274`).

---

### cPanel (No-SSH) Deployment Workflow

The project targets cPanel shared hosting with no SSH terminal. The deployment model uses a two-folder layout:

- **Private app folder** (`app/`, outside `public_html`): all Laravel source, `vendor/`, config, and `.env`
- **Public webroot** (`public_html/`): compiled front-end assets, a custom `index.php`, and the `uploads/` directory

#### Building the upload artifact (`scripts/build-cpanel-upload.sh`)

Run locally from the repo root:

```bash
composer install --no-dev --optimize-autoloader
./scripts/build-cpanel-upload.sh
```

What the script does:

1. **Asset build**: `npm ci` (if `node_modules/` absent) then `npm run build`, compiling Tailwind/Vite assets into `public/build/`.
2. **App copy**: `rsync`s the repo into `cpanel_upload/app/`, excluding `.git/`, `node_modules/`, `cpanel_upload/`, the backup zips, `.cpanel-secrets.local`, `database.txt`, loose root images, `public/uploads/news/`, `public/uploads/videos/`, `tests/`, `.env`, `laravel.log`, and compiled caches.
3. **Build stamp**: writes a `YYYYMMDD_HHMMSS` build ID to `cpanel_upload/app/.shaghilla-build-id`.
4. **Public copy**: `rsync`s `public/` into `cpanel_upload/public_html/`, excluding `uploads/news/` and `uploads/videos/`.
5. **Custom `index.php`**: writes a cPanel-aware `public_html/index.php` that auto-detects the private app folder by scanning siblings of `public_html/` for the newest `.shaghilla-build-id` stamp (falls back to a `.app-root` override file). Adds diagnostic response headers (`X-Shaghilla-Index-Build`, `X-Shaghilla-App-Build`, `X-Shaghilla-App-Root-Hash`).
6. **Secret generation**: reads (or creates) `.cpanel-secrets.local` to extract a stable `APP_KEY` and `RSS_IMPORT_SECRET` (never rotated between builds — keep the file locally; it is gitignored).
7. **Generated `.env`**: writes `cpanel_upload/app/.env` with production settings (`APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=info`, …) plus the secrets from `.cpanel-secrets.local` and DB credentials from `database.txt` (a local gitignored file with `Database:`/`User:`/`Pass:` lines).
8. **Zip**: produces `shaghilla_cpanel_upload_YYYYMMDD_HHMMSS.zip` containing both `app/` and `public_html/`.

> `ALMANAR_URGENT_SECRET` and `DASHBOARD_UPDATE_TOKEN` are **not** included in the auto-generated `.env`; add them manually after upload if needed.

#### First install (operator steps)

1. In cPanel, create a MySQL database + user, grant all privileges.
2. In phpMyAdmin, import `database/shaghilla_install.sql`.
3. Upload and extract the build zip to the cPanel home directory.
4. Move `app/` to the private folder (e.g. `~/app`); copy `public_html/` contents to the webroot.
5. Verify the generated `app/.env` (DB credentials).
6. Ensure `storage/` and `bootstrap/cache/` are writable (755/775).
7. Verify `/` (homepage) and `/admin` (Filament login).

#### Update workflow (operator steps)

1. Export the DB from phpMyAdmin as backup.
2. Rename the old private app folder (`app_backup_YYYYMMDD`).
3. Upload + extract the new zip — **do not** overwrite `app/.env` or `public_html/uploads/`.
4. Import any new `shaghilla_patch_*.sql` files via phpMyAdmin.
5. Clear caches by deleting contents of `bootstrap/cache/`, `storage/framework/cache/`, `storage/framework/views/`.
6. Verify pages, admin login, and a manual RSS import.

#### In-admin deploy mechanism (`DASHBOARD_UPDATE_TOKEN`)

When `DASHBOARD_UPDATE_TOKEN` is set, the Filament **Automation → Updates** page lets an authenticated admin upload a build zip and trigger a server-side deploy via `App\Services\DashboardUpdater`. The service:
- Validates the token with `hash_equals` (timing-safe)
- Validates zip structure (must contain `app/vendor/autoload.php` and `app/bootstrap/app.php`)
- Rejects zip entries with absolute paths or `..` traversal
- Extracts to a temp dir under `storage/app/private/updates/`
- Copies the current `.env` into the new app directory before going live
- Places the new app in a timestamped sibling directory and updates `public_html/.app-root` to repoint the live `index.php`
- Syncs `public_html/` assets while preserving `uploads/` and `.app-root`

This enables rolling deploys from the browser without File Manager or FTP.

---

### Database SQL File Summary

| File | Type | What it does |
|---|---|---|
| `shaghilla_install.sql` | Full install | Creates all base tables (`migrations`, `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `categories`, `feed_sources`, `keyword_rules`, `site_settings`, `site_pages`, `membership_applications`, `video_items`, `hosted_videos`, `contact_messages`, `articles`). Seeds 2 categories, 30 worker + 10 breaking keyword rules, 3 feed sources, 60+ site settings, the nav menu, and 1 admin user (placeholder credentials only — see security note). |
| `shaghilla_patch_article_destinations.sql` | Additive (idempotent) | Adds `destination` to `feed_sources` (`home`/`both`/`breaking`) and `show_on_home`, `is_breaking_locked`, `show_on_home_locked` to `articles`. Lock flags stop the RSS importer overriding admin editorial choices. `INFORMATION_SCHEMA`-guarded. |
| `shaghilla_patch_hosted_videos.sql` | Additive (`CREATE TABLE IF NOT EXISTS`) | Creates `hosted_videos` (self-hosted video). Seeds home-video-section site settings. |
| `shaghilla_patch_membership_applications.sql` | Additive (idempotent) | Creates/expands `membership_applications` (adds registry, volunteer, experience columns). Seeds membership form labels. |
| `shaghilla_patch_prune_old_articles.sql` | **Destructive maintenance** | Deletes RSS-imported articles older than `@keep_days` (default 30); keeps manual/Al-Manar and optionally breaking. **Not idempotent — back up first.** |
| `shaghilla_patch_remove_telegram.sql` | Cleanup (idempotent) | Drops legacy Telegram tables, removes Telegram from `share_platforms`, deletes `telegram_*` site settings. |
| `shaghilla_patch_share_platforms.sql` | Additive (`CREATE TABLE IF NOT EXISTS`) | Creates `share_platforms`; seeds WhatsApp, X, Instagram (native), Facebook. `ON DUPLICATE KEY UPDATE`. |
| `shaghilla_patch_site_pages.sql` | Additive (`CREATE TABLE IF NOT EXISTS`) | Creates `site_pages` (nav management: route / slug / external / custom HTML). Seeds the 4 system nav items. |

---

### Automation & Cron Model

Because the target is cPanel shared hosting without a persistent queue worker, scheduled work runs through two mechanisms:

**Option A — Laravel scheduler heartbeat (preferred).** A cPanel cron fires every minute:
```
* * * * * php /home/USER/app/artisan schedule:run >/dev/null 2>&1
```
`routes/console.php` registers two tasks:
- **`news:import-rss`** — runs every minute but self-throttles via `site_settings.rss_cron_interval_minutes` (default 60) by checking `last_rss_import_at`; `withoutOverlapping(55)`.
- **`news:import-almanar-urgent`** — runs every minute; `withoutOverlapping(2)`.

**Option B — URL-secret webhooks (when `php` isn't available in cron).** Both pipelines accept POST to a secret URL:
- RSS: `POST https://shaghilla.org/tasks/import-rss/{rss_import_secret}`
- Al-Manar urgent: `POST https://shaghilla.org/tasks/import-almanar-urgent/{almanar_urgent_secret}`

Example hourly cron:
```
0 * * * * curl -fsS -X POST "https://shaghilla.org/tasks/import-rss/<secret>" -d "ts=$(date +%s)" >/dev/null 2>&1
```
The `rss_import_secret` is surfaced in Filament under **Automation → RSS Import**. `QUEUE_CONNECTION=sync` means there is no queue daemon; queued jobs run inline in the request.

---

### Operational Security Posture

**Environment secrets** are managed three ways: (1) `.env` lives in the private app folder, never under `public_html/`, and is excluded from the build rsync; (2) `.cpanel-secrets.local` (gitignored) persists `APP_KEY` and `RSS_IMPORT_SECRET` across builds to prevent unintended rotation; (3) `database.txt` (gitignored) holds DB credentials the build reads into the generated `.env`.

**URL-secret import webhooks.** `RSS_IMPORT_SECRET` / `ALMANAR_URGENT_SECRET` act as bearer tokens in the request *path* (cron-friendly, no headers needed), so the secret appears in server access logs and cron definitions. Treat them as passwords: long, random, rotated if exposed.

**`DASHBOARD_UPDATE_TOKEN`.** When set, it lets an authenticated Filament admin upload and deploy a build zip from the browser — granting filesystem write to the cPanel home directory from a web request. The attack surface is the admin login plus the token; `DashboardUpdater` mitigates with `hash_equals` and path-traversal validation. The `.env` comment advises leaving it empty to disable when not deploying.

> **Note for maintainers — no passwords or secrets are committed.** The install/example artifacts originally shipped with *real* production credentials. In preparing this repository **all password material was removed**, not just masked:
> - `database/shaghilla_install.sql` — the admin user is seeded with an **empty** password hash (login disabled until you set one via the documented `UPDATE` one-liner); the real `rss_import_secret` is replaced with `change-me`.
> - `database/seeders/AdminUserSeeder.php` — no default password; requires `ADMIN_PASSWORD` from `.env` or it skips.
> - `.env.example` — `DB_PASSWORD` and `ADMIN_PASSWORD` are empty.
> - `database/factories/UserFactory.php` — hashes a random `Str::random(16)` instead of the usual literal `'password'`, so no password string is committed even in test scaffolding.
>
> A repo-wide scan confirms **no plaintext passwords, bcrypt hashes, or live secrets** are tracked.
>
> **If the original values were ever live on the server, rotate them now** (admin password, DB password, `rss_import_secret`) — and do not commit the cPanel backup zips, which still contain the originals.

