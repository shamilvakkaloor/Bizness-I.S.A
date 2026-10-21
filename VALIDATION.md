# Validation record

## Performed in the authoring environment

- `node --check public/app.js` — passed.
- `node --check public/sw.js` — to be captured in `validation-result.json`.
- JSON parsing and local source/route/package consistency checks — results in `validation-result.json`.
- Manual source review for permissions, transaction boundaries, duplicate posting, line matching and financial balancing — performed; not equivalent to execution.

## Not performed

- PHP/Composer execution: PHP and Composer are absent. The available package installation route failed; no PHP syntax or backend pass is claimed.
- Dependency installation / lockfile generation: not completed.
- Browser interaction/visual validation: local runner lacked a browser executable; the provided remote browser could not open the localhost preview (`ERR_BLOCKED_BY_CLIENT`). No screenshot or UI pass is claimed.
- Real MySQL tests, concurrency, real printer output and load testing: not run.
- Hostinger deployment: not performed; no Hostinger credentials/session or deployment target has been supplied.

## Included executable PHP tests

Money parsing and rounding; purchase/sale stock and ledgers; double-post idempotence; unbalanced journal rollback; negative stock cost settlement; receipt-to-bill stock duplication prevention; source quantity limits; unregistered GST rejection; posted-edit rejection; reversal history; branch/permission isolation; authentication; period locks; duplicate counter checkout.

Run `composer test` on PHP with SQLite, then with a dedicated disposable MySQL database. CI includes both. These tests are authored but unexecuted; failures must be fixed before any live use. They do not cover the full ERP scope.

## Release acceptance still required

- Concurrent sale/post/receipt/reversal tests on MySQL, including simultaneous requests from two staff.
- Full create/read/edit/approve/reverse permissions and branch-ID tampering tests.
- Payroll arithmetic, applicability, adjustments, deductions/advance recovery and statutory review.
- Tax rounding, discounts, returns, place of supply, GSTIN validation and invoice mandatory fields.
- Chronological stock cost reconciliation, zero-stock rounding, negative cover, serial uniqueness and returns.
- Financial statements reconciled to a reviewed sample set of books.
- File import limits, malformed workbook, formula injection, duplicate submits and rollback.
- Responsive UI, keyboard/focus, print layouts, three-language text/glyphs.
- Backup and restoration with the same application key.
- 10 deployments × 10 concurrent users with realistic products/bills and report generation.
