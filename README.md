# GIL — Full Stack System Developer Technical Assessment

A Laravel 12 + Filament 4 business application backed by **Microsoft SQL Server**,
covering all three assessment tasks as one connected system rather than three
disconnected demos.

The three tasks are wired together the way they would be in production: an
invoice raised on the A/R screen routes itself for approval when it breaches the
threshold, and an M-Pesa receipt arriving on the C2B endpoint settles that same
invoice and closes it.

| Task | Scope | Where |
| --- | --- | --- |
| 1 | A/R Invoice document (SAP B1 parity, Choose From List, VAT, approval label, validations) | `Sales → A/R Invoice` |
| 2 | Vehicle gate in / gate out, responsive, login session capture | `Gate Operations` |
| 3 | M-Pesa C2B callback API **+ reconciliation against invoices** | `POST /api/mpesa/c2b/*`, `Payments` |

Built around those, because a system that only does the three tasks is a demo
rather than something anyone could run:

| Also | Where |
| --- | --- |
| Approval workflow with per-approver limits | `Sales → Approvals` |
| Routes, trips and a driver self-service portal | `Operations`, `Driver → My Trips` |
| Audit log — every change, with who and from where | `Administration → Audit Log` |
| User administration with role and driver linking | `Administration → Users` |
| Custom error handling, three response shapes by audience | throughout |

---

## 1. Requirements

- PHP 8.2+ with `pdo_sqlsrv` and `sqlsrv`
- Composer, Node 18+
- SQL Server 2019+ (or Azure SQL Edge)

### Installing the SQL Server driver

macOS (Apple Silicon):

```bash
brew tap microsoft/mssql-release https://github.com/Microsoft/homebrew-mssql-release
brew trust microsoft/mssql-release
HOMEBREW_ACCEPT_EULA=Y brew install msodbcsql18 mssql-tools18 unixodbc

# PHP 8.2 needs the pinned 5.12 release; 5.13+ requires PHP 8.3.
CPPFLAGS="-I/opt/homebrew/include" LDFLAGS="-L/opt/homebrew/lib" \
  pecl install sqlsrv-5.12.0 pdo_sqlsrv-5.12.0

php -m | grep sqlsrv   # expect: pdo_sqlsrv, sqlsrv
```

### A SQL Server to point at

`mcr.microsoft.com/mssql/server` has no ARM build, so on Apple Silicon use
Azure SQL Edge:

```bash
docker run -d --name gil-mssql \
  -e "ACCEPT_EULA=1" -e "MSSQL_SA_PASSWORD=YourStrong!Passw0rd" \
  -p 1433:1433 mcr.microsoft.com/azure-sql-edge:latest

sqlcmd -S localhost,1433 -U sa -P 'YourStrong!Passw0rd' -C \
  -Q "CREATE DATABASE gil_assessment; CREATE DATABASE gil_assessment_test;"
```

> Give Docker at least 4 GB. SQL Server is memory hungry, and on a constrained
> Docker VM the driver times out during the pre-login handshake.

---

## 2. Setup

```bash
composer install && npm install
cp .env.example .env
php artisan key:generate
# set the DB_* values in .env, then:
php artisan migrate --seed
npm run build
php artisan serve
```

Open <http://127.0.0.1:8000/admin>. All accounts use the password `password`:

| Email | Role | Sees |
| --- | --- | --- |
| `admin@gil.test` | Administrator | everything, including master data |
| `sales@gil.test` | Sales | A/R Invoice, Invoice Register, payments |
| `approver@gil.test` | Approver | the approval queue (limit: 50,000) |
| `gate@gil.test` | Gate Officer | Gate In / Out / Log, routes and trips |
| `driver@gil.test` | Driver | **only** their own trips — nothing else |

Signing in as each shows a different navigation and a different dashboard —
the role gating is real, not cosmetic.

---

## 3. Task 1 — A/R Invoice

`Sales → A/R Invoice`. Built to the supplied screenshot: navy document title
bar, captions left of hairline field boxes, the tabbed content area, a dense
14-column line grid that scrolls horizontally, and the totals block bottom-right.

**Header** — Customer Code and Customer Name each have a *Choose From List*
button (the orange drill arrow) opening a searchable, sortable, paginated
record list, **and** work as type-ahead fields. The Name list leads with the
name, as specified. Selecting in either fills both, plus Contact Person, BP
Currency and KRA PIN. Series + auto-incremented No., Status, and Posting /
Value / Document dates.

