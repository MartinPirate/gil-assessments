<?php

namespace App\Enums;

/**
 * The stages an order passes through, from raised to rated.
 *
 * These are business milestones, not a mirror of `invoices.status`. A status
 * answers "what is this document now"; a stage answers "what has happened to
 * this order, and when". The two differ on purpose: a Closed invoice does not
 * say whether the goods were ever delivered, and a delivered order does not
 * stop being Closed.
 *
 * Order is given explicitly by {@see self::position()} rather than by the
 * declaration order, so a stage can be inserted later without renumbering a
 * table of stored rows.
 */
enum OrderStage: string
{
    case Placed = 'placed';
    case Approved = 'approved';
    case Paid = 'paid';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Rated = 'rated';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Placed => 'Placed',
            self::Approved => 'Approved',
            self::Paid => 'Paid',
            self::Dispatched => 'Dispatched',
            self::Delivered => 'Delivered',
            self::Rated => 'Rated',
            self::Cancelled => 'Cancelled',
        };
    }

    /** The sentence the timeline shows for the stage. */
    public function description(): string
    {
        return match ($this) {
            self::Placed => 'The invoice was raised and posted to the customer account.',
            self::Approved => 'The document cleared the approval queue.',
            self::Paid => 'Payment settled the document in full.',
            self::Dispatched => 'The vehicle departed with the goods.',
            self::Delivered => 'The vehicle arrived and the goods were handed over.',
            self::Rated => 'The customer rated the delivery.',
            self::Cancelled => 'The order was cancelled.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Placed => 'heroicon-o-document-plus',
            self::Approved => 'heroicon-o-check-badge',
            self::Paid => 'heroicon-o-banknotes',
            self::Dispatched => 'heroicon-o-truck',
            self::Delivered => 'heroicon-o-map-pin',
            self::Rated => 'heroicon-o-star',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Placed => 'gray',
            self::Approved => 'info',
            self::Paid => 'success',
            self::Dispatched => 'warning',
            self::Delivered => 'success',
            self::Rated => 'primary',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Where the stage sits on the track the UI draws.
     *
     * Cancelled is deliberately outside the sequence: it ends an order rather
     * than advancing it, so it is never "the next step".
     */
    public function position(): ?int
    {
        return match ($this) {
            self::Placed => 1,
            self::Approved => 2,
            self::Paid => 3,
            self::Dispatched => 4,
            self::Delivered => 5,
            self::Rated => 6,
            self::Cancelled => null,
        };
    }

    /**
     * The happy path, in order — what the progress track renders.
     *
     * Approval is left out: most documents never breach the threshold, so
     * showing every order a step it will never reach would misreport them
     * as incomplete.
     *
     * @return array<int, self>
     */
    public static function track(): array
    {
        return [self::Placed, self::Paid, self::Dispatched, self::Delivered, self::Rated];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $stage) => [$stage->value => $stage->label()])
            ->all();
    }
}
