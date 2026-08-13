<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>GIL — M-Pesa C2B Callback API</title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.style.css") }}" media="screen">
    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.print.css") }}" media="print">

    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.10/lodash.min.js"></script>

    <link rel="stylesheet"
          href="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/styles/obsidian.min.css">
    <script src="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/highlight.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jets/0.14.1/jets.min.js"></script>

    <style id="language-style">
        /* starts out as display none and is replaced with js later  */
                    body .content .bash-example code { display: none; }
                    body .content .javascript-example code { display: none; }
            </style>

    <script>
        var tryItOutBaseUrl = "http://localhost";
        var useCsrf = Boolean();
        var csrfUrl = "/sanctum/csrf-cookie";
    </script>
    <script src="{{ asset("/vendor/scribe/js/tryitout-5.11.0.js") }}"></script>

    <script src="{{ asset("/vendor/scribe/js/theme-default-5.11.0.js") }}"></script>

</head>

<body data-languages="[&quot;bash&quot;,&quot;javascript&quot;]">

<a href="#" id="nav-button">
    <span>
        MENU
        <img src="{{ asset("/vendor/scribe/images/navbar.png") }}" alt="navbar-image"/>
    </span>
</a>
<div class="tocify-wrapper">
    
            <div class="lang-selector">
                                            <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                            <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                    </div>
    
    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="Search">
    </div>

    <div id="toc">
                    <ul id="tocify-header-introduction" class="tocify-header">
                <li class="tocify-item level-1" data-unique="introduction">
                    <a href="#introduction">Introduction</a>
                </li>
                                    <ul id="tocify-subheader-introduction" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="the-two-endpoints">
                                <a href="#the-two-endpoints">The two endpoints</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="authentication">
                                <a href="#authentication">Authentication</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="idempotency">
                                <a href="#idempotency">Idempotency</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="registering-these-urls">
                                <a href="#registering-these-urls">Registering these URLs</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="how-the-payload-is-stored">
                                <a href="#how-the-payload-is-stored">How the payload is stored</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="two-payload-shapes-are-accepted">
                                <a href="#two-payload-shapes-are-accepted">Two payload shapes are accepted</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="what-happens-after-capture">
                                <a href="#what-happens-after-capture">What happens after capture</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-authenticating-requests" class="tocify-header">
                <li class="tocify-item level-1" data-unique="authenticating-requests">
                    <a href="#authenticating-requests">Authenticating requests</a>
                </li>
                            </ul>
                    <ul id="tocify-header-m-pesa-c2b-callbacks" class="tocify-header">
                <li class="tocify-item level-1" data-unique="m-pesa-c2b-callbacks">
                    <a href="#m-pesa-c2b-callbacks">M-Pesa C2B Callbacks</a>
                </li>
                                    <ul id="tocify-subheader-m-pesa-c2b-callbacks" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="m-pesa-c2b-callbacks-POSTapi-mpesa-c2b-validation">
                                <a href="#m-pesa-c2b-callbacks-POSTapi-mpesa-c2b-validation">Validation callback</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="m-pesa-c2b-callbacks-POSTapi-mpesa-c2b-confirmation">
                                <a href="#m-pesa-c2b-callbacks-POSTapi-mpesa-c2b-confirmation">Confirmation callback</a>
                            </li>
                                                                        </ul>
                            </ul>
            </div>

    <ul class="toc-footer" id="toc-footer">
                    <li style="padding-bottom: 5px;"><a href="{{ route("scribe.postman") }}">View Postman collection</a></li>
                            <li style="padding-bottom: 5px;"><a href="{{ route("scribe.openapi") }}">View OpenAPI spec</a></li>
                <li><a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a></li>
    </ul>

    <ul class="toc-footer" id="last-updated">
        <li>Last updated: August 10, 2026</li>
    </ul>
</div>

<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">
        <h1 id="introduction">Introduction</h1>
<p>REST endpoints that receive Safaricom M-Pesa C2B transaction callbacks, parse every field into its own database column, and confirm capture in a structured JSON response.</p>
<aside>
    <strong>Base URL</strong>: <code>http://localhost</code>
