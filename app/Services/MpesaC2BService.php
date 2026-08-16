<?php

namespace App\Services;

use App\Events\C2bConfirmationReceived;
use App\Models\MpesaTransaction;

/**
 * Task 3 — parsing and persisting M-Pesa C2B callbacks.
 *
 * Every documented Daraja C2B field is mapped to its own string column. The
 * untouched body is stored alongside so that anything Safaricom adds later is
 * still captured rather than silently dropped.
 */
class MpesaC2BService
{
    /**
     * Callback field => database column.
     *
     * @var array<string, string>
     */
    public const array FIELD_MAP = [
        'TransactionType' => 'transaction_type',
        'TransID' => 'trans_id',
        'TransTime' => 'trans_time',
        'TransAmount' => 'trans_amount',
        'BusinessShortCode' => 'business_short_code',
        'BillRefNumber' => 'bill_ref_number',
        'InvoiceNumber' => 'invoice_number',
        'OrgAccountBalance' => 'org_account_balance',
        'ThirdPartyTransID' => 'third_party_trans_id',
        'MSISDN' => 'msisdn',
        'FirstName' => 'first_name',
        'MiddleName' => 'middle_name',
        'LastName' => 'last_name',
    ];

    /**
     * Store a callback, returning the persisted transaction.
     *
     * Safaricom retries until it receives a success response, so the same
     * TransID can arrive more than once. Keying the write on
     * (trans_id, callback_type) makes a retry update the existing row instead
     * of creating a duplicate payment.
     *
     * @param  array<string, mixed>  $payload
     */
    public function store(
        array $payload,
        string $callbackType = MpesaTransaction::TYPE_CONFIRMATION,
        ?array $rawPayload = null,
        bool $isPayment = true,
    ): MpesaTransaction {
        $attributes = $this->parse($payload);

        $transaction = MpesaTransaction::updateOrCreate(
            [
                'trans_id' => $attributes['trans_id'],
                'callback_type' => $callbackType,
            ],
            $attributes + [
                'callback_type' => $callbackType,
                // Keep the body exactly as it arrived, which for an STK push is
                // the nested form rather than the flattened one.
                'raw_payload' => $rawPayload ?? $payload,
                'received_at' => now(),
            ],
        );

        // Set explicitly rather than relying on the column default: a default
        // is not reflected on the in-memory model, and the listener decides
        // whether to allocate by reading this value.
        if ($transaction->wasRecentlyCreated) {
            $transaction->allocation_status = MpesaTransaction::ALLOCATION_PENDING;
            $transaction->save();
        }

        // A failed or cancelled STK push is a record of an attempt, not money,
        // so it is never announced as a confirmed receipt.
        if (! $isPayment) {
            $transaction->update(['allocation_status' => MpesaTransaction::ALLOCATION_NOT_APPLICABLE]);

            return $transaction->refresh();
        }

        // Capture is done. Everything downstream — matching the receipt to an
        // invoice — hangs off this event, so reconciliation is decoupled from
        // recording the money and can never prevent it.
        if ($callbackType === MpesaTransaction::TYPE_CONFIRMATION) {
            C2bConfirmationReceived::dispatch($transaction, $rawPayload ?? $payload);
        }

        return $transaction->refresh();
    }

    /**
     * Map the callback body onto column values, all as strings.
     *
     * Field names are matched case-insensitively because the sandbox and
     * production payloads have differed on casing in the past (MSISDN vs
     * Msisdn), and a mismatch would silently store a null amount.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string|null>
     */
    public function parse(array $payload): array
    {
        $normalised = [];

        foreach ($payload as $key => $value) {
            $normalised[strtolower((string) $key)] = $value;
        }

        $attributes = [];

        foreach (self::FIELD_MAP as $field => $column) {
            $value = $normalised[strtolower($field)] ?? null;

            // Cast to string per the spec: "extract all fields into separate
            // string fields". Amounts are never turned into floats, so no
            // precision is lost in transit.
            $attributes[$column] = $this->stringify($value);
        }

        return $attributes;
    }

    /**
     * Scalars become strings; anything structured is JSON-encoded rather than
     * throwing, so a malformed field cannot reject an otherwise valid payment.
     */
    protected function stringify(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return json_encode($value) ?: null;
    }

    /**
     * Which documented fields were absent from a payload — surfaced in the
     * response so an integrator can see what Safaricom did not send.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public function missingFields(array $payload): array
    {
        $parsed = $this->parse($payload);

        return collect(self::FIELD_MAP)
            ->filter(fn (string $column) => $parsed[$column] === null)
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function knownFields(): array
    {
        return array_keys(self::FIELD_MAP);
    }

    /**
     * Fields present in the payload that this integration does not model.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public function unmappedFields(array $payload): array
    {
        $known = array_map('strtolower', $this->knownFields());

        return collect(array_keys($payload))
            ->reject(fn ($key) => in_array(strtolower((string) $key), $known, true))
            ->values()
            ->all();
    }
}
