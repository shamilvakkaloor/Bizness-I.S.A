# Buziness — initial operational release

Private business workspace for **Buziness Test**, developed from the supplied ExcelGuru source. Deployment target: https://buziness.exelapp.com/.

## Changes

- Redesigned responsive sign-in, navigation, dashboard, forms and tables.
- Owner/staff listing, editing, permission assignment and account disabling.
- Duplicate-email validation and immediate active permissions for newly created users.
- Resolved and locked dependencies for Hostinger PHP 8.3.35.
- Repaired the previously unexecuted test harness (helper naming, Mockery and Laravel test bootstrap).
- Added a protected Hostinger package with runtime configuration outside public_html, blocked backend paths, and a token-protected one-time initializer.

## Included workflows

Sales, quotations, orders, deliveries, purchases, returns, counter checkout, contacts, products, stock movements, draft/post/reverse transactions, double-entry accounting, receipts/payments, financial reports, audit history, imports, payroll/attendance and foundational industry-operation records.

This release does not complete every advanced ERP feature. The inherited IMPLEMENTATION_STATUS.md remains the scope backlog for statutory integrations, advanced costing, full localisation, kitchen workflows and capacity validation. Its statements that PHP was never executed are superseded by the checks below. No real-printer, statutory-compliance or concurrent-user capacity certification is claimed.

## Verification

2026-10-21: PHP 8.4.26 locally, with dependencies constrained to PHP 8.3.35. PHPUnit passed **18 tests / 52 assertions** on SQLite. Coverage includes stock/accounting posting, negative stock settlement, GRN/bill links, over-receipt rejection, reversal, duplicate checkout/post prevention, period locks, branch permissions, authentication and staff administration. PHP/JS syntax checks passed; Composer audit found no known advisories.

GitHub Actions runs SQLite and MySQL tests, lint and Composer audit on each push. Browser and live deployment results are appended after verification.

Verified GitHub run: [39621512005](https://github.com/shamilvakkaloor/Bizness-I.S.A/actions/runs/39621512005), commit `46dcee8`: **all checks passed**, including PHP 8.3.35, SQLite, MySQL 8.4 and Composer audit. Local authenticated smoke checks returned HTTP 200 for all 18 module/read endpoints. Desktop and 390-pixel mobile screenshots were inspected; login, product creation and settings/team display were checked in a browser.

Deployment verified 2026-10-22: live at https://buziness.exelapp.com/ on Hostinger. Database initialized and owner login verified. All 18 authenticated module/read endpoints returned HTTP 200. Private backend/configuration paths returned 403; the one-time initializer returned 404 after successful installation. Release archive remains outside public_html. Source secrets and owner credentials are excluded from Git.

## Development

Install PHP 8.3+ and Composer. Run `composer install`, copy `.env.example` to `.env`, generate the app key with `php artisan key:generate`, configure a local database, then run `php artisan migrate --seed` and `php artisan business:admin`. Start with `php artisan serve`; test with `composer test`. SQLite is supported locally; production uses MySQL.

## Deployment

Run `python deploy/package.py --secrets <private-json-path>` to stage `.deploy/`, then deploy that directory using Hostinger. Secrets include `app_key`, `db_password` and `setup_token`. Submit the owner password to the token-protected initializer over HTTPS. It removes itself after success. No default credentials are committed.

The packager targets this installation and is for first installation. Before future deployments, preserve runtime configuration and server storage; apply migrations without recreating the owner. Runtime configuration lives outside public_html. Local `.env`, `.tools/`, `.deploy/`, test databases and browser artifacts are ignored by Git.

