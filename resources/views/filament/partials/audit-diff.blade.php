<div class="audit-diff">
    <dl class="audit-diff__meta">
        <div><dt>When</dt><dd>{{ $log->created_at->format('d/m/Y H:i:s') }}</dd></div>
        <div><dt>Who</dt><dd>{{ $log->user_name ?? 'system' }} @if($log->user_role)<em>({{ $log->user_role }})</em>@endif</dd></div>
        <div><dt>IP</dt><dd>{{ $log->ip_address ?? '—' }}</dd></div>
        <div><dt>URL</dt><dd class="audit-diff__url">{{ $log->url ?? '—' }}</dd></div>
    </dl>

    @if (empty($log->changes_list))
        <p class="audit-diff__empty">No field-level changes were recorded.</p>
    @else
        <table class="audit-diff__table">
            <thead><tr><th>Field</th><th>From</th><th>To</th></tr></thead>
            <tbody>
                @foreach ($log->changes_list as $change)
                    <tr>
                        <td><code>{{ $change['field'] }}</code></td>
                        <td class="audit-diff__from">{{ is_scalar($change['from']) ? ($change['from'] ?? '—') : json_encode($change['from']) }}</td>
                        <td class="audit-diff__to">{{ is_scalar($change['to']) ? ($change['to'] ?? '—') : json_encode($change['to']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