**Approval label** — hidden until the document total passes 10,000, then shows
`Invoice will go for approval – Amount: {amount}`.

**Tabs** — Contents (the grid), Logistics, Accounting, TIMS / eTIMS.

**Line grid** — Type, Item No. (CFL, type-ahead, or free text), Description,
Quantity, Whse, Qty in Whse, UoM, Unit Price, Discount %, Price after Discount,
VAT Code, Gross Price after Disc., Total (LC), Gross Total (LC). Everything is
carried to **3 decimal places**.

**Footer** — Sales Employee (CFL), Owner, mandatory Remarks, and the full totals
block: Total Before Discount, Discount, Total After Discount, Down Payment,
Freight, Rounding, Tax, Total, Applied Amount, Balance Due.

**Buttons** — Add & New, Add Draft & New, Cancel.

**Validations** — discount above 50 rejected on both line and document; empty
Remarks rejected.

### VAT

Each line carries a VAT code (O0 zero-rated by default, V16 standard, V8, E).
Tax is computed on the line total, and a document-level discount **scales the
tax down with the taxable base** — otherwise a discounted invoice would
over-declare VAT to KRA.

---

## 4. Approval workflow

The label alone is half a requirement; a document that announces it needs
approval has to actually go somewhere.

An invoice over the threshold saves as `Pending Approval` **and** opens a queue
entry in the same transaction — a document is never committed as pending
without a matching request. `Sales → Approvals` (approver/admin only) shows the
queue with Approve and Reject actions; rejection requires a reason.

- Approving moves the invoice to `Open` (or `Closed` if already settled).
- Rejecting marks it `Rejected` and records who, when and why.
- A request cannot be decided twice — the row is locked and re-checked.
- Approvers have a per-user `approval_limit`; a junior approver cannot wave
  through a document above their authority.
- Drafts are never routed for approval and carry no balance.

---

## 5. Task 2 — Vehicle Gate Operations

Single column with larger touch targets on a phone, two columns from the medium
breakpoint up.

- **Gate In** — searchable Vehicle and Driver lists; Driver ID and Phone
  auto-populate. Time in and the recording user are captured automatically.
  Selecting a vehicle already inside warns immediately.
- **Gate Out** — lists **only vehicles with an open gate-in record**, not the
  whole fleet. Driver details, time in and time on site are read back from that
  record. Time out and the recording user are captured automatically.
- **Gate Log** — the audit trail, with a navigation badge showing how many
  vehicles are on site.

`GateService` owns the rules so both screens enforce the same invariant — a
vehicle can only be inside once — under a row lock, so two officers admitting
the same truck simultaneously cannot both succeed.

**Login sessions** — `login_sessions` records who logged in, when, from which IP
and user agent, and when they logged out. Laravel's own `sessions` table is a
garbage-collected cache; this is the durable trail the brief asks for.

---

## 6. Task 3 — M-Pesa C2B API and reconciliation

### Which "C2B" this is

Two different Safaricom products get called C2B, and they post completely
different payloads:

| | **C2B Register URL** (implemented) | **STK Push / Lipa na M-Pesa Online** |
| --- | --- | --- |
| Shape | flat | nested under `Body.stkCallback` |
| Amount | `TransAmount` | `Amount`, inside `CallbackMetadata.Item[]` |
| Receipt | `TransID` | `MpesaReceiptNumber` |
| Payer | `MSISDN`, `FirstName`, `MiddleName`, `LastName` | `PhoneNumber` only |
| Reference | `BillRefNumber` | none (rides on AccountReference at initiation) |

The brief names `TransactionId` and **`TransAmount`** as example fields.
`TransAmount` exists only in the Register-URL payload — STK has no such field —
so that is what this implements.

**Both shapes are accepted anyway.** Nothing stops an integrator pointing an STK
callback at a C2B URL, and parsing only the flat shape would store a row of
nulls and silently lose the payment. `CallbackNormaliser` flattens a nested STK
body into the same string columns (`MpesaReceiptNumber` → `TransID`, `Amount` →
`TransAmount`, `PhoneNumber` → `MSISDN`, …), while `raw_payload` keeps the body
exactly as Safaricom sent it. A cancelled or timed-out push (`ResultCode` 1032 /
1037) is recorded but marked `N/A` and never allocated — it is an attempt, not
money.

### Endpoints

```
POST /api/mpesa/c2b/validation
POST /api/mpesa/c2b/confirmation
```

