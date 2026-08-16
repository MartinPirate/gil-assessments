<?php

namespace App\Services;

use App\Models\Customer;

/**
 * Issues the next business partner code — CC00009, CC00010, and so on.
 *
 * Built on DocumentNumberService rather than on MAX(code) + 1, for the reason
 * that service exists: reading the highest code and adding one races, and two
 * people pressing Create together would both be handed the same one. The
 * counter row is locked for the duration of the surrounding transaction.
 */
class CustomerCodeService
{
    public const PREFIX = 'CC';

    public function __construct(protected DocumentNumberService $numbers) {}

    /**
     * Must be called inside a transaction — the lock is only useful while the
     * insert that uses the code is still open.
     */
    public function next(): string
    {
        $number = $this->numbers->next(
            DocumentNumberService::CUSTOMER,
            self::PREFIX,
            $this->startingPoint(),
        );

        return self::PREFIX.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Where the counter begins on a database that already has customers in it.
     *
     * Only consulted the first time a code is issued; after that the counter
     * row exists and carries the answer itself.
     */
    protected function startingPoint(): int
    {
        $highest = Customer::query()
            ->where('code', 'like', self::PREFIX.'%')
            ->selectRaw('MAX(CAST(SUBSTRING([code], 3, 20) AS INT)) AS highest')
            ->value('highest');

        return ((int) $highest) + 1;
    }
}
