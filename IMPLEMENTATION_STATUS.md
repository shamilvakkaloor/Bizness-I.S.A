# Implementation status — 0.1 development source

“Source included” means authored, not executed or certified. Nothing in this file implies successful PHP/backend testing.

| Requirement | What this package includes | Remaining work / limits |
|---|---|---|
| Laravel/MySQL/Hostinger | Standalone Laravel source, schema, config, setup guide | Install/resolve dependencies, lock versions, execute tests, deploy |
| Separate business deployments | Single-business schema and per-installation configuration | Provision the ten installations and measure capacity |
| Admin and staff | Login, password change/reset command, staff creation/update API, role/action/branch enforcement | Staff listing/editing UI, forced first-login password change, MFA and email recovery |
| Full change history | Audit before/after snapshots and posted document snapshots | Tamper-evident external archive; advanced audit diffs |
| Sales + POS | Invoice entry, quotation/order/delivery, counter payment checkout, returns | Dedicated rapid barcode counter screen, split tender and drawer integration |
| Purchases | Direct bill, optional PO/GRN/bill linking, partial quantities, supplier payments | Automated three-way price tolerance/approval, freight/landed cost |
| Accounting | Journals, opening balances, receipts/payments, expenses/income, balanced posting, period lock | Year-end closure workflow, advanced financial statement presentation, cash-flow statement |
| Inventory | Multiple branches/warehouses, transfer, opening stock, negative alerts, moving average | Negative transfers/production blocked; formerly provisional sale returns blocked; backdated cost replay absent |
| Variants/units | SKU per variant, unit conversion validation in API | Variant matrix and conversion editor/selector UI |
| Batch/serial/expiry | Lot balances, serial availability, expiry checks | FEFO automatic allocation; returned expired stock quarantine; full serial lifecycle reports |
| Price lists | Retail, wholesale, party price overrides and discount limits | Editable existing party-price UI and date-ranged promotions |
| Manufacturing | Component/output order and transactional one-output production posting | BOM versions, scrap, labour/overhead allocation, multi-output costing, batch/serial production UI |
| Restaurant | Table/takeaway order records, items, notes, stages and linked invoice | Recipe ingredient consumption, kitchen ticket printing, table split/merge and billing conversion |
| Service business | Assigned job, description, due date, linked invoice | Work logs, parts consumption, recurring billing |
| Projects/contracts | Budget, milestones, linked documents | Automated project costing, retention, progress billing and cost-centre allocation |
| Payroll | Attendance/leave labels, overtime, adjustments, advance recovery, payslips and journals | Employee-specific statutory eligibility, wage component structures, labour-law rules, statutory filings, advance subsidiary ledger and payroll correction workflow |
| Statutory calculation | Effective-dated configurable percentage/cap rules; manual review required | No built-in verified PF/ESI/PT/TDS rules, thresholds or automatic legal updates |
| Fixed assets | Register, sequential straight-line monthly book depreciation, journal | Acquisition validation against cost, disposal, impairment, pro-rata conventions, tax depreciation |
| Bank/cheques | Manual statement entries, exact matching to one ledger entry, cheque tracking | Bank spreadsheet import, matching suggestions, split matches, automatic cheque settlement |
| Financial reporting | Trial balance, P&L, balance sheet with accumulated earnings, current dues, party ledger API, ledger CSV | Ageing as of historical dates; opening bill ageing; dedicated cash-flow, GST and statutory reports |
| GST | Basic treatment/rates, registration controls, consistent printing | e-invoice/e-way bill, filing exports, reverse charge, cess, tax-inclusive prices, purchase ITC restrictions, complete mandatory invoice fields |
| Imports | XLSX/CSV preview and atomic apply, master/document templates, draft documents | Flexible column mapping UI; opening unpaid bills with correct historical cost/ageing; import idempotency tokens |
| Printing | A4 and configurable 48–110 mm browser print, payslips | Real printers/driver testing, silent ESC-POS, complete localisation and GST invoice acceptance |
| Three languages | Unicode data and a language selector with core navigation translations | Full English/Malayalam/Hindi interface and invoice translations; embedded print fonts |
| PWA | Manifest/service worker, online-only behaviour | Device installation testing, PNG icons and install UX compatibility verification |
| Load target | Separate deployments, indexed core tables, paginated transactions | 100-concurrent-user load tests and measured hosting sizing |
| Test evidence | JS syntax check; static package checks; meaningful PHP tests included | PHP execution, dependency install, browser UI tests, MySQL, production deployment |

## Known design limits requiring further work before real use

- Backdated stock postings do not recompute historical moving-average valuation. Operational postings should be chronological until replay/period-costing support is implemented.
- Negative-stock settlement is source code only and requires accountant-reviewed edge-case tests (partial cover, multiple receipts, returns and adjustments). Original provisional issue records remain marked; their returns/reversals are conservatively blocked.
- Plain purchase tax treatment currently follows the business registration switch, so a nonregistered business cannot yet record supplier GST as a separate nonrecoverable cost component.
- Manufacturing and restaurant screens are not complete substitutes for specialised manufacturing/restaurant products.
- Payroll uses calendar-day proration and requires attendance for every calendar day. Midmonth joining/leaving and split-month rule changes are not supported.
- Some configuration/edit flows are API-only. All API requests still enforce authentication, permissions and CSRF.
- The main master list loads at most 10,000 records; server-side product search must replace it for larger catalogues.
- An empty/incorrect account selection in a form is rejected server-side; acceptance testing must improve field-level guidance.

This is a substantial starting implementation with an explicit completion backlog, not the complete production application discussed in the requirements.
