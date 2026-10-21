# Setup and Hostinger deployment

## 1. First run on a development machine

Use PHP 8.3 or a newer version compatible with the resolved dependencies, Composer 2 and MySQL 8.0+. Required PHP extensions include PDO MySQL, PDO SQLite (tests), mbstring, XML/DOM, ctype, curl, fileinfo, openssl, zip and GD. The Excel dependency may require additional extensions; Composer checks the exact requirements.

On Windows, use an official PHP development environment with Composer and MySQL. Run these commands inside the extracted `business-suite` directory:

```bash
composer install
```

**No composer.lock or vendor directory is included**, because dependency downloads and PHP execution were unavailable in the authoring environment. The first install resolves the supported version ranges and creates a lockfile. Review that lockfile, run `composer audit` and all tests, then commit it. Every subsequent environment must install from the same reviewed lockfile. Do not treat dependency resolution as already verified.

Copy `.env.example` to `.env` (Windows: `copy .env.example .env`; macOS/Linux: `cp .env.example .env`). Create an empty database and a database user limited to that database. Enter its credentials in `.env`.

```bash
php artisan key:generate
php artisan migrate --seed
php artisan business:admin
composer test
php artisan serve
```

The admin command asks privately for a password of at least 12 characters; there is no public registration or installation page. Open `http://localhost:8000`. For local HTTP only, `SESSION_SECURE_COOKIE=false` is needed; turn it on for production HTTPS.

Node/npm are not required for the shipped interface: the CSS and JavaScript are plain assets. PHP and Composer are required.

## 2. Configure the business

1. Settings → Edit profile: business name/address, state, registration status, valid GSTIN when applicable, receipt width and enabled modules.
2. Add branches, warehouses, accounts and staff. Only one business/legal entity belongs in an installation.
3. Add customers/suppliers and a named walk-in customer for counter sales.
4. Add products/services. Use a separate SKU for each variant; turn stock tracking off for a service.
5. Enter an opening journal for cash, bank, customer/supplier dues and other ledger balances. Use a separate journal per party when opening subsidiary balances are needed.
6. Enter opening stock using the Opening stock document. It debits Inventory and credits Opening equity; do not duplicate that inventory value in the opening journal.
7. Review a trial balance before entering operational transactions.

## 3. Required acceptance checks

Run `composer test` first. Also run the same suite against a dedicated disposable MySQL test database; test commands must never point at live data. The CI workflow shows the settings. The suite performs migrations and data resets.

Test your actual workflows: purchase → receipt → bill → partial payment; invoice → receipt → return; negative stock → subsequent receipt; transfers; payroll; period locks; branch restrictions; concurrent posting; reversal history. Use the gates in `VALIDATION.md`.

The financial calculations and tax classification need an accountant's acceptance. Administrator-entered payroll rules are a configurable calculation mechanism, not a certified statutory rules service.

## 4. Hostinger installation

Confirm your purchased plan supports the selected PHP version/extensions, MySQL, Composer/SSH or uploading locally built dependencies, and the necessary private directory access. The provider is selected; capacity is not verified.

Use one subdomain/domain and one separate database per business. Subdirectory hosting is not supported by the root-relative asset URLs in this build.

Recommended directory layout:

- Private application directory: `business-suite/` (app, bootstrap, config, database, resources, routes, storage, vendor, .env).
- Website document root: `business-suite/public/` if your hosting configuration supports it.

If the plan fixes the document root to `public_html`, copy only the contents of `public/` there and use `deploy/hostinger-index.php` as `public_html/index.php`. The sample assumes the private application is the sibling `../business-suite`; adjust that one path for the actual account layout.

**Do not place `.env`, the project root, database exports or private storage under the public web root.** Do not expose Composer or Artisan through a browser route.

Set production environment values:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-business-subdomain.example
SESSION_SECURE_COOKIE=true
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=your_database
DB_USERNAME=your_database_user
DB_PASSWORD=your_private_password
```

Generate a distinct APP_KEY for every new business installation. Never change the APP_KEY on an established installation without a migration/recovery plan.

On a clean production installation, using the tested lockfile:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan key:generate
php artisan migrate --seed --force
php artisan business:admin
php artisan optimize
```

If server-side Composer is unavailable, build `vendor/` locally on compatible PHP and upload it with the application. Retain the reviewed composer.lock. Ensure `storage/` and `bootstrap/cache/` are writable by the PHP process; use the least permissions required by the account, not blanket 777.

Enable HTTPS before inviting users. Check login, saving a draft, posting, printing and logout on the actual URL. PWA installation requires HTTPS and browser support; it does not provide offline billing.

## 5. Multiple businesses and workload

Create an independent deployment, APP_KEY, database and database user per business. Keep a release/version register for each. Share code through the source repository, not shared business databases.

The intended target is 10 businesses × 10 concurrent staff. This is **not** a proven capacity of the Unlimited hosting plan. Measure p95 response times and posting latency under a 100-user workload using representative data before committing to this capacity. Posting is intentionally serialized inside each business to protect accounting integrity; it may become the bottleneck. Migrate busy businesses to separate resources when measured results require it.

## 6. Backups and updates

Back up each database and private storage, and preserve its APP_KEY in a protected location. Test restoration into a separate environment. Host-level daily backups alone do not guarantee that every transaction since the last backup can be recovered.

For an update: back up, enter maintenance mode, deploy the reviewed source and dependency lock, run migrations, clear/rebuild caches, perform a smoke test and exit maintenance mode. Do not run `migrate:fresh`, a test suite, or a reset seed against production.

## 7. Password recovery and printing

Use `php artisan business:reset-password email@example.com` from the authorised server console. There is no email password-reset integration yet.

A4 and thermal receipts use the browser's print dialog. Set the physical paper width and margins in the printer driver. Test 58 mm/80 mm output, long item names, Malayalam/Hindi glyphs and pagination. Silent USB/Bluetooth/ESC-POS printing is not implemented.