Every documented C2B field is parsed into **its own string column** —
`TransactionType`, `TransID`, `TransTime`, `TransAmount`, `BusinessShortCode`,
`BillRefNumber`, `InvoiceNumber`, `OrgAccountBalance`, `ThirdPartyTransID`,
`MSISDN`, `FirstName`, `MiddleName`, `LastName`.

```bash
curl -X POST http://127.0.0.1:8000/api/mpesa/c2b/confirmation \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"TransactionType":"Pay Bill","TransID":"RKTQDM7W6S","TransTime":"20260810180000",
       "TransAmount":"1500.00","BusinessShortCode":"600984","BillRefNumber":"IN-2",
       "MSISDN":"254708374149","FirstName":"John","LastName":"Mwangi"}'
```

Returns `ResultCode: 0` plus a structured confirmation of what was captured,
which fields were missing, and which were unrecognised.

### Registering the URLs with Safaricom

Endpoints receive nothing until Safaricom has been told they exist. That is an
authenticated Daraja call, so the app ships with the OAuth client and a command:

```bash
php artisan mpesa:register-urls --status   # show config, register nothing
php artisan mpesa:register-urls            # confirms, then registers
```

It POSTs `ShortCode`, `ResponseType`, `ConfirmationURL` and `ValidationURL` to
`/mpesa/c2b/v1/registerurl`. Requires `MPESA_CONSUMER_KEY`,
`MPESA_CONSUMER_SECRET` and `MPESA_SHORTCODE`. The command refuses to register a
`localhost` URL — Safaricom accepts such a registration and then never delivers,
which presents as "the callback never fires".

`ResponseType` defaults to `Completed`: if the Validation URL is unreachable the
payment is accepted anyway, so an outage never turns paying customers away.

### API documentation

Browsable reference, generated from the code itself:

| URL | What it is |
|---|---|
| `/docs` | HTML reference with a **Try it out** console |
| `/docs.openapi` | OpenAPI 3.0.3 spec (YAML) — feed this to Stoplight Elements, Swagger UI, Redoc, Insomnia |
| `/docs.postman` | Postman collection |

```bash
php artisan scribe:generate          # rebuild after changing endpoints
php artisan scribe:generate --force  # ignore the extraction cache
```

Field descriptions live in `MpesaC2BCallbackRequest::bodyParameters()`, right
beside the validation rules they describe, so the published reference cannot
drift from what the code actually accepts. Response examples are declared on the
controller, and Scribe is configured not to make live response calls — generating
docs never writes a payment to the database.

The docs cover, per the brief: every request field with type and example, the
field → column mapping proving each is stored separately, the success response
shape, and the 403 / 422 / 500 failure shapes with what Safaricom does with each.

### Reconciliation

Without this the endpoint is a write-only log: money arrives and nobody knows
what it paid for. The customer types the invoice number into the M-Pesa account
field, so `BillRefNumber` is the link.

- `IN-2`, `INV-2`, `IN2` and `2` all resolve to the same document.
- An **ambiguous** bare number (same number in two series) is deliberately *not*
  guessed — paying the wrong invoice is worse than queuing it for a human.
- An **overpayment** is applied only up to the balance; the excess stays on the
  receipt as unallocated rather than pushing the invoice negative.
- An **unmatched** receipt is queued, and `Payments → M-Pesa Transactions` offers
  a manual Allocate action recording who allocated it.
- Paying an invoice awaiting approval does **not** promote it past that step.

Balances are recomputed from the allocation rows themselves rather than
incremented, so the figures cannot drift from the records that justify them.

### On always answering `ResultCode: 0`

A common pattern (including in the mjengo-tracker reference) is to swallow every
error and always reply `{"ResultCode":0,"ResultDesc":"Accepted"}`, so Safaricom
never retries. That trades a retry for silent data loss: if the write failed, the
payment is simply gone from your system.

This implementation persists first and only returns a non-zero code when
persistence itself failed, so Safaricom retries — and because the write is
keyed on `(trans_id, callback_type)` with a unique index, a retry updates the
existing row instead of paying twice. At-least-once delivery plus idempotency
beats at-most-once.

## 7. Operations — routes, trips and the driver portal

A gate movement says a vehicle left; a trip says where it was going, who was
driving and whether it arrived.

- **Routes** (`Operations → Routes`) — origin, destination, distance, estimated
  hours.
- **Trips** (`Operations → Trips`) — assign a vehicle and driver to a route.
  Status runs `Scheduled → In Transit → Completed`, and the transitions are
  guarded: a completed trip cannot go back, and a vehicle or driver already on
  an open trip cannot be double-booked (checked under a row lock).
