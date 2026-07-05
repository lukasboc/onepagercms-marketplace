# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Laravel 13 (PHP 8.3) app powering the OnePagerCMS plugin & theme marketplace: developer ZIP submissions with a human review queue (modeled after wordpress.org/plugins) plus a read-only JSON API consumed by OnePagerCMS installations. Stack: Breeze (Blade) auth with email verification, Alpine.js, Tailwind, Vite, SQLite by default.

## Commands

```bash
composer setup                 # full first-time setup (install, .env, key, migrate, npm build)
composer dev                   # serve + queue + pail logs + vite, concurrently
php artisan migrate --seed     # seeds admin@marketplace.test / dev@marketplace.test (password: "password")

php artisan test                                        # all tests (in-memory SQLite, no setup needed)
php artisan test tests/Feature/SubmissionFlowTest.php   # one file
php artisan test --filter=test_full_submission_and_review_flow  # one test

vendor/bin/pint                # code style (Laravel Pint)
npm run build                  # Vite build (needed before serving if assets missing)
```

## Architecture

Three surfaces sharing the same models:

- **Public catalog** (`CatalogController`, `resources/views/public/`) — `/`, `/plugins`, `/themes`, `/items/{slug}`, `/developers`. No auth; shows approved items only.
- **Authed areas** (`routes/web.php`) — `/developer/items` (submission + new versions, `auth`+`verified`) and `/admin/review` (queue keyed by pending `ItemVersion`, `admin` middleware — alias registered in `bootstrap/app.php`, checks `users.role`).
- **JSON API v1** (`routes/api.php`, `app/Http/Controllers/Api/`) — read-only, `throttle:60,1`, exposes only approved items that have ≥1 approved version. `/api/v1/updates?items[]=slug:version` is the bulk update check used by CMS installs.

### Domain model & lifecycle

`Item` (status: pending/approved/rejected/delisted) → hasMany `ItemVersion` (pending/approved/rejected), `ReviewNote`, `ItemScreenshot`. Every submission — initial or update — creates a pending `ItemVersion` that an admin must approve. Approving a version auto-approves a pending item; rejecting one only rejects the item if no other pending/approved versions exist (`Admin/ReviewController`). Both actions record a `ReviewNote` and send the `ItemReviewed` mailable.

Item identity (slug, name, type, version) comes from the manifest **inside the uploaded ZIP** (`plugin.json`/`theme.json`), never from form input. `App\Services\ZipManifestService` validates uploads and deliberately mirrors the CMS installer's rules (`onepagercms system/Installer.php` in the sibling repo): zip-slip scan, slug/version format, plugin `main` entry, `php -l` lint of all contained PHP files, ≤ 50 MB. If validation rules change here, keep them in sync with the CMS installer.

`Item::latestApprovedVersion()` sorts versions with PHP natural sort, not SQL — don't replace it with an `orderBy('version')`.

### Free vs. paid items

Free item ZIPs live on the `local` disk (`storage/app/private/items/{slug}/`) and are streamed through the API download route, which increments the download counter. Paid items are listing-only: the API returns `403` on their download route, they require a `purchase_url` (form) and an `update_endpoint` (manifest), and distribution/licensing/updates run on the developer's own server — reference implementation in `docs/license-server-example.php`.

## Tests

Feature tests use in-memory SQLite (`phpunit.xml`), `Storage::fake('local')`, and `Mail::fake()`. Submission tests build real ZIPs at runtime with `ZipArchive` helpers (see `SubmissionFlowTest::makePluginZip()`) — reuse that pattern rather than fixture files.
