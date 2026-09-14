@extends('portal.crm._layout')

@section('title', 'Deleted Leads')

@section('crm-content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="portal-section-title mb-0">Deleted Leads</div>
        <p class="text-muted mb-0" style="font-size: 0.85rem;">Restore a lead to bring it back, or permanently delete it.</p>
    </div>
    <a href="{{ route('portal.crm.leads.index') }}" class="portal-btn-ghost btn btn-sm">Back to Leads</a>
</div>

<div class="portal-card p-3 p-md-4">
    <div class="table-responsive">
        <table class="table portal-table mb-0">
            <thead>
                <tr>
                    <th>Lead</th>
                    <th>Property</th>
                    @if($isAdmin)
                    <th>Owner</th>
                    @endif
                    <th>Stage</th>
                    <th>Deleted</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $lead->name ?: 'Unknown' }}</div>
                        <div class="text-muted" style="font-size: 0.78rem;">{{ $lead->email ?: $lead->formatted_phone ?: '-' }}</div>
                    </td>
                    <td>{{ $lead->property?->getTranslation('title') ?? '-' }}</td>
                    @if($isAdmin)
                    <td>{{ $lead->owner?->displayName() ?? '-' }}</td>
                    @endif
                    <td>{{ $lead->stage?->name ?? '-' }}</td>
                    <td class="text-muted">{{ $lead->deleted_at->format('d M Y, H:i') }}</td>
                    <td class="text-end">
                        <form action="{{ route('portal.crm.leads.restore', $lead->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm portal-btn-ghost">Restore</button>
                        </form>
                        <button type="button" class="btn btn-sm btn-outline-danger force-delete-lead-btn" data-id="{{ $lead->id }}">Delete Permanently</button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $isAdmin ? 6 : 5 }}" class="portal-empty">No deleted leads.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $leads->links() }}</div>

<form id="forceDeleteLeadForm" method="POST" class="d-none">
    @csrf
    @method('DELETE')
</form>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.force-delete-lead-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Permanently delete this lead? This cannot be undone.')) return;
                const form = document.getElementById('forceDeleteLeadForm');
                form.action = "{{ url('portal/crm/leads') }}/" + btn.dataset.id + '/force';
                form.submit();
            });
        });
    });
</script>
@endpush
@endsection
