@php $sessions = $this->getSessions(); @endphp

@if ($sessions->isEmpty())
    <p class="usr-empty">This account has never signed in.</p>
@else
    <ul class="usr-sessions">
        @foreach ($sessions as $session)
            <li class="usr-session">
                <span class="usr-session__dot {{ $session->logged_out_at ? '' : 'usr-session__dot--live' }}"
                      aria-hidden="true"></span>

                <span class="usr-session__body">
                    <span class="usr-session__when">
                        {{ $session->logged_in_at?->format('d/m/Y H:i') }}
                        @unless ($session->logged_out_at)
                            <span class="usr-session__live">still active</span>
                        @endunless
                    </span>
                    <span class="usr-session__meta">
                        {{ $session->ip_address ?? 'unknown address' }}
                        @if ($session->logged_out_at)
                            &middot; {{ $session->logged_in_at->diffForHumans($session->logged_out_at, true) }}
                        @endif
                    </span>
                </span>
            </li>
        @endforeach
    </ul>
@endif
