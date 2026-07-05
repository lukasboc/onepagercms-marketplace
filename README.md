# OnePagerCMS Marketplace

Laravel application powering the OnePagerCMS plugin & theme marketplace:
developer submissions with a human review process (modeled after
wordpress.org/plugins), and a public JSON API consumed by OnePagerCMS
installations (Extensions → Marketplace).

- Laravel 13.x, Breeze (Blade) auth with email verification, SQLite by default.
- ZIPs of free items are stored on the `local` disk (`storage/app/private/items/...`)
  and streamed through the API. Paid items are listed with a purchase link only;
  their distribution, license validation and updates run on the developer's own
  server (see `docs/license-server-example.php`).

## Setup

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed     # seeds admin@marketplace.test / dev@marketplace.test (password: "password")
npm install && npm run build
php artisan serve
```

Point a OnePagerCMS installation at this server by setting the `marketplace-url`
setting (settings table) to `http://127.0.0.1:8000/api/v1`.

## Public pages (no login required)

- `/` — landing page with most popular / recently added extensions.
- `/plugins`, `/themes` — searchable catalog (approved items only).
- `/items/{slug}` — detail page with description, version history and a direct
  download for free items (paid items link to the developer's shop).
- `/developers` — public developer guide (how submissions work, manifest
  reference, guidelines, paid/license protocol) with a "Submit your extension"
  call to action. Login is only required from that point on — submitting,
  managing items and the admin review queue stay behind auth.

## Roles & flows

- **Developer** (`role = developer`, default on registration): registers, verifies
  e-mail, submits a ZIP + metadata under `/developer/items`. Slug, name, type and
  version come from the manifest inside the ZIP. Automated checks run on
  submission (valid ZIP, manifest rules identical to the CMS installer, zip-slip
  scan, `php -l` on all PHP files, ≤ 50 MB). Updates to listed items are
  submitted as new versions and reviewed again.
- **Admin** (`role = admin`): review queue under `/admin/review` — inspect
  metadata, download the review ZIP, approve or reject (rejection note required).
  The developer is notified by e-mail (`ItemReviewed` mailable).
- Paid items require a `purchase_url` and an `update_endpoint` in the manifest.
  The API never serves their ZIPs (`403` on the download route).

## JSON API (v1, read-only, throttled, only `approved` content)

```
GET /api/v1/items?type=plugin|theme&search=&page=&per_page=
GET /api/v1/items/{slug}
GET /api/v1/items/{slug}/download        # free items; increments download counter
GET /api/v1/updates?items[]=slug:version # bulk update check
```

## Tests

```bash
php artisan test
```

Covers the API surface (visibility rules, download rules for free/paid,
update checks) and the full submission → review → listing flow.
