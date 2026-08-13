<?php

namespace App\Services\Mpesa;

/**
 * Flattens the shapes Safaricom actually posts into one set of fields.
 *
 * Two different products get called "C2B" in the wild:
 *
 *  1. C2B Register URL (Validation / Confirmation). A flat body:
 *     {"TransactionType":"Pay Bill","TransID":"RKT...","TransAmount":"1500.00",
 *      "MSISDN":"254...","BillRefNumber":"IN-2", ...}
 *     This is the one the assessment describes — TransAmount and TransID only
 *     exist here.
 *
 *  2. STK Push / Lipa na M-Pesa Online. A nested body:
 *     {"Body":{"stkCallback":{"CheckoutRequestID":"ws_CO_...","ResultCode":0,
 *       "CallbackMetadata":{"Item":[{"Name":"Amount","Value":1500},
 *       {"Name":"MpesaReceiptNumber","Value":"RKT..."}, ...]}}}}
 *
 * Nothing stops an integrator pointing an STK callback at a C2B URL. Parsing
 * only shape 1 would store a row of nulls and silently lose the payment, so
 * both are normalised here into the same string fields.
 */
class CallbackNormaliser
{
    public const SHAPE_C2B = 'c2b';
    public const SHAPE_STK = 'stk';

    /**
     * STK metadata item name => the C2B field it corresponds to.
     */
    protected const STK_FIELD_MAP = [
        'MpesaReceiptNumber' => 'TransID',
        'Amount' => 'TransAmount',
        'TransactionDate' => 'TransTime',
        'PhoneNumber' => 'MSISDN',
        'Balance' => 'OrgAccountBalance',
    ];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function shapeOf(array $payload): string
    {
        return isset($payload['Body']['stkCallback']) ? self::SHAPE_STK : self::SHAPE_C2B;
    }

    /**
     * Return the payload in flat C2B field names, whatever shape it arrived in.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalise(array $payload): array
    {
        return $this->shapeOf($payload) === self::SHAPE_STK
            ? $this->flattenStk($payload)
            : $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function flattenStk(array $payload): array
    {
        $callback = $payload['Body']['stkCallback'] ?? [];

        $flat = [
            'TransactionType' => 'Customer Lipa na M-Pesa Online',
            // A cancelled or failed STK push carries no receipt number, so fall
            // back to the checkout id — the record still has to be storable and
            // uniquely keyed, otherwise failures vanish.
            'TransID' => $callback['CheckoutRequestID'] ?? $callback['MerchantRequestID'] ?? null,
            'ThirdPartyTransID' => $callback['MerchantRequestID'] ?? null,
        ];

        // MpesaReceiptNumber maps onto TransID, so a successful push replaces
        // the CheckoutRequestID placeholder set above with the real receipt.
        foreach ($this->metadataItems($callback) as $name => $value) {
            $field = self::STK_FIELD_MAP[$name] ?? null;

            if ($field !== null && filled($value)) {
                $flat[$field] = $value;
            }
        }

        $flat['TransTime'] = $this->normaliseStkTimestamp($flat['TransTime'] ?? null);

        return array_filter($flat, fn ($value) => $value !== null);
    }

    /**
     * CallbackMetadata.Item is a list of {Name, Value} pairs.
     *
     * @param  array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    protected function metadataItems(array $callback): array
    {
        $items = $callback['CallbackMetadata']['Item'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        $result = [];

        foreach ($items as $item) {
            if (is_array($item) && isset($item['Name'])) {
                $result[$item['Name']] = $item['Value'] ?? null;
            }
        }

        return $result;
    }

    /**
     * STK sends TransactionDate as the integer 20260810180000; C2B sends the
     * same instant as a string. Normalise to the C2B form so one accessor can
     * parse either.
     */
    protected function normaliseStkTimestamp(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return (string) $value;
    }

    /**
     * Whether an STK callback reports a completed payment.
     *
     * ResultCode 0 is success; anything else (1032 = cancelled by user,
     * 1037 = timeout) is a failed attempt that must not be treated as money.
     *
     * @param  array<string, mixed>  $payload
     */
    public function isSuccessful(array $payload): bool
    {
        if ($this->shapeOf($payload) === self::SHAPE_C2B) {
            // A C2B confirmation only ever describes money already received.
            return true;
        }

        return (int) ($payload['Body']['stkCallback']['ResultCode'] ?? 1) === 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resultDescription(array $payload): ?string
    {
        return $payload['Body']['stkCallback']['ResultDesc'] ?? null;
    }
}
