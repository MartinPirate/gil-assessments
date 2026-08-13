<?php

use Knuckles\Scribe\Config\AuthIn;
use Knuckles\Scribe\Config\Defaults;
use Knuckles\Scribe\Extracting\Strategies;

use function Knuckles\Scribe\Config\configureStrategy;
use function Knuckles\Scribe\Config\removeStrategies;

// Only the most common configs are shown. See the https://scribe.knuckles.wtf/laravel/reference/config for all.

return [
    // The HTML <title> for the generated documentation.
    'title' => 'GIL — M-Pesa C2B Callback API',

    // A short description of your API. Will be included in the docs webpage, Postman collection and OpenAPI spec.
    'description' => 'REST endpoints that receive Safaricom M-Pesa C2B transaction callbacks, parse every field into its own database column, and confirm capture in a structured JSON response.',

    // Text to place in the "Introduction" section, right after the `description`. Markdown and HTML are supported.
    // Kept flush-left: PHP strips only the closing marker's indentation, so any
    // extra leading spaces survive into the Markdown and become a code block.
    'intro_text' => <<<'INTRO'
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

INTRO,

    // The base URL displayed in the docs.
    // If you're using `laravel` type, you can set this to a dynamic string, like '{{ config("app.tenant_url") }}' to get a dynamic base URL.
    'base_url' => config('app.url'),

    // Routes to include in the docs
    'routes' => [
        [
            'match' => [
                // Only the M-Pesa callbacks are a public API; the admin panel is not.
                'prefixes' => ['api/mpesa/*'],

                // Match only routes whose domains match this pattern (use * as a wildcard to match any characters). Example: 'api.*'.
                'domains' => ['*'],
            ],

            // Include these routes even if they did not match the rules above.
            'include' => [
                // 'users.index', 'POST /new', '/auth/*'
            ],

            // Exclude these routes even if they matched the rules above.
            'exclude' => [
                // 'GET /health', 'admin.*'
            ],
        ],
    ],

    // The type of documentation output to generate.
    // - "static" will generate a static HTMl page in the /public/docs folder,
    // - "laravel" will generate the documentation as a Blade view, so you can add routing and authentication.
    // - "external_static" and "external_laravel" do the same as above, but pass the OpenAPI spec as a URL to an external UI template
    'type' => 'laravel',

    // See https://scribe.knuckles.wtf/laravel/reference/config#theme for supported options
    'theme' => 'default',

    'static' => [
        // HTML documentation, assets and Postman collection will be generated to this folder.
        // Source Markdown will still be in resources/docs.
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        // Whether to automatically create a docs route for you to view your generated docs. You can still set up routing manually.
        'add_routes' => true,

        // URL path to use for the docs endpoint (if `add_routes` is true).
        // By default, `/docs` opens the HTML page, `/docs.postman` opens the Postman collection, and `/docs.openapi` the OpenAPI spec.
        'docs_url' => '/docs',

        // Directory within `public` in which to store CSS and JS assets.
        // By default, assets are stored in `public/vendor/scribe`.
        // If set, assets will be stored in `public/{{assets_directory}}`
        'assets_directory' => null,

        // Middleware to attach to the docs endpoint (if `add_routes` is true).
        'middleware' => [],
    ],

    'external' => [
        'html_attributes' => [],
    ],

    'try_it_out' => [
        // Add a Try It Out button to your endpoints so consumers can test endpoints right from their browser.
        // Don't forget to enable CORS headers for your endpoints.
        'enabled' => true,

        // The base URL to use in the API tester. Leave as null to be the same as the displayed URL (`scribe.base_url`).
        'base_url' => null,

        // [Laravel Sanctum] Fetch a CSRF token before each request, and add it as an X-XSRF-TOKEN header.
        'use_csrf' => false,

        // The URL to fetch the CSRF token from (if `use_csrf` is true).
        'csrf_url' => '/sanctum/csrf-cookie',
    ],

    // How is your API authenticated? This information will be used in the displayed docs, generated examples and response calls.
    'auth' => [
        // Set this to true if ANY endpoints in your API use authentication.
        'enabled' => false,

        // Set this to true if your API should be authenticated by default. If so, you must also set `enabled` (above) to true.
        // You can then use @unauthenticated or @authenticated on individual endpoints to change their status from the default.
        'default' => false,

        // Where is the auth value meant to be sent in a request?
        'in' => AuthIn::BEARER->value,

        // The name of the auth parameter (e.g. token, key, apiKey) or header (e.g. Authorization, Api-Key).
        'name' => 'key',

        // The value of the parameter to be used by Scribe to authenticate response calls.
        // This will NOT be included in the generated documentation. If empty, Scribe will use a random value.
        'use_value' => env('SCRIBE_AUTH_KEY'),

        // Placeholder your users will see for the auth parameter in the example requests.
        // Set this to null if you want Scribe to use a random value as placeholder instead.
        'placeholder' => '{YOUR_AUTH_KEY}',

        // Any extra authentication-related info for your users. Markdown and HTML are supported.
        'extra_info' => 'You can retrieve your token by visiting your dashboard and clicking <b>Generate API token</b>.',
    ],

    // Example requests for each endpoint will be shown in each of these languages.
    // Supported options are: bash, javascript, php, python
    // To add a language of your own, see https://scribe.knuckles.wtf/laravel/advanced/example-requests
    // Note: does not work for `external` docs types
    'example_languages' => [
        'bash',
        'javascript',
    ],

    // Generate a Postman collection (v2.1.0) in addition to HTML docs.
    // For 'static' docs, the collection will be generated to public/docs/collection.json.
    // For 'laravel' docs, it will be generated to storage/app/scribe/collection.json.
    // Setting `laravel.add_routes` to true (above) will also add a route for the collection.
    'postman' => [
        'enabled' => true,

        'overrides' => [
            // 'info.version' => '2.0.0',
        ],
    ],

    // Generate an OpenAPI spec in addition to docs webpage.
    // For 'static' docs, the collection will be generated to public/docs/openapi.yaml.
    // For 'laravel' docs, it will be generated to storage/app/scribe/openapi.yaml.
    // Setting `laravel.add_routes` to true (above) will also add a route for the spec.
    'openapi' => [
        'enabled' => true,

        // The OpenAPI spec version to generate. Supported versions: '3.0.3', '3.1.0'.
        // OpenAPI 3.1 is more compatible with JSON Schema and is becoming the dominant version.
        // See https://spec.openapis.org/oas/v3.1.0 for details on 3.1 changes.
        'version' => '3.0.3',

        'overrides' => [
            // 'info.version' => '2.0.0',
        ],

        // Additional generators to use when generating the OpenAPI spec.
        // Should extend `Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator`.
        'generators' => [],
    ],

    'groups' => [
        // Endpoints which don't have a @group will be placed in this default group.
        'default' => 'Endpoints',

        // By default, Scribe will sort groups alphabetically, and endpoints in the order their routes are defined.
        // You can override this by listing the groups, subgroups and endpoints here in the order you want them.
        // See https://scribe.knuckles.wtf/blog/laravel-v4#easier-sorting and https://scribe.knuckles.wtf/laravel/reference/config#order for details
        // Note: does not work for `external` docs types
        'order' => [],
    ],

    // Custom logo path. This will be used as the value of the src attribute for the <img> tag,
    // so make sure it points to an accessible URL or path. Set to false to not use a logo.
    // For example, if your logo is in public/img:
    // - 'logo' => '../img/logo.png' // for `static` type (output folder is public/docs)
    // - 'logo' => 'img/logo.png' // for `laravel` type
    'logo' => false,

    // Customize the "Last updated" value displayed in the docs by specifying tokens and formats.
    // Examples:
    // - {date:F j Y} => March 28, 2022
    // - {git:short} => Short hash of the last Git commit
    // Available tokens are `{date:<format>}` and `{git:<format>}`.
    // The format you pass to `date` will be passed to PHP's `date()` function.
    // The format you pass to `git` can be either "short" or "long".
    // Note: does not work for `external` docs types
    'last_updated' => 'Last updated: {date:F j, Y}',

    'examples' => [
        // Set this to any number to generate the same example values for parameters on each run,
        'faker_seed' => 1234,

        // With API resources and transformers, Scribe tries to generate example models to use in your API responses.
        // By default, Scribe will try the model's factory, and if that fails, try fetching the first from the database.
        // You can reorder or remove strategies here.
        'models_source' => ['factoryCreate', 'factoryMake', 'databaseFirst'],
    ],

    // The strategies Scribe will use to extract information about your routes at each stage.
    // Use configureStrategy() to specify settings for a strategy in the list.
    // Use removeStrategies() to remove an included strategy.
    'strategies' => [
        'metadata' => [
            ...Defaults::METADATA_STRATEGIES,
        ],
        'headers' => [
            ...Defaults::HEADERS_STRATEGIES,
            Strategies\StaticData::withSettings(data: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
        ],
        'urlParameters' => [
            ...Defaults::URL_PARAMETERS_STRATEGIES,
        ],
        'queryParameters' => [
            ...Defaults::QUERY_PARAMETERS_STRATEGIES,
        ],
        'bodyParameters' => [
            ...Defaults::BODY_PARAMETERS_STRATEGIES,
        ],
        'responses' => configureStrategy(
            Defaults::RESPONSES_STRATEGIES,
            Strategies\Responses\ResponseCalls::withSettings(
                only: ['GET *'],
                // Recommended: disable debug mode in response calls to avoid error stack traces in responses
                config: [
                    'app.debug' => false,
                ]
            )
        ),
        'responseFields' => [
            ...Defaults::RESPONSE_FIELDS_STRATEGIES,
        ],
    ],

    // For response calls, API resource responses and transformer responses,
    // Scribe will try to start database transactions, so no changes are persisted to your database.
    // Tell Scribe which connections should be transacted here. If you only use one db connection, you can leave this as is.
    'database_connections_to_transact' => [config('database.default')],

    'fractal' => [
        // If you are using a custom serializer with league/fractal, you can specify it here.
        'serializer' => null,
    ],
];
