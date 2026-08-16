<?php

namespace App\Http\Requests;

use App\Services\Mpesa\CallbackNormaliser;
use App\Services\MpesaC2BService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validation for an inbound M-Pesa C2B callback.
 *
 * Deliberately permissive: Safaricom is the caller and rejecting a payment we
 * do not perfectly recognise would lose money. Only TransID is truly required,
 * because it is the idempotency key. Everything else is length-bounded so a
 * malformed or hostile body cannot overflow a column.
 */
class MpesaC2BCallbackRequest extends FormRequest
{
    /** @var array<string, mixed>|null */
    protected ?array $originalPayload = null;

    protected ?string $shape = null;

    public function authorize(): bool
    {
        // The endpoint is public by necessity — Safaricom cannot authenticate.
        // Access is instead restricted by the IP allow-list middleware.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'TransID' => ['required', 'string', 'max:64'],
            'TransactionType' => ['nullable', 'string', 'max:100'],
            'TransTime' => ['nullable', 'string', 'max:32'],
            'TransAmount' => ['nullable', 'string', 'max:32'],
            'BusinessShortCode' => ['nullable', 'string', 'max:32'],
            'BillRefNumber' => ['nullable', 'string', 'max:100'],
            'InvoiceNumber' => ['nullable', 'string', 'max:100'],
            'OrgAccountBalance' => ['nullable', 'string', 'max:32'],
            'ThirdPartyTransID' => ['nullable', 'string', 'max:100'],
            'MSISDN' => ['nullable', 'string', 'max:32'],
            'FirstName' => ['nullable', 'string', 'max:100'],
            'MiddleName' => ['nullable', 'string', 'max:100'],
            'LastName' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Field documentation, kept beside the rules it describes.
     *
     * Scribe reads this to build the request table in the generated docs, so
     * the published API reference cannot drift from the validation.
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'TransactionType' => [
                'description' => 'The kind of payment, e.g. "Pay Bill" or "Buy Goods".',
                'example' => 'Pay Bill',
            ],
            'TransID' => [
                // Stated explicitly: a bodyParameters() entry overrides what
                // Scribe would otherwise infer from rules(), and without this
                // the docs show the one mandatory field as optional.
                'required' => true,
                'description' => "Safaricom's receipt number. Unique — this is the idempotency key, so a retried callback updates the existing record rather than paying twice.",
                'example' => 'RKTQDM7W6S',
            ],
            'TransTime' => [
                'description' => 'When the payment happened, formatted yyyyMMddHHmmss.',
                'example' => '20260810180000',
            ],
            'TransAmount' => [
                'description' => 'Amount paid. Stored as a string so no precision is lost on the way in.',
                'example' => '1500.00',
            ],
            'BusinessShortCode' => [
                'description' => 'The paybill or till number that was paid.',
                'example' => '600984',
            ],
            'BillRefNumber' => [
                'description' => 'The account number the customer typed. This is what the receipt is matched to an invoice by — "IN-2", "INV-2", "IN2" and "2" all resolve to the same document.',
                'example' => 'IN-2',
            ],
            'InvoiceNumber' => [
                'description' => "Safaricom's own invoice reference. Usually empty.",
                // null tells Scribe to show no example rather than inventing one.
                'example' => null,
            ],
            'OrgAccountBalance' => [
                'description' => "The short code's balance after the payment.",
                'example' => '49197.00',
            ],
            'ThirdPartyTransID' => [
                'description' => 'A reference supplied by a third-party system. Usually empty.',
                'example' => null,
            ],
            'MSISDN' => [
                'description' => 'The paying phone number.',
                'example' => '254708374149',
            ],
            'FirstName' => ['description' => "Payer's first name.", 'example' => 'John'],
            'MiddleName' => ['description' => "Payer's middle name.", 'example' => 'Doe'],
            'LastName' => ['description' => "Payer's last name.", 'example' => 'Mwangi'],
        ];
    }

    /**
     * Keep Safaricom's own field names in the error output — Laravel would
     * otherwise snake-case "TransID" into "trans i d".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_combine(
            array_keys(MpesaC2BService::FIELD_MAP),
            array_keys(MpesaC2BService::FIELD_MAP),
        );
    }

    /**
     * Laravel's default 422 body would not be recognised by Safaricom's
     * parser. Reply in the ResultCode shape it expects so a rejected callback
     * is retried rather than treated as delivered.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'ResultCode' => 1,
            'ResultDesc' => 'Rejected: invalid payload',
            'success' => false,
            'message' => 'The callback payload failed validation.',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }

    /**
     * Normalise the shape, then cast scalars to strings.
     *
     * An STK Push callback arrives nested under Body.stkCallback with entirely
     * different field names; flattening it here means the validation rules and
     * the whole downstream path only ever see flat C2B fields.
     *
     * Numeric values also arrive unquoted in the JSON body, so they are cast
     * before the string rules run.
     */
    protected function prepareForValidation(): void
    {
        $normaliser = app(CallbackNormaliser::class);

        $this->originalPayload = $this->all();
        $this->shape = $normaliser->shapeOf($this->originalPayload);

        $flat = $normaliser->normalise($this->originalPayload);

        $casted = [];

        foreach ($flat as $key => $value) {
            $casted[$key] = is_scalar($value) ? (string) $value : $value;
        }

        // replace(), not merge(): the nested Body key must not survive into
        // validation, or an STK payload would fail the string rules.
        $this->replace($casted);
    }

    /**
     * The body exactly as Safaricom sent it, before flattening.
     *
     * @return array<string, mixed>
     */
    public function originalPayload(): array
    {
        return $this->originalPayload ?? $this->all();
    }

    public function shape(): string
    {
        return $this->shape ?? CallbackNormaliser::SHAPE_C2B;
    }

    /**
     * A cancelled or timed-out STK push is not money received.
     */
    public function isSuccessfulPayment(): bool
    {
        return app(CallbackNormaliser::class)->isSuccessful($this->originalPayload());
    }

    public function resultDescription(): ?string
    {
        return app(CallbackNormaliser::class)->resultDescription($this->originalPayload());
    }
}
