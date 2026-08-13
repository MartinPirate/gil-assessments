<?php

namespace App\Support;

/**
 * Single source of truth for invoice arithmetic.
 *
 * The Livewire form recalculates on every keystroke and the save path
 * recalculates again before writing, so the rules live here rather than being
 * duplicated in both places — a discrepancy between the two would let a user
 * post totals that do not match their own lines.
 *
 * Net figures exclude VAT, gross figures include it, matching the sample
 * screen's "Price after Discount" vs "Gross Price after Disc." columns.
 */
class InvoiceCalculator
{
    /**
     * Precision, matching the sample document.
     *
     * The screen shows unit prices to 4 d.p. (KES 1,850.0000), discounts to
     * 6 d.p. (5.405405) and totals to 2 d.p. (KES 35,000.00). Money is
     * *computed and stored* at MONEY scale and only *displayed* at TOTAL
     * scale, so a rounded presentation never becomes a rounded stored value.
     */
    public const SCALE = 4;          // unit prices, line and document money
    public const PERCENT_SCALE = 6;  // discount %
    public const TOTAL_SCALE = 2;    // Total (LC), Gross Total (LC), footer
    public const QTY_SCALE = 3;      // quantities

    /**
     * How each field is rendered on screen.
     *
     * @var array<string, int>
     */
    public const DISPLAY_SCALES = [
        'quantity' => self::QTY_SCALE,
        'qty_in_warehouse' => self::QTY_SCALE,
        'price_before_discount' => self::SCALE,
        'price_after_discount' => self::SCALE,
        'gross_price_after_discount' => self::SCALE,
        'discount_percent' => self::PERCENT_SCALE,
        'line_total' => self::TOTAL_SCALE,
        'total' => self::TOTAL_SCALE,
        'gross_total' => self::TOTAL_SCALE,
        'vat_amount' => self::TOTAL_SCALE,
        'total_before_discount' => self::TOTAL_SCALE,
        'total_after_discount' => self::TOTAL_SCALE,
        'total_down_payment' => self::TOTAL_SCALE,
        'freight' => self::TOTAL_SCALE,
        'rounding' => self::TOTAL_SCALE,
        'tax_total' => self::TOTAL_SCALE,
        'document_total' => self::TOTAL_SCALE,
        'applied_amount' => self::TOTAL_SCALE,
        'balance_due' => self::TOTAL_SCALE,
    ];

    /**
     * Format a value the way the document shows it, e.g. "1,850.0000".
     */
    public static function display(string $field, float|string|null $value, bool $thousands = true): string
    {
        $scale = self::DISPLAY_SCALES[$field] ?? self::TOTAL_SCALE;

        return number_format((float) $value, $scale, '.', $thousands ? ',' : '');
    }

    /* -----------------------------------------------------------------
     | Line level
     | ----------------------------------------------------------------- */

    /**
     * Unit price once the line discount % is applied.
     */
    public static function priceAfterDiscount(float $priceBeforeDiscount, float $discountPercent): float
    {
        $discountPercent = self::clampPercent($discountPercent);

        return self::round($priceBeforeDiscount * (1 - ($discountPercent / 100)));
    }

    /**
     * Line total (net of VAT) = quantity x discounted unit price.
     */
    public static function lineTotal(float $quantity, float $priceAfterDiscount): float
    {
        return self::round($quantity * $priceAfterDiscount);
    }

    /**
     * Recompute every derived column of one line from its inputs.
     *
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    public static function recalculateLine(array $line): array
    {
        $quantity = (float) ($line['quantity'] ?? 0);
        $vatRate = max(0.0, (float) ($line['vat_rate'] ?? 0));

        $priceAfter = self::priceAfterDiscount(
            (float) ($line['price_before_discount'] ?? 0),
            (float) ($line['discount_percent'] ?? 0),
        );

        $netTotal = self::lineTotal($quantity, $priceAfter);

        // VAT is computed on the line total, not on the unit price, so that
        // rounding happens once per line rather than once per unit.
        $vatAmount = self::round($netTotal * ($vatRate / 100));

        $line['price_after_discount'] = $priceAfter;
        $line['line_total'] = $netTotal;
        $line['vat_amount'] = $vatAmount;
        $line['gross_price_after_discount'] = self::round($priceAfter * (1 + ($vatRate / 100)));
        $line['gross_total'] = self::round($netTotal + $vatAmount);

        return $line;
    }

    /* -----------------------------------------------------------------
     | Document level
     | ----------------------------------------------------------------- */

