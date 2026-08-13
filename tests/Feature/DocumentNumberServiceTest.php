<?php

namespace Tests\Feature;

use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The auto-incremented "No." on the A/R Invoice header.
 */
class DocumentNumberServiceTest extends TestCase
{
    // Truncation rather than RefreshDatabase: these tests open real
    // transactions, and RefreshDatabase would already have one open, hiding
    // the very behaviour under test.
    use DatabaseTruncation;

    protected DocumentNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DocumentNumberService::class);
    }

    public function test_numbers_start_at_one_and_increment(): void
    {
        foreach ([1, 2, 3] as $expected) {
            $number = DB::transaction(fn () => $this->service->next(DocumentNumberService::AR_INVOICE, 'IN'));
            $this->assertSame($expected, $number);
        }
    }

    public function test_each_series_has_its_own_counter(): void
    {
        DB::transaction(fn () => $this->service->next(DocumentNumberService::AR_INVOICE, 'IN'));
        DB::transaction(fn () => $this->service->next(DocumentNumberService::AR_INVOICE, 'IN'));

        $other = DB::transaction(fn () => $this->service->next(DocumentNumberService::AR_INVOICE, 'CR'));

        $this->assertSame(1, $other);
    }

    public function test_peek_reports_the_next_number_without_consuming_it(): void
    {
        $this->assertSame(1, $this->service->peek(DocumentNumberService::AR_INVOICE, 'IN'));
        $this->assertSame(1, $this->service->peek(DocumentNumberService::AR_INVOICE, 'IN'));

        DB::transaction(fn () => $this->service->next(DocumentNumberService::AR_INVOICE, 'IN'));

        $this->assertSame(2, $this->service->peek(DocumentNumberService::AR_INVOICE, 'IN'));
    }

    /**
     * The row lock is only held for the life of the caller's transaction, so
     * calling this outside one would hand the same number to two requests.
     * Failing loudly is better than silently issuing duplicates.
     */
    public function test_it_refuses_to_issue_a_number_outside_a_transaction(): void
    {
        $this->expectException(\LogicException::class);

        $this->service->next(DocumentNumberService::AR_INVOICE, 'IN');
    }

    public function test_numbers_are_never_reused_across_many_sequential_calls(): void
    {
        $numbers = [];

        for ($i = 0; $i < 25; $i++) {
            $numbers[] = DB::transaction(fn () => $this->service->next(DocumentNumberService::AR_INVOICE, 'IN'));
        }

        $this->assertSame($numbers, array_unique($numbers));
        $this->assertSame(range(1, 25), $numbers);
    }
}
