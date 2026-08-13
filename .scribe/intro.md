# Introduction

REST endpoints that receive Safaricom M-Pesa C2B transaction callbacks, parse every field into its own database column, and confirm capture in a structured JSON response.

<aside>
    <strong>Base URL</strong>: <code>http://localhost</code>
</aside>

These are the two URLs registered with Safaricom Daraja for a paybill or till
short code. Safaricom is the only intended caller.

## The two endpoints

| URL | When Safaricom calls it | What it means |
|---|---|---|
| `POST /api/mpesa/c2b/validation` | *Before* completing a payment | "May this payment proceed?" |
| `POST /api/mpesa/c2b/confirmation` | *After* the payment completes | "This money has been received." |

Both parse and store the payload. Only a **confirmation** is treated as money
received and reconciled against an invoice.

## Authentication

**None.** Safaricom sends no credentials, so these endpoints cannot require any.
They are protected instead by:

- an **IP allow-list** (`MPESA_ALLOWED_IPS`) restricting callers to Safaricom's
  published callback addresses — empty in sandbox/local, set in production;
- **rate limiting** keyed on short code + MSISDN rather than IP, because every
  callback arrives from the same small pool of addresses.

## Idempotency

Safaricom retries a callback until it receives a success response, so the same
`TransID` will legitimately arrive more than once. Writes are keyed on
`(TransID, callback type)` behind a unique index: **a retry updates the existing
record rather than recording a second payment.**

## Registering these URLs

The endpoints receive nothing until Safaricom has been told they exist:

```bash
php artisan mpesa:register-urls --status   # show configuration, send nothing
php artisan mpesa:register-urls            # confirm, then register
```
## How the payload is stored

The requirement is that every field is extracted into its **own string field**.
Each callback key maps one-to-one onto a column on `mpesa_transactions`:

| Callback field | Column | Type | Notes |
|---|---|---|---|
| `TransactionType` | `transaction_type` | nvarchar(100) | |
| `TransID` | `trans_id` | nvarchar(64) | **unique** with `callback_type` — the idempotency key |
| `TransTime` | `trans_time` | nvarchar(32) | kept as `yyyyMMddHHmmss`; exposed as a date via an accessor |
| `TransAmount` | `trans_amount` | nvarchar(32) | **never cast to float** — no precision lost on money |
| `BusinessShortCode` | `business_short_code` | nvarchar(32) | |
| `BillRefNumber` | `bill_ref_number` | nvarchar(100) | indexed; matches the receipt to an invoice |
| `InvoiceNumber` | `invoice_number` | nvarchar(100) | |
| `OrgAccountBalance` | `org_account_balance` | nvarchar(32) | |
| `ThirdPartyTransID` | `third_party_trans_id` | nvarchar(100) | |
| `MSISDN` | `msisdn` | nvarchar(32) | indexed |
| `FirstName` | `first_name` | nvarchar(100) | |
| `MiddleName` | `middle_name` | nvarchar(100) | |
| `LastName` | `last_name` | nvarchar(100) | |

Every record also stores:

| Column | Purpose |
|---|---|
| `raw_payload` | the body **exactly** as Safaricom sent it, so nothing added later is silently dropped |
| `callback_type` | `validation` or `confirmation` |
| `received_at` | when this system received it — distinct from `TransTime`, when the payment happened |
| `allocation_status` | `Pending`, `Matched`, `Partial`, `Unmatched` or `N/A` |
| `allocated_amount` | how much of the receipt has been applied to invoices |

Field matching is **case-insensitive**: sandbox and production payloads have
differed on casing (`MSISDN` vs `Msisdn`), and a mismatch would otherwise
silently store a null amount. Any field Safaricom sends that is not modelled is
reported back in `data.unmapped_fields` rather than discarded — the value is
still kept in `raw_payload`.

## Two payload shapes are accepted

"C2B" refers to two different Safaricom products, which post different bodies:

| | **C2B Register URL** (documented here) | **STK Push / Lipa na M-Pesa Online** |
|---|---|---|
| Shape | flat | nested under `Body.stkCallback` |
| Amount | `TransAmount` | `Amount`, inside `CallbackMetadata.Item[]` |
| Receipt | `TransID` | `MpesaReceiptNumber` |
| Payer | `MSISDN`, `FirstName`, ... | `PhoneNumber` only |

Nothing stops an integrator pointing an STK callback at these URLs, so a nested
body is flattened into the same columns rather than stored as a row of nulls.
A cancelled or timed-out push (`ResultCode` 1032 / 1037) is recorded but marked
`N/A` and never treated as money received.

## What happens after capture

A confirmation fires an internal `C2bConfirmationReceived` event, and a listener
matches the receipt to the invoice named in `BillRefNumber`. **Capture and
reconciliation are deliberately separate:** if matching fails, the receipt is
still recorded and queued for manual allocation, because the money has already
moved.


