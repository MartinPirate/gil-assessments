<?php

namespace App\Services;

use App\Enums\OrderStage;
use App\Models\ApprovalRequest;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The workflow behind the "Invoice will go for approval" label.
 *
 * The label alone is only half the requirement: a document that announces it
 * needs approval has to actually be routed somewhere and decided on.
 */
class ApprovalService
{
    public function __construct(protected OrderLifecycleService $lifecycle) {}

    /**
     * Open a request if the document breached the threshold.
     *
     * Called inside the invoice's own save transaction, so a document is never
     * committed as "Pending Approval" without a matching queue entry.
     *
     * $userId is null when the repair command opens a request for a document
     * that was already pending — nobody requested it, and inventing a
     * requester would be worse than recording none.
     */
    public function requestIfNeeded(Invoice $invoice, ?int $userId = null): ?ApprovalRequest
    {
        if (! $invoice->requires_approval || $invoice->isDraft()) {
            return null;
        }

        // Guard against a second request for the same document.
        $existing = $invoice->approvalRequests()
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->first();

        if ($existing) {
            return $existing;
        }

        return ApprovalRequest::create([
            'invoice_id' => $invoice->getKey(),
            'amount' => $invoice->document_total,
            'threshold' => Invoice::APPROVAL_THRESHOLD,
            'status' => ApprovalRequest::STATUS_PENDING,
            'requested_by' => $userId,
            'requested_at' => now(),
        ]);
    }

    /**
     * Approve a pending request; the invoice becomes a normal open document.
     */
    public function approve(ApprovalRequest $request, User $decider, ?string $reason = null): ApprovalRequest
    {
        return $this->decide($request, $decider, ApprovalRequest::STATUS_APPROVED, $reason);
    }

    public function reject(ApprovalRequest $request, User $decider, string $reason): ApprovalRequest
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'A rejection reason is required.',
            ]);
        }

        return $this->decide($request, $decider, ApprovalRequest::STATUS_REJECTED, $reason);
    }

    /**
     * Shared decision path so approve and reject cannot drift apart.
     */
    protected function decide(ApprovalRequest $request, User $decider, string $status, ?string $reason): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $decider, $status, $reason) {
            // Re-read under a lock: two approvers opening the same queue entry
            // must not both record a decision.
            $locked = ApprovalRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== ApprovalRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'request' => "This request was already {$locked->status} by ".
                        ($locked->decider?->name ?? 'another user').'.',
                ]);
            }

            if (! $decider->canApproveAmount((float) $locked->amount)) {
                throw ValidationException::withMessages([
                    'request' => 'This amount is above your approval limit.',
                ]);
            }

            $locked->update([
                'status' => $status,
                'decided_by' => $decider->getKey(),
                'decided_at' => now(),
                'decision_reason' => $reason,
            ]);

            $invoice = Invoice::query()->whereKey($locked->invoice_id)->lockForUpdate()->firstOrFail();

            $invoice->update([
                'status' => $status === ApprovalRequest::STATUS_APPROVED
                    ? ($invoice->balance_due > 0 ? Invoice::STATUS_OPEN : Invoice::STATUS_CLOSED)
                    : Invoice::STATUS_REJECTED,
            ]);

            // A rejection ends the order rather than advancing it, so the two
            // decisions map to different stages.
            $this->lifecycle->record(
                $invoice,
                $status === ApprovalRequest::STATUS_APPROVED ? OrderStage::Approved : OrderStage::Cancelled,
                $locked->decided_at,
                $decider,
                $status === ApprovalRequest::STATUS_APPROVED
                    ? "Approved by {$decider->name}."
                    : "Rejected by {$decider->name}: {$reason}",
            );

            return $locked->refresh();
        });
    }
}
