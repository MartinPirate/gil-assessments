<?php

namespace Tests\Unit;

use App\Services\Mpesa\CallbackNormaliser;
use PHPUnit\Framework\TestCase;

/**
 * Flattening the two payload shapes Safaricom actually posts.
 */
class CallbackNormaliserTest extends TestCase
{
    protected CallbackNormaliser $normaliser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normaliser = new CallbackNormaliser;
    }

    /**
     * @param  array<int, array{Name: string, Value?: mixed}>|null  $items
     * @return array<string, mixed>
     */
    protected function stk(int $resultCode = 0, ?array $items = null): array
    {
        $callback = [
            'MerchantRequestID' => '29115-34620561-1',
            'CheckoutRequestID' => 'ws_CO_123',
            'ResultCode' => $resultCode,
            'ResultDesc' => $resultCode === 0 ? 'Success' : 'Cancelled by user',
        ];

        if ($resultCode === 0) {
            $callback['CallbackMetadata'] = ['Item' => $items ?? [
                ['Name' => 'Amount', 'Value' => 1500],
                ['Name' => 'MpesaReceiptNumber', 'Value' => 'NLJ7RT61SV'],
                ['Name' => 'TransactionDate', 'Value' => 20260810180000],
                ['Name' => 'PhoneNumber', 'Value' => 254708374149],
            ]];
        }

        return ['Body' => ['stkCallback' => $callback]];
    }

    public function test_it_detects_each_shape(): void
    {
        $this->assertSame(CallbackNormaliser::SHAPE_STK, $this->normaliser->shapeOf($this->stk()));
        $this->assertSame(CallbackNormaliser::SHAPE_C2B, $this->normaliser->shapeOf(['TransID' => 'X']));
        $this->assertSame(CallbackNormaliser::SHAPE_C2B, $this->normaliser->shapeOf([]));
    }

    public function test_a_flat_c2b_payload_passes_through_untouched(): void
    {
        $payload = ['TransID' => 'RKT1', 'TransAmount' => '1500.00'];

        $this->assertSame($payload, $this->normaliser->normalise($payload));
    }

    public function test_stk_metadata_maps_onto_c2b_field_names(): void
    {
        $flat = $this->normaliser->normalise($this->stk());

        $this->assertSame('NLJ7RT61SV', $flat['TransID']);
        $this->assertSame(1500, $flat['TransAmount']);
        $this->assertSame(254708374149, $flat['MSISDN']);
        $this->assertSame('20260810180000', $flat['TransTime']);
        $this->assertSame('29115-34620561-1', $flat['ThirdPartyTransID']);
        $this->assertSame('Customer Lipa na M-Pesa Online', $flat['TransactionType']);
    }

    /**
     * A cancelled push carries no receipt, but the attempt still has to be
     * storable under a unique key.
     */
    public function test_a_failed_push_falls_back_to_the_checkout_id(): void
    {
        $flat = $this->normaliser->normalise($this->stk(resultCode: 1032));

        $this->assertSame('ws_CO_123', $flat['TransID']);
        $this->assertArrayNotHasKey('TransAmount', $flat);
    }

    public function test_it_reports_whether_money_actually_moved(): void
    {
        $this->assertTrue($this->normaliser->isSuccessful($this->stk()));
        $this->assertFalse($this->normaliser->isSuccessful($this->stk(resultCode: 1032)));
        $this->assertFalse($this->normaliser->isSuccessful($this->stk(resultCode: 1037)));

        // A C2B confirmation only ever describes money already received.
        $this->assertTrue($this->normaliser->isSuccessful(['TransID' => 'X']));
    }

    public function test_metadata_items_without_a_value_are_skipped(): void
    {
        $flat = $this->normaliser->normalise($this->stk(items: [
            ['Name' => 'Amount', 'Value' => 500],
            ['Name' => 'MpesaReceiptNumber', 'Value' => 'ABC'],
            ['Name' => 'Balance'],
        ]));

        $this->assertSame(500, $flat['TransAmount']);
        $this->assertArrayNotHasKey('OrgAccountBalance', $flat);
    }

    public function test_a_malformed_metadata_block_does_not_throw(): void
    {
        $payload = ['Body' => ['stkCallback' => [
            'CheckoutRequestID' => 'ws_CO_9',
            'ResultCode' => 0,
            'CallbackMetadata' => ['Item' => 'not-an-array'],
        ]]];

        $flat = $this->normaliser->normalise($payload);

        $this->assertSame('ws_CO_9', $flat['TransID']);
    }

    public function test_it_exposes_the_result_description(): void
    {
        $this->assertSame('Cancelled by user', $this->normaliser->resultDescription($this->stk(1032)));
        $this->assertNull($this->normaliser->resultDescription(['TransID' => 'X']));
    }
}
