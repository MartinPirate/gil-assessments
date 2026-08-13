<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MpesaC2BCallbackRequest;
use App\Models\MpesaTransaction;
use App\Services\MpesaC2BService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @group M-Pesa C2B Callbacks
 *
 * The two URLs registered with Safaricom Daraja for a paybill / till short code.
 *
 * Every documented C2B field is parsed out of the payload and stored in its own
 * string column, the untouched body is retained, and the response confirms
 * exactly what was captured.
 */
class MpesaC2BController extends Controller
{
    public function __construct(protected MpesaC2BService $service) {}

    /**
     * Confirmation callback
     *
     * Called by Safaricom **after** a payment has completed. This is money that
     * has actually moved, so the record is captured and then reconciled against
     * the invoice named in `BillRefNumber`.
     *
     * Returns `ResultCode: 0` once the transaction is safely stored. If storage
     * fails the response is non-zero so Safaricom retries — combined with the
     * idempotency key that is safer than accepting and silently losing the
     * payment.
     *
     * @unauthenticated
     *
     * @bodyParam TransactionType string The kind of payment. Example: Pay Bill
     * @bodyParam TransID string required Safaricom's receipt number. Unique — this is the idempotency key. Example: RKTQDM7W6S
     * @bodyParam TransTime string Timestamp of the payment, formatted yyyyMMddHHmmss. Example: 20260810180000
     * @bodyParam TransAmount string The amount paid. Stored as a string so no precision is lost. Example: 1500.00
     * @bodyParam BusinessShortCode string The paybill / till that was paid. Example: 600984
     * @bodyParam BillRefNumber string The account number the customer typed. Used to match the receipt to an invoice. Example: IN-2
     * @bodyParam InvoiceNumber string Safaricom's own invoice reference. Usually empty. No-example
     * @bodyParam OrgAccountBalance string The short code's balance after the payment. Example: 49197.00
     * @bodyParam ThirdPartyTransID string A reference from a third-party system. Usually empty. No-example
     * @bodyParam MSISDN string The paying phone number. Example: 254708374149
     * @bodyParam FirstName string Payer's first name. Example: John
     * @bodyParam MiddleName string Payer's middle name. Example: Doe
     * @bodyParam LastName string Payer's last name. Example: Mwangi
     *
     * @response 200 scenario="Captured and matched to an invoice" {
     *   "ResultCode": 0,
     *   "ResultDesc": "Confirmation received successfully",
     *   "success": true,
     *   "message": "Transaction captured successfully.",
     *   "data": {
     *     "id": 1,
     *     "callback_type": "confirmation",
     *     "received_at": "2026-08-10T18:00:04+00:00",
     *     "fields": {
     *       "TransactionType": "Pay Bill",
     *       "TransID": "RKTQDM7W6S",
     *       "TransTime": "20260810180000",
     *       "TransAmount": "1500.00",
     *       "BusinessShortCode": "600984",
     *       "BillRefNumber": "IN-2",
     *       "InvoiceNumber": null,
     *       "OrgAccountBalance": "49197.00",
     *       "ThirdPartyTransID": null,
     *       "MSISDN": "254708374149",
     *       "FirstName": "John",
     *       "MiddleName": "Doe",
     *       "LastName": "Mwangi"
     *     },
     *     "payer_name": "John Doe Mwangi",
     *     "transacted_at": "2026-08-10T18:00:00+00:00",
     *     "missing_fields": ["InvoiceNumber", "ThirdPartyTransID"],
     *     "unmapped_fields": []
     *   }
     * }
     * @response 422 scenario="Payload rejected (no TransID)" {
     *   "ResultCode": 1,
     *   "ResultDesc": "Rejected: invalid payload",
     *   "success": false,
     *   "message": "The callback payload failed validation.",
     *   "errors": {"TransID": ["The TransID field is required."]}
     * }
     * @response 403 scenario="Caller is not on the Safaricom IP allow-list" {
     *   "ResultCode": 1,
     *   "ResultDesc": "Rejected"
     * }
     * @response 500 scenario="Could not be stored — Safaricom should retry" {
     *   "ResultCode": 1,
     *   "ResultDesc": "Failed to record transaction. Please retry.",
     *   "success": false
     * }
     */
    public function confirmation(MpesaC2BCallbackRequest $request): JsonResponse
    {
        return $this->ingest($request, MpesaTransaction::TYPE_CONFIRMATION);
    }