    /**
     * Sum of all line totals — the "Total Before Discount" footer field.
     *
     * @param  iterable<array<string, mixed>>  $lines
     */
    public static function totalBeforeDiscount(iterable $lines): float
    {
        return self::sumLines($lines, 'line_total');
    }

    /**
     * Sum of line VAT, before any document-level discount is applied.
     *
     * @param  iterable<array<string, mixed>>  $lines
     */
    public static function taxBeforeDiscount(iterable $lines): float
    {
        return self::sumLines($lines, 'vat_amount');
    }

    /**
     * "Total After Discount" — the document-level discount % applied to the sum.
     */
    public static function totalAfterDiscount(float $totalBeforeDiscount, float $discountPercent): float
    {
        $discountPercent = self::clampPercent($discountPercent);

        return self::round($totalBeforeDiscount * (1 - ($discountPercent / 100)));
    }

    /**
     * Compute the whole totals block in one place.
     *
     * A document discount reduces the taxable base, so the tax total is scaled
     * by the same factor rather than being taken from the lines untouched —
     * otherwise a discounted invoice would over-declare VAT.
     *
     * @param  iterable<array<string, mixed>>  $lines
     * @return array{
     *     total_before_discount: float, total_after_discount: float,
     *     tax_total: float, freight: float, rounding: float,
     *     total_down_payment: float, document_total: float,
     *     applied_amount: float, balance_due: float
     * }
     */
    public static function documentTotals(
        iterable $lines,
        float $discountPercent = 0,
        float $freight = 0,
        float $downPayment = 0,
        float $appliedAmount = 0,
        bool $roundingEnabled = false,
    ): array {
        $before = self::totalBeforeDiscount($lines);
        $after = self::totalAfterDiscount($before, $discountPercent);

        $discountFactor = $before > 0 ? ($after / $before) : 1.0;
        $tax = self::round(self::taxBeforeDiscount($lines) * $discountFactor);

        $freight = max(0.0, self::round($freight));
        $downPayment = max(0.0, self::round($downPayment));

        $gross = self::round($after + $tax + $freight);

        // "Rounding" on the screen is the adjustment to the nearest whole
        // currency unit, stored separately so the figure is auditable.
        $rounding = 0.0;
        if ($roundingEnabled) {
            $rounding = self::round(round($gross) - $gross);
            $gross = self::round($gross + $rounding);
        }

        $documentTotal = self::round(max(0.0, $gross - $downPayment));
        $applied = max(0.0, self::round($appliedAmount));

        return [
            'total_before_discount' => $before,
            'total_after_discount' => $after,
            'tax_total' => $tax,
            'freight' => $freight,
            'rounding' => $rounding,
            'total_down_payment' => $downPayment,
            'document_total' => $documentTotal,
            'applied_amount' => $applied,
            // Never negative: an overpayment is tracked on the receipt, not as
            // a negative balance on the invoice.
            'balance_due' => self::round(max(0.0, $documentTotal - $applied)),
        ];
    }

    /* -----------------------------------------------------------------
     | Helpers
     | ----------------------------------------------------------------- */

    /**
     * A repeater row the user has added but not filled in yet. The sample
     * screen always shows one such trailing row, and it must not be saved
     * or counted.
     *
     * @param  array<string, mixed>  $line
     */
    public static function isBlankLine(array $line): bool
    {
        $hasIdentity = filled($line['item_no'] ?? null) || filled($line['item_description'] ?? null);
        $hasNumbers = (float) ($line['quantity'] ?? 0) > 0
            || (float) ($line['price_before_discount'] ?? 0) > 0;

        return ! $hasIdentity && ! $hasNumbers;
    }

    public static function round(float $value): float
    {
        return round($value, self::SCALE);
    }

    /**
     * @param  iterable<array<string, mixed>>  $lines
     */
    protected static function sumLines(iterable $lines, string $column): float
    {
        $total = 0.0;

        foreach ($lines as $line) {
            if (! is_array($line) || self::isBlankLine($line)) {
                continue;
            }

            $total += (float) (self::recalculateLine($line)[$column] ?? 0);
        }

        return self::round($total);
    }

    /**
     * Discounts outside 0–100 would invert or inflate the total. Validation
     * rejects >50 with a message, but the maths must stay sane regardless of
     * how the value got here.
     */
    private static function clampPercent(float $percent): float
    {
        return max(0.0, min(100.0, $percent));
    }
}
