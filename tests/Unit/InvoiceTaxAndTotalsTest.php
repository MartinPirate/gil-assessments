<?php

namespace Tests\Unit;

use App\Support\InvoiceCalculator;
use PHPUnit\Framework\TestCase;

/**
 * VAT and the document totals block.
 */
class InvoiceTaxAndTotalsTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function line(array $overrides = []): array
    {
        return array_merge([
            'item_no' => 'FG00011',
            'quantity' => 20,
            'price_before_discount' => 1850,
            'discount_percent' => 0,
            'vat_rate' => 16,
        ], $overrides);
    }

    public function test_vat_is_computed_on_the_line_total(): void
    {
        $line = InvoiceCalculator::recalculateLine($this->line());

        $this->assertSame(37000.0, $line['line_total']);
        $this->assertSame(5920.0, $line['vat_amount']);
        $this->assertSame(42920.0, $line['gross_total']);
        $this->assertSame(2146.0, $line['gross_price_after_discount']);
    }

    public function test_a_zero_rated_line_carries_no_tax(): void
    {
        $line = InvoiceCalculator::recalculateLine($this->line(['vat_rate' => 0]));

        $this->assertSame(0.0, $line['vat_amount']);
        $this->assertSame($line['line_total'], $line['gross_total']);
    }

    public function test_the_totals_block_adds_tax_and_freight(): void
    {
        $totals = InvoiceCalculator::documentTotals([$this->line()], freight: 1000);

        $this->assertSame(37000.0, $totals['total_before_discount']);
        $this->assertSame(37000.0, $totals['total_after_discount']);
        $this->assertSame(5920.0, $totals['tax_total']);
        $this->assertSame(1000.0, $totals['freight']);
        $this->assertSame(43920.0, $totals['document_total']);
        $this->assertSame(43920.0, $totals['balance_due']);
    }

    /**
     * A document discount reduces the taxable base, so VAT has to fall with
     * it — otherwise the invoice over-declares tax to KRA.
     */
    public function test_a_document_discount_scales_the_tax(): void
    {
        $totals = InvoiceCalculator::documentTotals([$this->line()], discountPercent: 10);

        $this->assertSame(33300.0, $totals['total_after_discount']);
        $this->assertSame(5328.0, $totals['tax_total']);   // 5920 x 0.9
        $this->assertSame(38628.0, $totals['document_total']);
    }

    public function test_a_down_payment_reduces_the_document_total(): void
    {
        $totals = InvoiceCalculator::documentTotals([$this->line()], downPayment: 20000);

        $this->assertSame(22920.0, $totals['document_total']);
        $this->assertSame(22920.0, $totals['balance_due']);
    }

    public function test_applied_amounts_reduce_the_balance_but_not_the_total(): void
    {
        $totals = InvoiceCalculator::documentTotals([$this->line()], appliedAmount: 20000);

        $this->assertSame(42920.0, $totals['document_total']);
        $this->assertSame(22920.0, $totals['balance_due']);
    }

    /**
     * An overpayment belongs on the receipt, not as a negative receivable.
     */
    public function test_the_balance_never_goes_negative(): void
    {
        $totals = InvoiceCalculator::documentTotals([$this->line()], appliedAmount: 99999);

        $this->assertSame(0.0, $totals['balance_due']);
    }

    public function test_rounding_adjusts_to_the_nearest_whole_unit(): void
    {
        $line = $this->line(['quantity' => 1, 'price_before_discount' => 100.4, 'vat_rate' => 0]);

        $without = InvoiceCalculator::documentTotals([$line]);
        $with = InvoiceCalculator::documentTotals([$line], roundingEnabled: true);

        $this->assertSame(100.4, $without['document_total']);
        $this->assertSame(-0.4, $with['rounding']);
        $this->assertSame(100.0, $with['document_total']);
    }

    public function test_rounding_is_zero_when_disabled(): void
    {
        $totals = InvoiceCalculator::documentTotals([$this->line()]);

        $this->assertSame(0.0, $totals['rounding']);
    }
}