    /**
     * Validation callback
     *
     * Called by Safaricom **before** completing a payment, to ask whether it
     * should proceed. The payload is identical to the confirmation body.
     *
     * The attempt is recorded, but no money has moved yet, so nothing is
     * reconciled against an invoice. Replying `ResultCode: 0` accepts the
     * payment; a non-zero code would reject it.
     *
     * Note that Validation is only invoked at all if the short code has
     * External Validation enabled with Safaricom.
     *
     * @unauthenticated
     *
     * @bodyParam TransactionType string The kind of payment. Example: Pay Bill
     * @bodyParam TransID string required Safaricom's receipt number. Example: RKTQDM7W6S
     * @bodyParam TransTime string Timestamp, formatted yyyyMMddHHmmss. Example: 20260810180000
     * @bodyParam TransAmount string The amount to be paid. Example: 1500.00
     * @bodyParam BusinessShortCode string The paybill / till being paid. Example: 600984
     * @bodyParam BillRefNumber string The account number the customer typed. Example: IN-2
     * @bodyParam InvoiceNumber string Safaricom's own invoice reference. No-example
     * @bodyParam OrgAccountBalance string The short code's balance. Example: 49197.00
     * @bodyParam ThirdPartyTransID string Third-party reference. No-example
     * @bodyParam MSISDN string The paying phone number. Example: 254708374149
     * @bodyParam FirstName string Payer's first name. Example: John
     * @bodyParam MiddleName string Payer's middle name. Example: Doe
     * @bodyParam LastName string Payer's last name. Example: Mwangi
     *
     * @response 200 scenario="Payment accepted" {
     *   "ResultCode": 0,
     *   "ResultDesc": "Accepted",
     *   "success": true,
     *   "message": "Transaction captured successfully.",
     *   "data": {
     *     "id": 2,
     *     "callback_type": "validation",
     *     "received_at": "2026-08-10T18:00:01+00:00",
     *     "fields": {"TransID": "RKTQDM7W6S", "TransAmount": "1500.00"},
     *     "payer_name": "John Doe Mwangi",
     *     "missing_fields": [],
     *     "unmapped_fields": []
     *   }
     * }
     */
    public function validation(MpesaC2BCallbackRequest $request): JsonResponse
    {
        return $this->ingest($request, MpesaTransaction::TYPE_VALIDATION);
    }

    /**
     * Shared ingest path for both callback types.
     */
    protected function ingest(MpesaC2BCallbackRequest $request, string $type): JsonResponse
    {
        $payload = $request->validated();

        try {
            $transaction = $this->service->store(
                $payload,
                $type,
                // Stored verbatim so an STK body is kept in the shape Safaricom
                // sent it, not the flattened version we work with.
                rawPayload: $request->originalPayload(),
                // A cancelled or timed-out STK push is recorded but must not be
                // allocated to an invoice — no money moved.
                isPayment: $request->isSuccessfulPayment(),
            );
        } catch (\Throwable $e) {
            // Never swallow: log with context, then tell Safaricom we failed
            // so the callback is retried rather than counted as delivered.
            Log::error('M-Pesa C2B callback could not be stored', [
                'callback_type' => $type,
                'trans_id' => $payload['TransID'] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Failed to record transaction. Please retry.',
                'success' => false,
            ], 500);
        }

        Log::info('M-Pesa C2B callback stored', [
            'callback_type' => $type,
            'trans_id' => $transaction->trans_id,
            'amount' => $transaction->trans_amount,
        ]);

        return response()->json([
            // The two keys Safaricom itself acts on — anything non-zero makes
            // it retry, so these must be present and correct.
            'ResultCode' => 0,
            'ResultDesc' => $type === MpesaTransaction::TYPE_VALIDATION ? 'Accepted' : 'Confirmation received successfully',

            // The structured confirmation of capture the spec asks for.
            'success' => true,
            'message' => 'Transaction captured successfully.',
            'data' => [
                'id' => $transaction->id,
                'callback_type' => $transaction->callback_type,
                'received_at' => $transaction->received_at?->toIso8601String(),
                'fields' => [
                    'TransactionType' => $transaction->transaction_type,
                    'TransID' => $transaction->trans_id,
                    'TransTime' => $transaction->trans_time,
                    'TransAmount' => $transaction->trans_amount,
                    'BusinessShortCode' => $transaction->business_short_code,
                    'BillRefNumber' => $transaction->bill_ref_number,
                    'InvoiceNumber' => $transaction->invoice_number,
                    'OrgAccountBalance' => $transaction->org_account_balance,
                    'ThirdPartyTransID' => $transaction->third_party_trans_id,
                    'MSISDN' => $transaction->msisdn,
                    'FirstName' => $transaction->first_name,
                    'MiddleName' => $transaction->middle_name,
                    'LastName' => $transaction->last_name,
                ],
                'payer_name' => $transaction->payer_name,
                'transacted_at' => $transaction->transacted_at?->toIso8601String(),
                'missing_fields' => $this->service->missingFields($payload),
                'unmapped_fields' => $this->service->unmappedFields($request->all()),
            ],
        ]);
    }
}