</aside>
<p>These are the two URLs registered with Safaricom Daraja for a paybill or till
short code. Safaricom is the only intended caller.</p>
<h2 id="the-two-endpoints">The two endpoints</h2>
<table>
<thead>
<tr>
<th>URL</th>
<th>When Safaricom calls it</th>
<th>What it means</th>
</tr>
</thead>
<tbody>
<tr>
<td><code>POST /api/mpesa/c2b/validation</code></td>
<td><em>Before</em> completing a payment</td>
<td>"May this payment proceed?"</td>
</tr>
<tr>
<td><code>POST /api/mpesa/c2b/confirmation</code></td>
<td><em>After</em> the payment completes</td>
<td>"This money has been received."</td>
</tr>
</tbody>
</table>
<p>Both parse and store the payload. Only a <strong>confirmation</strong> is treated as money
received and reconciled against an invoice.</p>
<h2 id="authentication">Authentication</h2>
<p><strong>None.</strong> Safaricom sends no credentials, so these endpoints cannot require any.
They are protected instead by:</p>
<ul>
<li>an <strong>IP allow-list</strong> (<code>MPESA_ALLOWED_IPS</code>) restricting callers to Safaricom's
published callback addresses — empty in sandbox/local, set in production;</li>
<li><strong>rate limiting</strong> keyed on short code + MSISDN rather than IP, because every
callback arrives from the same small pool of addresses.</li>
</ul>
<h2 id="idempotency">Idempotency</h2>
<p>Safaricom retries a callback until it receives a success response, so the same
<code>TransID</code> will legitimately arrive more than once. Writes are keyed on
<code>(TransID, callback type)</code> behind a unique index: <strong>a retry updates the existing
record rather than recording a second payment.</strong></p>
<h2 id="registering-these-urls">Registering these URLs</h2>
<p>The endpoints receive nothing until Safaricom has been told they exist:</p>
<pre><code class="language-bash">php artisan mpesa:register-urls --status   # show configuration, send nothing
php artisan mpesa:register-urls            # confirm, then register</code></pre>
<h2 id="how-the-payload-is-stored">How the payload is stored</h2>
<p>The requirement is that every field is extracted into its <strong>own string field</strong>.
Each callback key maps one-to-one onto a column on <code>mpesa_transactions</code>:</p>
<table>
<thead>
<tr>
<th>Callback field</th>
<th>Column</th>
<th>Type</th>
<th>Notes</th>
</tr>
</thead>
<tbody>
<tr>
<td><code>TransactionType</code></td>
<td><code>transaction_type</code></td>
<td>nvarchar(100)</td>
<td></td>
</tr>
<tr>
<td><code>TransID</code></td>
<td><code>trans_id</code></td>
<td>nvarchar(64)</td>
<td><strong>unique</strong> with <code>callback_type</code> — the idempotency key</td>
</tr>
<tr>
<td><code>TransTime</code></td>
<td><code>trans_time</code></td>
<td>nvarchar(32)</td>
<td>kept as <code>yyyyMMddHHmmss</code>; exposed as a date via an accessor</td>
</tr>
<tr>
<td><code>TransAmount</code></td>
<td><code>trans_amount</code></td>
<td>nvarchar(32)</td>
<td><strong>never cast to float</strong> — no precision lost on money</td>
</tr>
<tr>
<td><code>BusinessShortCode</code></td>
<td><code>business_short_code</code></td>
<td>nvarchar(32)</td>
<td></td>
</tr>
<tr>
<td><code>BillRefNumber</code></td>
<td><code>bill_ref_number</code></td>
<td>nvarchar(100)</td>
<td>indexed; matches the receipt to an invoice</td>
</tr>
<tr>
<td><code>InvoiceNumber</code></td>
<td><code>invoice_number</code></td>
<td>nvarchar(100)</td>
<td></td>
</tr>
<tr>
<td><code>OrgAccountBalance</code></td>
<td><code>org_account_balance</code></td>
<td>nvarchar(32)</td>
<td></td>
</tr>
<tr>
<td><code>ThirdPartyTransID</code></td>
<td><code>third_party_trans_id</code></td>
<td>nvarchar(100)</td>
<td></td>
</tr>
<tr>
<td><code>MSISDN</code></td>
<td><code>msisdn</code></td>
<td>nvarchar(32)</td>
<td>indexed</td>
</tr>
<tr>
<td><code>FirstName</code></td>
<td><code>first_name</code></td>
<td>nvarchar(100)</td>
<td></td>
</tr>
<tr>
<td><code>MiddleName</code></td>
<td><code>middle_name</code></td>
<td>nvarchar(100)</td>
<td></td>
</tr>
<tr>
<td><code>LastName</code></td>
<td><code>last_name</code></td>
<td>nvarchar(100)</td>
<td></td>
</tr>
</tbody>
</table>
<p>Every record also stores:</p>
<table>
<thead>
<tr>
<th>Column</th>
<th>Purpose</th>
</tr>
</thead>
<tbody>
<tr>
<td><code>raw_payload</code></td>
<td>the body <strong>exactly</strong> as Safaricom sent it, so nothing added later is silently dropped</td>
</tr>
<tr>
<td><code>callback_type</code></td>
<td><code>validation</code> or <code>confirmation</code></td>
</tr>
<tr>
<td><code>received_at</code></td>
<td>when this system received it — distinct from <code>TransTime</code>, when the payment happened</td>
</tr>
<tr>
<td><code>allocation_status</code></td>
<td><code>Pending</code>, <code>Matched</code>, <code>Partial</code>, <code>Unmatched</code> or <code>N/A</code></td>
</tr>
<tr>
<td><code>allocated_amount</code></td>
<td>how much of the receipt has been applied to invoices</td>
</tr>
</tbody>
</table>
<p>Field matching is <strong>case-insensitive</strong>: sandbox and production payloads have
differed on casing (<code>MSISDN</code> vs <code>Msisdn</code>), and a mismatch would otherwise
silently store a null amount. Any field Safaricom sends that is not modelled is
reported back in <code>data.unmapped_fields</code> rather than discarded — the value is
still kept in <code>raw_payload</code>.</p>
<h2 id="two-payload-shapes-are-accepted">Two payload shapes are accepted</h2>
<p>"C2B" refers to two different Safaricom products, which post different bodies:</p>
<table>
<thead>
<tr>
<th></th>
<th><strong>C2B Register URL</strong> (documented here)</th>
<th><strong>STK Push / Lipa na M-Pesa Online</strong></th>
</tr>
</thead>
<tbody>
<tr>
<td>Shape</td>
<td>flat</td>
<td>nested under <code>Body.stkCallback</code></td>
</tr>
<tr>
<td>Amount</td>
<td><code>TransAmount</code></td>
<td><code>Amount</code>, inside <code>CallbackMetadata.Item[]</code></td>
</tr>
<tr>
<td>Receipt</td>
<td><code>TransID</code></td>
<td><code>MpesaReceiptNumber</code></td>
</tr>
<tr>
<td>Payer</td>
<td><code>MSISDN</code>, <code>FirstName</code>, ...</td>
<td><code>PhoneNumber</code> only</td>
</tr>
</tbody>
</table>
<p>Nothing stops an integrator pointing an STK callback at these URLs, so a nested
body is flattened into the same columns rather than stored as a row of nulls.
A cancelled or timed-out push (<code>ResultCode</code> 1032 / 1037) is recorded but marked
<code>N/A</code> and never treated as money received.</p>
<h2 id="what-happens-after-capture">What happens after capture</h2>
<p>A confirmation fires an internal <code>C2bConfirmationReceived</code> event, and a listener
matches the receipt to the invoice named in <code>BillRefNumber</code>. <strong>Capture and
reconciliation are deliberately separate:</strong> if matching fails, the receipt is
still recorded and queued for manual allocation, because the money has already
moved.</p>

        <h1 id="authenticating-requests">Authenticating requests</h1>