- **Driver portal** (`Driver → My Trips`) — a driver signs in and sees *only*
  their own trips, as cards sized for a phone in a cab, with a single action
  showing whichever transition is legal right now.

Scoping is applied in the query (`Trip::forDriver()`), not in the view, and the
trip id that arrives from the browser is re-checked against the signed-in
driver before any action runs. A login with no linked driver record matches
**nothing** rather than everything.

---

## 8. Audit log

Approvals, payments and gate movements are financially or physically
consequential, so "the system says so" is not an acceptable answer.

The `Auditable` trait records every create, update and delete on invoices, gate
logs, approvals, payment allocations, trips, routes, users and all master data:
who did it, when, from which IP and URL, and a field-level before/after diff.

- Only the attributes that **actually changed** are stored, never the whole row.
- No-op saves and `touch()` produce no entry — a trail full of
  "updated_at changed" hides the entries that matter.
- **Secrets are never recorded.** Two independent guards: an explicit exclude
  list, and a regex rejecting any key matching `password|token|secret|api_key`,
  so a column added later cannot leak by omission.
- Admin-only, and immutable through the panel — a log you can edit is not a log.

---

## 9. Users and roles

`Administration → Users` (admin only). Create, edit and deactivate accounts,
set the role and approval limit, and link a driver login to its driver record.

| Role | Can |
| --- | --- |
| Administrator | everything, including master data and the audit log |
| Sales | raise invoices, view the register and payments |
| Approver | decide approval requests, up to their own limit |
| Gate Officer | gate in/out, gate log, routes and trips |
| Driver | their own trips only |

Two lockout guards: an admin cannot change **their own** role or deactivate
their own account, and cannot delete themselves. Deactivated users keep their
history but cannot sign in. Passwords are hashed on the way in, and leaving the
field blank on edit keeps the existing one rather than blanking it.

---

## 10. Error handling

Three audiences, three response shapes:

| Caller | Gets |
| --- | --- |
| Safaricom (`/api/mpesa/*`) | `{"ResultCode": 1, "ResultDesc": "...", "reference": "..."}` — an HTML error page would be parsed as a failure and retried forever |
| Other API clients | `{"success": false, "message": "...", "reference": "..."}` |
| People in the panel | A branded page with a quotable reference id |

A 5xx message is deliberately generic — the real exception routinely names
tables, columns and file paths. Every incident is logged with a short reference
(`1ENYC2JU`), the URL, method, IP and user id, so a support report ties to a
specific stack trace. Expected exceptions (validation, auth, 404) are not
reported as incidents; only 5xx is ours.

---

## 11. Tests

```bash
php artisan test              # 182 tests
php artisan test --coverage   # with a coverage summary (needs PCOV or Xdebug)
```

**182 tests, 687 assertions.** They run against a **real SQL Server** database
(`gil_assessment_test`), not SQLite, so row locking, IDENTITY columns and
nvarchar limits behave as they do in production. Credentials live in
`.env.testing` (gitignored).

| Suite | Covers |
| --- | --- |
| `Unit/InvoiceCalculatorTest` | discount maths, rounding, blank rows, out-of-range discounts, per-field display precision |
| `Unit/InvoiceTaxAndTotalsTest` | VAT on lines, discount scaling tax, freight, down payments, rounding, non-negative balance |
| `Unit/CallbackNormaliserTest` | both M-Pesa payload shapes, failed pushes, malformed metadata |
| `Unit/UserRoleTest` | the full role × capability matrix |
| `Feature/ArInvoiceScreenTest` | auto-population, sequential numbering, approval threshold, both discount validations, mandatory remarks, server-side recalculation, CFL state-path safety |
| `Feature/ApprovalWorkflowTest` | routing, drafts bypassing approval, approve/reject, double-decision guard, per-user limits |
| `Feature/PaymentReconciliationTest` | auto-matching, reference formats, ambiguity refusal, overpayment, unmatched queue, retries, manual allocation |
| `Feature/MpesaC2BApiTest` · `MpesaStkCallbackTest` · `MpesaCaptureDecouplingTest` · `DarajaClientTest` | field-by-field persistence, idempotency, STK flattening, capture/reconciliation decoupling, OAuth + RegisterURL |
| `Feature/GateOperationsTest` | auto-captured time/user, driver auto-population, double gate-in, gate-out filtering, login/logout sessions |
| `Feature/DriverPortalTest` | **driver isolation** — own trips only, foreign trip ids refused, unlinked accounts see nothing, trip lifecycle, double-booking |
| `Feature/AuditLogTest` | actor capture, changed-fields only, no-op suppression, **secrets never recorded**, immutability |
| `Feature/UserManagementTest` | admin-only access, password hashing, blank-password edits, self-lockout guards, driver linking/relinking |
| `Feature/ErrorHandlingTest` | status mapping, **no leakage of SQL or secrets in 5xx**, ResultCode shape on M-Pesa routes |
| `Feature/RoleAccessTest` · `ChooseFromListRegistryTest` · `DocumentNumberServiceTest` | role matrix, picker whitelist, numbering concurrency |

