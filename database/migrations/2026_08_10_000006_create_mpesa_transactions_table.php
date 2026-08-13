<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-Pesa C2B callbacks (Task 3).
 *
 * The spec asks for every field of the payload in its own string column, so
 * the columns below mirror the Daraja C2B confirmation body one-for-one and
 * are all nvarchar — no casting, no lossy conversion of amounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->id();

            $table->string('transaction_type', 100)->nullable();      // TransactionType
            $table->string('trans_id', 64);                           // TransID
            $table->string('trans_time', 32)->nullable();             // TransTime (yyyyMMddHHmmss)
            $table->string('trans_amount', 32)->nullable();           // TransAmount
            $table->string('business_short_code', 32)->nullable();    // BusinessShortCode
            $table->string('bill_ref_number', 100)->nullable();       // BillRefNumber
            $table->string('invoice_number', 100)->nullable();        // InvoiceNumber
            $table->string('org_account_balance', 32)->nullable();    // OrgAccountBalance
            $table->string('third_party_trans_id', 100)->nullable();  // ThirdPartyTransID
            $table->string('msisdn', 32)->nullable();                 // MSISDN
            $table->string('first_name', 100)->nullable();            // FirstName
            $table->string('middle_name', 100)->nullable();           // MiddleName
            $table->string('last_name', 100)->nullable();             // LastName

            // Which endpoint received it, plus the untouched body so nothing
            // Safaricom adds later is silently dropped.
            $table->string('callback_type', 20)->default('confirmation'); // validation | confirmation
            $table->json('raw_payload');
            $table->dateTime('received_at');
            $table->timestamps();

            // Safaricom retries a callback until it gets a 200, so the same
            // TransID can legitimately arrive several times. This makes the
            // ingest idempotent instead of creating duplicate payments.
            $table->unique(['trans_id', 'callback_type']);
            $table->index('msisdn');
            $table->index('bill_ref_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpesa_transactions');
    }
};
