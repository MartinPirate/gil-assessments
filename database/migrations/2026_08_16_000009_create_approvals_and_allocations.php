<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The approval queue behind the "Invoice will go for approval" label, and the
 * allocation of M-Pesa receipts against invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 3);
            $table->decimal('threshold', 18, 3);
            $table->string('status', 20)->default('Pending');   // Pending|Approved|Rejected
            $table->foreignId('requested_by')->constrained('users');
            $table->dateTime('requested_at');
            // No cascade: SQL Server rejects multiple cascade paths to users.
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->dateTime('decided_at')->nullable();
            $table->string('decision_reason', 1000)->nullable();
            $table->timestamps();

            $table->index(['status', 'requested_at']);
            // One open request per invoice; decided ones are kept for history.
            $table->index('invoice_id');
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mpesa_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained();
            $table->decimal('amount', 18, 3);
            $table->string('matched_by', 20)->default('auto');   // auto | manual
            $table->foreignId('allocated_by')->nullable()->constrained('users');
            $table->dateTime('allocated_at');
            $table->timestamps();

            // A receipt is applied to a given invoice at most once.
            $table->unique(['mpesa_transaction_id', 'invoice_id']);
            $table->index('invoice_id');
        });

        Schema::table('mpesa_transactions', function (Blueprint $table) {
            // Pending | Matched | Unmatched | Overpaid
            $table->string('allocation_status', 20)->default('Pending')->after('callback_type');
            $table->decimal('allocated_amount', 18, 3)->default(0)->after('allocation_status');

            $table->index('allocation_status');
        });
    }

    public function down(): void
    {
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->dropColumn(['allocation_status', 'allocated_amount']);
        });

        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('approval_requests');
    }
};