### Coverage

PCOV is used (`pecl install pcov`). `php artisan test --coverage` reports **61% overall**, with an HTML report written to `storage/coverage/html`.

That headline number is held down by Filament resource scaffolding (tables,
forms, list pages) sitting at 0% — declarative configuration with no branching
of its own. The business logic is where it should be:

| 100% | 87–98% |
| --- | --- |
| `InvoiceCalculator`, `GateService`, `InvoiceWriter`, `CallbackNormaliser`, `ChooseFromListRegistry` | `Auditable` 97.7 · `ApprovalService` 97.9 · `MpesaC2BService` 97.8 · `DarajaClient` 96.7 · `PaymentAllocationService` 89.9 · `TripService` 87.0 |

## 12. Engineering notes

- **Concurrency.** Document numbers come from a counter row locked inside the
  same transaction that inserts the document — `MAX(doc_num) + 1` would hand two
  simultaneous saves the same number. The service refuses to run outside a
  transaction rather than silently issuing duplicates. Gate movements, approval
  decisions and payment allocations are all locked the same way.
- **Numbering is per series.** `IN-5` and `CR-5` are different documents; the
  unique index is on `(series, doc_num)`.
- **Totals and VAT are recomputed server-side** on save. Posted values are
  display state, so tampering with them cannot change what is stored — there are
  tests for both.
- **One calculator.** `InvoiceCalculator` is the single source of truth shared by
  the live form and the save path, so the two cannot drift.
- **Snapshotting.** Customer code/name, contact, PIN and employee name are copied
  onto the invoice; later master-data edits cannot rewrite posted history.
- **Numbering is per series.** `IN-5` and `CR-5` are different documents, so the
  unique index is on `(series, doc_num)` — a bug the reconciliation tests caught.
- **Capture is decoupled from reconciliation.** An M-Pesa confirmation fires
  `C2bConfirmationReceived`; a listener matches it to an invoice. If matching
  fails the receipt is still recorded and queued, because the money has already
  moved. There is a test that makes the allocator throw and asserts the receipt
  survives.
- **Money is stored wider than it is displayed.** 4 d.p. for prices, 6 for
  discounts, shown at the document's precision — a rounded presentation never
  becomes a rounded stored value.
- **SQL Server specifics** that bit during the build and are worth knowing:
  multiple NULLs count as duplicates in a `UNIQUE` index (the driver→user link
  needs a *filtered* index), `UPDATE` rejects `ORDER BY`, and multiple cascade
  paths into one table are refused.
- **Choose From List is one shared component**, re-targeted at whichever field
  opened it via a server-side registry. The browser sends a registry key, never a
  model class or query, and state paths are validated (including against
  `data_set()` wildcards) before anything is written.

## 13. Assumptions and known limits

- **Decimal places follow the screenshot, not the brief.** The brief says "up to
  3 decimal places"; the sample document shows 4 (unit price), 6 (discount %)
  and 2 (totals). The screenshot won, on the basis that it is the more specific
  artefact — this is flagged here because it is a deliberate departure.
- **Logistics / Accounting / TIMS / ETIMS tabs are structural.** The fields are
  laid out and read-only; actual eTIMS transmission is a KRA integration well
  outside this assessment.
- **Copy To is disabled**, with a tooltip explaining why: there is no downstream
  document type (credit note, delivery) yet. A button that looks live and does
  nothing is worse than one that says what it is waiting for.
- **Posted invoices are not editable.** The brief asks for entry; making posted
  documents editable without a reversal trail would be worse than not offering
  it. Drafts exist for work in progress.
- **Roles are a fixed enum, not an ACL table.** Five clearly separated jobs with
  nothing to configure; a permissions table would be machinery without a use.
- **The window minimise/maximise/close chrome is decorative.** It matches the
  SAP client visually but is marked `aria-hidden`, since those controls have no
  meaning in a browser tab.
