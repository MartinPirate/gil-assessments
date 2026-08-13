<?php

namespace App\Support\Timeline;

use App\Models\OrderStageEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LaBoiteACode\FilamentActivityTimeline\Contracts\ActivitySource;
use LaBoiteACode\FilamentActivityTimeline\Data\TimelineEntry;
use LaBoiteACode\FilamentActivityTimeline\Data\TimelineResult;

/**
 * Feeds the order lifecycle into the activity timeline.
 *
 * The plugin ships a Spatie activity-log source, which reports raw model
 * changes — "status: Open -> Closed". That is a record of edits, not of the
 * business process. This source reads the stage events instead, so the
 * timeline shows what happened to the order rather than which column moved.
 */
class OrderStageSource implements ActivitySource
{
    protected ?Model $record = null;

    /** @var list<string> */
    protected array $events = [];

    protected bool $latestFirst = true;

    /**
     * The contract promises immutability, so every configuration call works on
     * a copy. Sharing one instance between two timelines on the same page would
     * otherwise let the second re-scope the first.
     */
    public function forRecord(Model $record): static
    {
        $clone = clone $this;
        $clone->record = $record;

        return $clone;
    }

    public function events(array $events): static
    {
        $clone = clone $this;
        $clone->events = $events;

        return $clone;
    }

    public function latestFirst(bool $condition = true): static
    {
        $clone = clone $this;
        $clone->latestFirst = $condition;

        return $clone;
    }

    public function paginate(int $perPage, ?string $cursor = null): TimelineResult
    {
        if ($this->record === null) {
            return TimelineResult::empty();
        }

        $query = OrderStageEvent::query()
            ->with('causer')
            ->where('invoice_id', $this->record->getKey());

        if ($this->events !== []) {
            $query->whereIn('stage', $this->events);
        }

        $query
            ->orderBy('occurred_at', $this->latestFirst ? 'desc' : 'asc')
            ->orderBy('id', $this->latestFirst ? 'desc' : 'asc');

        // Read one more than asked for: if it comes back, there is another page.
        $offset = $cursor !== null ? max(0, (int) $cursor) : 0;
        $rows = $query->skip($offset)->take($perPage + 1)->get();

        $hasMore = $rows->count() > $perPage;
        $rows = $rows->take($perPage);

        $entries = $rows
            ->map(fn (OrderStageEvent $event): TimelineEntry => new TimelineEntry(
                id: $event->getKey(),
                event: $event->stage->value,
                title: $event->stage->label(),
                description: $event->note ?: $event->stage->description(),
                causer: $event->causer,
                subject: $this->record,
                subjectType: $this->record::class,
                subjectId: $this->record->getKey(),
                properties: $event->meta ?? [],
                occurredAt: CarbonImmutable::parse($event->occurred_at),
                icon: $event->stage->icon(),
                color: $event->stage->color(),
            ))
            ->values()
            ->all();

        return new TimelineResult(
            entries: $entries,
            hasMore: $hasMore,
            nextCursor: $hasMore ? (string) ($offset + $perPage) : null,
        );
    }
}