<p>This API is not authenticated.</p>

        <h1 id="m-pesa-c2b-callbacks">M-Pesa C2B Callbacks</h1>

    <p>The two URLs registered with Safaricom Daraja for a paybill / till short code.</p>
<p>Every documented C2B field is parsed out of the payload and stored in its own
string column, the untouched body is retained, and the response confirms
exactly what was captured.</p>

                                <h2 id="m-pesa-c2b-callbacks-POSTapi-mpesa-c2b-validation">Validation callback</h2>

<p>
</p>

<p>Called by Safaricom <strong>before</strong> completing a payment, to ask whether it
should proceed. The payload is identical to the confirmation body.</p>
<p>The attempt is recorded, but no money has moved yet, so nothing is
reconciled against an invoice. Replying <code>ResultCode: 0</code> accepts the
payment; a non-zero code would reject it.</p>
<p>Note that Validation is only invoked at all if the short code has
External Validation enabled with Safaricom.</p>

<span id="example-requests-POSTapi-mpesa-c2b-validation">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost/api/mpesa/c2b/validation" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"TransID\": \"RKTQDM7W6S\",
    \"TransactionType\": \"Pay Bill\",
    \"TransTime\": \"20260810180000\",
    \"TransAmount\": \"1500.00\",
    \"BusinessShortCode\": \"600984\",
    \"BillRefNumber\": \"IN-2\",
    \"OrgAccountBalance\": \"49197.00\",
    \"MSISDN\": \"254708374149\",
    \"FirstName\": \"John\",
    \"MiddleName\": \"Doe\",
    \"LastName\": \"Mwangi\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/mpesa/c2b/validation"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "TransID": "RKTQDM7W6S",
    "TransactionType": "Pay Bill",
    "TransTime": "20260810180000",
    "TransAmount": "1500.00",
    "BusinessShortCode": "600984",
    "BillRefNumber": "IN-2",
    "OrgAccountBalance": "49197.00",
    "MSISDN": "254708374149",
    "FirstName": "John",
    "MiddleName": "Doe",
    "LastName": "Mwangi"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-mpesa-c2b-validation">
            <blockquote>
            <p>Example response (200, Payment accepted):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;ResultCode&quot;: 0,
    &quot;ResultDesc&quot;: &quot;Accepted&quot;,
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Transaction captured successfully.&quot;,
    &quot;data&quot;: {
        &quot;id&quot;: 2,
        &quot;callback_type&quot;: &quot;validation&quot;,
        &quot;received_at&quot;: &quot;2026-08-10T18:00:01+00:00&quot;,
        &quot;fields&quot;: {
            &quot;TransID&quot;: &quot;RKTQDM7W6S&quot;,
            &quot;TransAmount&quot;: &quot;1500.00&quot;
        },
        &quot;payer_name&quot;: &quot;John Doe Mwangi&quot;,
        &quot;missing_fields&quot;: [],
        &quot;unmapped_fields&quot;: []
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-mpesa-c2b-validation" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-mpesa-c2b-validation"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-mpesa-c2b-validation"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-mpesa-c2b-validation" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-mpesa-c2b-validation">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-mpesa-c2b-validation" data-method="POST"
      data-path="api/mpesa/c2b/validation"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-mpesa-c2b-validation', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-mpesa-c2b-validation"
                    onclick="tryItOut('POSTapi-mpesa-c2b-validation');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-mpesa-c2b-validation"
                    onclick="cancelTryOut('POSTapi-mpesa-c2b-validation');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-mpesa-c2b-validation"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/mpesa/c2b/validation</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-mpesa-c2b-validation"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-mpesa-c2b-validation"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>TransID</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="TransID"                data-endpoint="POSTapi-mpesa-c2b-validation"
               value="RKTQDM7W6S"
               data-component="body">
    <br>
<p>Safaricom's receipt number. Example: <code>RKTQDM7W6S</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>TransactionType</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="TransactionType"                data-endpoint="POSTapi-mpesa-c2b-validation"
               value="Pay Bill"
               data-component="body">
    <br>
<p>The kind of payment. Example: <code>Pay Bill</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>TransTime</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="TransTime"                data-endpoint="POSTapi-mpesa-c2b-validation"
               value="20260810180000"
               data-component="body">
    <br>
<p>Timestamp, formatted yyyyMMddHHmmss. Example: <code>20260810180000</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>TransAmount</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="TransAmount"                data-endpoint="POSTapi-mpesa-c2b-validation"
               value="1500.00"
               data-component="body">
    <br>
<p>The amount to be paid. Example: <code>1500.00</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>BusinessShortCode</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="BusinessShortCode"                data-endpoint="POSTapi-mpesa-c2b-validation"
               value="600984"
               data-component="body">
    <br>
<p>The paybill / till being paid. Example: <code>600984</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>BillRefNumber</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="BillRefNumber"                data-endpoint="POSTapi-mpesa-c2b-validation"
               value="IN-2"
               data-component="body">
    <br>
<p>The account number the customer typed. Example: <code>IN-2</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>InvoiceNumber</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="InvoiceNumber"                data-endpoint="POSTapi-mpesa-c2b-validation"
               value=""
               data-component="body">
    <br>
<p>Safaricom's own invoice reference.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>OrgAccountBalance</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="OrgAccountBalance"                data-endpoint="POSTapi-mpesa-c2b-validation"
               value="49197.00"
               data-component="body">
    <br>
<p>The short code's balance. Example: <code>49197.00</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>ThirdPartyTransID</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="ThirdPartyTransID"                data-endpoint="POSTapi-mpesa-c2b-validation"
               value=""
               data-component="body">
    <br>
<p>Third-party reference.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>MSISDN</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="MSISDN"                data-endpoint="POSTapi-mpesa-c2b-validation"
               value="254708374149"
               data-component="body">
    <br>
<p>The paying phone number. Example: <code>254708374149</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>FirstName</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="FirstName"                data-endpoint="POSTapi-mpesa-c2b-validation"
               value="John"
               data-component="body">
    <br>
<p>Payer's first name. Example: <code>John</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>MiddleName</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="MiddleName"                data-endpoint="POSTapi-mpesa-c2b-validation"
               value="Doe"
               data-component="body">
    <br>
<p>Payer's middle name. Example: <code>Doe</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>LastName</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="LastName"                data-endpoint="POSTapi-mpesa-c2b-validation"
               value="Mwangi"
               data-component="body">
    <br>
<p>Payer's last name. Example: <code>Mwangi</code></p>
        </div>
        </form>

                    <h2 id="m-pesa-c2b-callbacks-POSTapi-mpesa-c2b-confirmation">Confirmation callback</h2>

<p>
</p>

<p>Called by Safaricom <strong>after</strong> a payment has completed. This is money that
has actually moved, so the record is captured and then reconciled against
the invoice named in <code>BillRefNumber</code>.</p>
<p>Returns <code>ResultCode: 0</code> once the transaction is safely stored. If storage
fails the response is non-zero so Safaricom retries — combined with the
idempotency key that is safer than accepting and silently losing the
payment.</p>

<span id="example-requests-POSTapi-mpesa-c2b-confirmation">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost/api/mpesa/c2b/confirmation" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"TransID\": \"RKTQDM7W6S\",
    \"TransactionType\": \"Pay Bill\",
    \"TransTime\": \"20260810180000\",
    \"TransAmount\": \"1500.00\",
    \"BusinessShortCode\": \"600984\",
    \"BillRefNumber\": \"IN-2\",
    \"OrgAccountBalance\": \"49197.00\",
    \"MSISDN\": \"254708374149\",
    \"FirstName\": \"John\",
    \"MiddleName\": \"Doe\",
    \"LastName\": \"Mwangi\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/mpesa/c2b/confirmation"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "TransID": "RKTQDM7W6S",
    "TransactionType": "Pay Bill",
    "TransTime": "20260810180000",
    "TransAmount": "1500.00",
    "BusinessShortCode": "600984",
    "BillRefNumber": "IN-2",
    "OrgAccountBalance": "49197.00",
    "MSISDN": "254708374149",
    "FirstName": "John",
    "MiddleName": "Doe",
    "LastName": "Mwangi"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-mpesa-c2b-confirmation">
            <blockquote>
            <p>Example response (200, Captured and matched to an invoice):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;ResultCode&quot;: 0,
    &quot;ResultDesc&quot;: &quot;Confirmation received successfully&quot;,
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Transaction captured successfully.&quot;,
    &quot;data&quot;: {
        &quot;id&quot;: 1,
        &quot;callback_type&quot;: &quot;confirmation&quot;,
        &quot;received_at&quot;: &quot;2026-08-10T18:00:04+00:00&quot;,
        &quot;fields&quot;: {
            &quot;TransactionType&quot;: &quot;Pay Bill&quot;,
            &quot;TransID&quot;: &quot;RKTQDM7W6S&quot;,
            &quot;TransTime&quot;: &quot;20260810180000&quot;,
            &quot;TransAmount&quot;: &quot;1500.00&quot;,
            &quot;BusinessShortCode&quot;: &quot;600984&quot;,
            &quot;BillRefNumber&quot;: &quot;IN-2&quot;,
            &quot;InvoiceNumber&quot;: null,
            &quot;OrgAccountBalance&quot;: &quot;49197.00&quot;,
            &quot;ThirdPartyTransID&quot;: null,
            &quot;MSISDN&quot;: &quot;254708374149&quot;,
            &quot;FirstName&quot;: &quot;John&quot;,
            &quot;MiddleName&quot;: &quot;Doe&quot;,
            &quot;LastName&quot;: &quot;Mwangi&quot;
        },
        &quot;payer_name&quot;: &quot;John Doe Mwangi&quot;,
        &quot;transacted_at&quot;: &quot;2026-08-10T18:00:00+00:00&quot;,
        &quot;missing_fields&quot;: [
            &quot;InvoiceNumber&quot;,
            &quot;ThirdPartyTransID&quot;
        ],
        &quot;unmapped_fields&quot;: []
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (403, Caller is not on the Safaricom IP allow-list):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;ResultCode&quot;: 1,
    &quot;ResultDesc&quot;: &quot;Rejected&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Payload rejected (no TransID)):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;ResultCode&quot;: 1,
    &quot;ResultDesc&quot;: &quot;Rejected: invalid payload&quot;,
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;The callback payload failed validation.&quot;,
    &quot;errors&quot;: {
        &quot;TransID&quot;: [
            &quot;The TransID field is required.&quot;
        ]
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (500, Could not be stored — Safaricom should retry):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;ResultCode&quot;: 1,
    &quot;ResultDesc&quot;: &quot;Failed to record transaction. Please retry.&quot;,
    &quot;success&quot;: false
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-mpesa-c2b-confirmation" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-mpesa-c2b-confirmation"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-mpesa-c2b-confirmation"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-mpesa-c2b-confirmation" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-mpesa-c2b-confirmation">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-mpesa-c2b-confirmation" data-method="POST"
      data-path="api/mpesa/c2b/confirmation"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-mpesa-c2b-confirmation', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-mpesa-c2b-confirmation"
                    onclick="tryItOut('POSTapi-mpesa-c2b-confirmation');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-mpesa-c2b-confirmation"
                    onclick="cancelTryOut('POSTapi-mpesa-c2b-confirmation');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-mpesa-c2b-confirmation"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/mpesa/c2b/confirmation</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-mpesa-c2b-confirmation"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-mpesa-c2b-confirmation"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>TransID</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="TransID"                data-endpoint="POSTapi-mpesa-c2b-confirmation"
               value="RKTQDM7W6S"
               data-component="body">
    <br>
<p>Safaricom's receipt number. Unique — this is the idempotency key. Example: <code>RKTQDM7W6S</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>TransactionType</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="TransactionType"                data-endpoint="POSTapi-mpesa-c2b-confirmation"
               value="Pay Bill"
               data-component="body">
    <br>
<p>The kind of payment. Example: <code>Pay Bill</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>TransTime</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="TransTime"                data-endpoint="POSTapi-mpesa-c2b-confirmation"
               value="20260810180000"
               data-component="body">
    <br>
<p>Timestamp of the payment, formatted yyyyMMddHHmmss. Example: <code>20260810180000</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>TransAmount</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="TransAmount"                data-endpoint="POSTapi-mpesa-c2b-confirmation"
               value="1500.00"
               data-component="body">
    <br>
<p>The amount paid. Stored as a string so no precision is lost. Example: <code>1500.00</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>BusinessShortCode</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="BusinessShortCode"                data-endpoint="POSTapi-mpesa-c2b-confirmation"
               value="600984"
               data-component="body">
    <br>
<p>The paybill / till that was paid. Example: <code>600984</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>BillRefNumber</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="BillRefNumber"                data-endpoint="POSTapi-mpesa-c2b-confirmation"
               value="IN-2"
               data-component="body">
    <br>
<p>The account number the customer typed. Used to match the receipt to an invoice. Example: <code>IN-2</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>InvoiceNumber</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="InvoiceNumber"                data-endpoint="POSTapi-mpesa-c2b-confirmation"
               value=""
               data-component="body">
    <br>
<p>Safaricom's own invoice reference. Usually empty.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>OrgAccountBalance</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="OrgAccountBalance"                data-endpoint="POSTapi-mpesa-c2b-confirmation"
               value="49197.00"
               data-component="body">
    <br>
<p>The short code's balance after the payment. Example: <code>49197.00</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>ThirdPartyTransID</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="ThirdPartyTransID"                data-endpoint="POSTapi-mpesa-c2b-confirmation"
               value=""
               data-component="body">
    <br>
<p>A reference from a third-party system. Usually empty.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>MSISDN</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="MSISDN"                data-endpoint="POSTapi-mpesa-c2b-confirmation"
               value="254708374149"
               data-component="body">
    <br>
<p>The paying phone number. Example: <code>254708374149</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>FirstName</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="FirstName"                data-endpoint="POSTapi-mpesa-c2b-confirmation"
               value="John"
               data-component="body">
    <br>
<p>Payer's first name. Example: <code>John</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>MiddleName</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="MiddleName"                data-endpoint="POSTapi-mpesa-c2b-confirmation"
               value="Doe"
               data-component="body">
    <br>
<p>Payer's middle name. Example: <code>Doe</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>LastName</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="LastName"                data-endpoint="POSTapi-mpesa-c2b-confirmation"
               value="Mwangi"
               data-component="body">
    <br>
<p>Payer's last name. Example: <code>Mwangi</code></p>
        </div>
        </form>

            

        
    </div>
    <div class="dark-box">
                    <div class="lang-selector">
                                                        <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                                        <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                            </div>
            </div>
</div>
</body>
</html>
