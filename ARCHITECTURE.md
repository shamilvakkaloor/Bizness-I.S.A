# Business architecture and agreed scope

## Product and deployment

India-focused small/medium business operations: one legal business per installation, one primary admin, multiple staff accounts, branches and warehouses. Ten separate business deployments with a target of ten concurrent users each. PHP/Laravel, MySQL, Hostinger, responsive browser interface and online-only PWA. English/Malayalam/Hindi are the desired complete languages; current translation coverage is partial.

## Components and relationships

Browser → session-protected Laravel routes → validation and permissions → transactional application services → MySQL.

- Master data: business, branches, warehouses, parties, products/variants, selling prices, accounts and employees.
- Commercial documents: draft headers and lines, posting state, source line references, payment allocations and immutable posted snapshots.
- Stock: product/warehouse/lot balances, signed movement quantities in thousandths, values in paise, moving-average issues, provisional cost settlement.
- Accounting: balanced ledger entries per document, party and branch. Report balances derive from entries, not editable totals.
- Operations: manufacturing orders, restaurant order records, service jobs, project records, fixed assets and cheques.
- Payroll: daily attendance → draft payroll → payslips → posted journal. Administrator-entered contribution rules carry effective dates and retain calculation snapshots.
- Audit: actor, time, entity, before/after values, reason and branch. Password values are excluded. Application code exposes no audit mutation endpoint. A database administrator can still alter database records; this is not cryptographic tamper-proof storage.

## Core transaction flows

Direct purchase → stock + supplier payable. Optional purchase order → receipt (inventory / goods-received-not-invoiced) → bill (clear receipt accrual; price variance separately) → payment allocation.

Quotation / sales order → optional delivery (inventory / cost of goods sold) → invoice (customer receivable / sales / output tax). Direct invoice combines fulfilment and invoicing. Counter checkout posts invoice and full outstanding receipt atomically.

Sales and purchase returns require original invoice source lines and enforce cumulative returned quantities. Separate draft edits use versions; posted corrections use linked returns or reversal documents. A document with later stock activity cannot be reversed blindly. Financial periods may be locked through a date.

Receipts and payments may remain unallocated as advances. Allocations are bounded by invoice total less credit/return amounts and prior allocations. Unallocated amounts remain visible in the party ledger. Current invoice ageing excludes opening journals.

Cross-warehouse transfer moves the recorded carrying cost. Cross-branch transfers use interbranch clearing entries so each branch is balanced; entity total inventory is unchanged.

Opening stock posts Inventory / Opening equity. Opening balances use balanced journals, separately tagged by party where applicable.

## Concurrency and failure handling

Posting obtains a business row lock and a document lock, then applies all stock, ledgers and status changes in one database transaction. Already-posted documents return without repeating their side effects. Counter checkout checks remaining allocations while holding that lock. Exceptions roll back the complete operation. Deadlock retries are bounded.

The business-wide lock prioritises correctness in this initial design, at the cost of serializing postings. It does not establish a throughput guarantee. Draft creation lacks a client idempotency token, so duplicate drafts are possible after a timeout; posting each distinct draft remains a separate action requiring review.

## Stock policy

Negative stock is allowed with a warning for ordinary item sales. Such issues retain a provisional marker and outstanding uncovered quantity. Later receipts post a separate cost settlement. Serial-number issues require an available serial and do not permit inventing negative serial stock. Transfers and manufacturing component issues require sufficient stock to carry a known value. These are explicit departures from unrestricted negative-stock behaviour and must be resolved before final acceptance if every operation must permit negatives.

Batch and serial tracking use a lot key; each serial line has one base unit. Batch expiry is checked on issue. Variants are individual SKUs. Conversions exist in the API/schema but the current transaction UI operates in base units.

## GST policy

Registration mode (unregistered/regular/composition) is separate from transaction treatment (taxable/exempt/nil-rated/non-GST). Posted tax amounts and invoice snapshots agree. No print-only invented GST mode. This build implements basic percentage tax calculations and printing, not a complete GST compliance engine, return filing, e-invoice or e-way bill service. Unregistered supplier/customer/purchase tax edge cases require further implementation and review.

## Roles and branch access

Admin has all actions. Staff permissions include view, create, approve, cancel, export, costs, accounting, masters, price_override, payroll and audit. Branch lists restrict transaction access and report scopes. Master records are shared within one business; stock, accounting and operations carry branch identity. Costs and full accounting require additional permissions. Master edit audit records are retained; staff management is admin-only.

## Delivery gates

1. Dependency resolution and lockfile review.
2. PHP syntax, PHPUnit SQLite and MySQL tests.
3. Industry-flow, payroll and GST acceptance.
4. Complete translation and printer verification.
5. Multi-user concurrency, capacity, backup/restore and security tests.
6. Hostinger staging deployment and owner acceptance before production.
