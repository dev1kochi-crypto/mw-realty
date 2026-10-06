{{-- Status / leads / last-lead cells of a connected Facebook Page row (integrations/index). --}}
<td>
    @if($connection->last_error)
    <span class="intg-status intg-status--warn" title="{{ $connection->last_error }}"><i class="fas fa-triangle-exclamation"></i>Needs attention</span>
    <div class="small text-danger mt-1" style="max-width: 280px;">{{ \Illuminate\Support\Str::limit($connection->last_error, 120) }}</div>
    @elseif($connection->subscribed_at)
    <span class="intg-status intg-status--ok"><i class="fas fa-circle-check"></i>Receiving leads</span>
    @else
    <span class="intg-status intg-status--off">Not subscribed</span>
    @endif
</td>
<td class="text-center fw-semibold">{{ number_format($connection->leads_count) }}</td>
<td class="small">
    {{ $connection->last_lead_at?->diffForHumans() ?? '—' }}
    @if($connection->last_synced_at)<div class="text-muted">Synced {{ $connection->last_synced_at->diffForHumans() }}</div>@endif
</td>
