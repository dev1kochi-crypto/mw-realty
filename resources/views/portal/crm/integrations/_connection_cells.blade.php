{{-- Status / leads / last-lead cells of a connected Facebook Page row (integrations/index). --}}
<td>
    @if($connection->needs_reconnect_at)
    <span class="intg-status intg-status--warn"><i class="fas fa-plug-circle-xmark"></i>Reconnect needed</span>
    <div class="small text-danger mt-1" style="max-width: 300px;">
        Stopped {{ $connection->needs_reconnect_at->diffForHumans() }} — {{ \Illuminate\Support\Str::limit($connection->last_error, 140) }}
    </div>
    @if($connection->reconnect_notified_at)<div class="small text-muted">Owner emailed {{ $connection->reconnect_notified_at->format('d M Y') }}</div>@endif
    @elseif($connection->last_error)
    <span class="intg-status intg-status--warn" title="{{ $connection->last_error }}"><i class="fas fa-triangle-exclamation"></i>Needs attention</span>
    <div class="small text-danger mt-1" style="max-width: 280px;">{{ \Illuminate\Support\Str::limit($connection->last_error, 120) }}</div>
    @elseif($connection->subscribed_at)
    <span class="intg-status intg-status--ok"><i class="fas fa-circle-check"></i>Receiving leads</span>
    @else
    <span class="intg-status intg-status--off">Not subscribed</span>
    @endif

    {{-- Background lead import (ImportFacebookPageLeads) — updated live by the page's poller. --}}
    @if($connection->needs_reconnect_at)
    {{-- No import progress while access is broken — the reconnect message above says what to do. --}}
    @elseif($connection->importInProgress())
    <div class="fbimport js-import" data-id="{{ $connection->id }}">
        <div class="fbimport__label"><span class="fbimport__spin"></span><span class="js-import-text">{{ $connection->import_status === 'queued' ? 'Starting lead import…' : "Importing leads… {$connection->import_added} added" }}</span></div>
        <div class="fbbar"><span></span></div>
    </div>
    @elseif($connection->import_status === 'done' && $connection->import_finished_at?->gt(now()->subDays(3)))
    <div class="small text-success mt-1"><i class="fas fa-circle-check me-1"></i>Imported {{ number_format($connection->import_added) }} lead{{ $connection->import_added === 1 ? '' : 's' }}@if($connection->import_skipped), {{ number_format($connection->import_skipped) }} already in the CRM @endif</div>
    @elseif($connection->import_status === 'empty' && $connection->import_finished_at?->gt(now()->subDays(3)))
    <div class="small text-muted mt-1" style="max-width: 300px;"><i class="fas fa-inbox me-1"></i>{{ $connection->import_error ?: 'No leads to import.' }}</div>
    @elseif($connection->import_status === 'failed' && $connection->import_finished_at?->gt(now()->subDays(3)))
    <div class="small text-danger mt-1" style="max-width: 300px;"><i class="fas fa-circle-xmark me-1"></i>Lead import stopped: {{ \Illuminate\Support\Str::limit($connection->import_error, 120) }}</div>
    @endif
</td>
<td class="text-center fw-semibold">{{ number_format($connection->leads_count) }}</td>
<td class="small">
    {{ $connection->last_lead_at?->diffForHumans() ?? '—' }}
    @if($connection->last_synced_at)<div class="text-muted">Synced {{ $connection->last_synced_at->diffForHumans() }}</div>@endif
</td>
