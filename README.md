# Buziness

**Current release:** See [RELEASE.md](RELEASE.md) for implemented changes, setup and executed test evidence. The original development-build notes below describe the supplied baseline and are retained for provenance; their unverified status is superseded by the release notes.

## Original ExcelGuru development build

A single-business Laravel/PHP + MySQL web application intended for separate Hostinger installations. Internet is required. One primary administrator creates staff logins; branches and warehouses belong to that business.

**Status: unverified development source, not a production release.** The authoring environment did not contain PHP or Composer; installing PHP was unavailable. Dependencies were not resolved, PHP was not executed, and no MySQL or end-to-end test has passed here. Do not load live accounting data or promise client capacity based on this package.

Start with [SETUP.md](SETUP.md). Read [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md) before evaluating functionality. [ARCHITECTURE.md](ARCHITECTURE.md) records the decisions and data flows. [VALIDATION.md](VALIDATION.md) distinguishes completed checks from tests still to run.

## Included source

- Laravel bootstrap/configuration, migrations, chart of accounts, interactive admin creation.
- Session login, CSRF protection, action permissions, branch access, protected printing.
- Responsive dashboard, masters, sales/purchases, counter checkout, drafts/posting, returns, vouchers, journal entries, stock adjustment, opening stock, warehouse transfers.
- Linked purchase order / goods receipt / bill and order / delivery / invoice workflows.
- Stock and double-entry posting in database transactions; serialized posting per business; optimistic draft edits; audit snapshots; reversals.
- Negative stock warnings and provisional moving-average cost settlement.
- Attendance, payroll calculation using administrator-entered effective-dated percentage rules, payslips, accounting posting.
- Manufacturing component/output orders; restaurant order tracking; service jobs; project records; asset register and monthly straight-line book depreciation; cheque tracking.
- Statement-line reconciliation, trial balance, profit/loss, balance sheet, current invoice ageing, ledger CSV export.
- XLSX/CSV preview and import templates; A4/thermal HTML print layouts; online-only PWA manifest/service worker.
- PHP tests and a GitHub Actions workflow for execution on PHP 8.3 with SQLite and MySQL.

There is no built-in demo login, sample business data or default password. Create your owner login through the CLI after installing dependencies and migrating the database.

## Important scope boundaries

This first build does **not** complete the entire originally discussed ERP scope. In particular, restaurant recipe consumption/kitchen printing, advanced project costing, comprehensive Indian statutory/tax integrations, complete three-language translation, production concurrency validation and device-specific printer testing remain unfinished. The status document enumerates them instead of representing record screens as complete industry applications.

No cloud deployment, Hostinger account access, domain purchase or subscription was performed. No real client data is included. Application name is a configurable working name.
