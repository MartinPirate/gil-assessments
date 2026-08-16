<?php

namespace App\Services;

use App\Models\DocumentSeries;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Issues the sequential "No." shown on the AR Invoice header.
 *
 * MAX(doc_num) + 1 is not safe here: two concurrent saves would read the same
 * maximum and both try to insert it. Instead the series row is locked for
 * update inside the caller's transaction, so the second save blocks until the
 * first commits and then sees the incremented value.
 */
class DocumentNumberService
{
    public const string AR_INVOICE = 'AR_INVOICE';

    /**
     * Business partner codes come out of the same counter as documents do —
     * they need the same guarantee, that two people pressing Create at the
     * same moment cannot be handed CC00009 twice.
     */
    public const string CUSTOMER = 'CUSTOMER';

    /**
     * Reserve the next number for a document type/series.
     *
     * Must be called inside a transaction — the lock is only held until the
     * surrounding transaction commits, which is exactly the window in which
     * the document row is inserted.
     */
    public function next(string $documentType, string $series, int $startAt = 1): int
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException(
                'DocumentNumberService::next() must run inside a transaction so the series row stays locked until the document is written.'
            );
        }

        $row = DocumentSeries::query()
            ->where('document_type', $documentType)
            ->where('series', $series)
            ->lockForUpdate()
            ->first();

        if (! $row) {
            // First ever document for this series. The unique index on
            // (document_type, series) is what makes this safe if two requests
            // race to create it; the loser re-reads under the lock.
            try {
                $row = DocumentSeries::create([
                    'document_type' => $documentType,
                    'series' => $series,
                    'next_number' => $startAt,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                $row = DocumentSeries::query()
                    ->where('document_type', $documentType)
                    ->where('series', $series)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }

        $number = (int) $row->next_number;

        // Atomic increment rather than $row->next_number++ / save().
        DocumentSeries::query()
            ->whereKey($row->getKey())
            ->update(['next_number' => DB::raw('next_number + 1')]);

        return $number;
    }

    public function peek(string $documentType, string $series, int $startAt = 1): int
    {
        $row = DocumentSeries::query()
            ->where('document_type', $documentType)
            ->where('series', $series)
            ->first();

        return (int) ($row->next_number ?? $startAt);
    }
}
