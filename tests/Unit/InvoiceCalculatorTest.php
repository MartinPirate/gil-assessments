<?php

namespace Tests\Unit;

use App\Support\InvoiceCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Pure arithmetic — no database, so this runs fast and pins the money rules.
 */
class InvoiceCalculatorTest extends TestCase
{
    public function test_price_after_discount_applies_the_percentage(): void
    {
        $this->assertSame(1278.0, InvoiceCalculator::priceAfterDiscount(1420, 10));
        $this->assertSame(1850.0, InvoiceCalculator::priceAfterDiscount(1850, 0));
        $this->assertSame(925.0, InvoiceCalculator::priceAfterDiscount(1850, 50));
    }

    public function test_money_is_held_to_four_decimal_places(): void
    {
        // Matches the sample document, which shows KES 1,850.0000.
        $this->assertSame(1750.0, InvoiceCalculator::priceAfterDiscount(1850, 5.405405));
        $this->assertSame(33.3333, InvoiceCalculator::round(33.3333333));
    }

    public function test_each_field_displays_at_its_documented_precision(): void
    {
        // Unit prices 4 d.p., discounts 6 d.p., totals 2 d.p.
        $this->assertSame('1,850.0000', InvoiceCalculator::display('price_before_discount', 1850));
        $this->assertSame('5.405405', InvoiceCalculator::display('discount_percent', 5.405405));
        $this->assertSame('35,000.00', InvoiceCalculator::display('line_total', 35000));
        $this->assertSame('35000.00', InvoiceCalculator::display('document_total', 35000, thousands: false));
    }

    public function test_line_total_is_quantity_times_discounted_price(): void
    {
        $this->assertSame(15336.0, InvoiceCalculator::lineTotal(12, 1278));
    }

    public function test_recalculate_line_fills_derived_columns(): void
    {
        $line = InvoiceCalculator::recalculateLine([
            'quantity' => 20,
            'price_before_discount' => 1850,
            'discount_percent' => 0,
        ]);

        $this->assertSame(1850.0, $line['price_after_discount']);
        $this->assertSame(37000.0, $line['line_total']);
    }

    public function test_totals_ignore_blank_trailing_rows(): void
    {
        $lines = [
            ['quantity' => 2, 'price_before_discount' => 100, 'discount_percent' => 0],
            // The empty row the grid always shows must not count.
            ['quantity' => null, 'price_before_discount' => null, 'discount_percent' => 0],
        ];

        $this->assertSame(200.0, InvoiceCalculator::totalBeforeDiscount($lines));
    }

    public function test_document_discount_applies_to_the_sum(): void
    {
        $this->assertSame(900.0, InvoiceCalculator::totalAfterDiscount(1000, 10));
        $this->assertSame(1000.0, InvoiceCalculator::totalAfterDiscount(1000, 0));
    }

    public function test_out_of_range_discounts_cannot_invert_the_total(): void
    {
        // Validation rejects >50 with a message, but the maths must stay sane
        // regardless of how a value reached the calculator.
        $this->assertSame(0.0, InvoiceCalculator::totalAfterDiscount(1000, 150));
        $this->assertSame(1000.0, InvoiceCalculator::totalAfterDiscount(1000, -20));
    }

    public function test_blank_line_detection(): void
    {
        $this->assertTrue(InvoiceCalculator::isBlankLine([]));
        $this->assertTrue(InvoiceCalculator::isBlankLine(['quantity' => 0, 'item_no' => null]));
        $this->assertFalse(InvoiceCalculator::isBlankLine(['item_no' => 'FG00011']));
        $this->assertFalse(InvoiceCalculator::isBlankLine(['quantity' => 1]));
    }
}
