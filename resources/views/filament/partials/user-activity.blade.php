@php $entries = $this->getActivity(); @endphp

@if ($entries->isEmpty())
    <p class="usr-empty">This account has not changed anything yet.</p>
@else
    <ol class="usr-activity">
        @foreach ($entries as $entry)
            <li class="usr-activity__item">
                <span class="usr-activity__event usr-activity__event--{{ \Illuminate\Support\Str::slug($entry->event) }}">
                    {{ ucfirst($entry->event) }}
                </span>

                <span class="usr-activity__what">
                    {{ $entry->auditable_label ?: class_basename($entry->auditable_type ?? '') }}
                </span>

                <time class="usr-activity__when" datetime="{{ $entry->created_at?->toIso8601String() }}">
                    {{ $entry->created_at?->diffForHumans() }}
                </time>
            </li>
        @endforeach
    </ol>
@endif
