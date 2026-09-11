@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.portal-accounts.index') }}" class="text-decoration-none text-muted">Clients</a></li>
    <li class="breadcrumb-item active" aria-current="page">Plan Requests</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold">Plan Upgrade Requests</h4>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Current Plan</th>
                        <th>Requested Plan</th>
                        <th>Requested</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                    @php($portalUser = $req->portalUser)
                    <tr data-id="{{ $req->id }}">
                        <td>
                            <a href="{{ route('cms.portal-accounts.show', ['id' => $portalUser->id, 'type' => $portalUser->type]) }}" class="text-decoration-none fw-semibold">
                                {{ $portalUser->type === 'company' ? ($portalUser->company_name ?: $portalUser->name) : $portalUser->name }}
                            </a>
                            <div class="text-muted small">{{ ucfirst($portalUser->type) }}</div>
                        </td>
                        <td>{{ $req->currentPlan?->getTranslation('name') ?? 'No Plan' }}</td>
                        <td class="fw-semibold">{{ $req->plan->getTranslation('name') }}</td>
                        <td>{{ $req->requested_at->format('d M Y H:i') }}</td>
                        <td class="text-end">
                            @can('portal-accounts.edit')
                            <button type="button" class="btn btn-sm btn-success approve-request-btn" data-id="{{ $req->id }}">Approve</button>
                            <button type="button" class="btn btn-sm btn-outline-danger reject-request-btn" data-id="{{ $req->id }}">Reject</button>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No pending plan requests.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $requests->links() }}</div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function postAction(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(data || {}),
        }).then(r => r.json());
    }

    const approveUrl = @json(route('cms.portal-accounts.plan-upgrade-requests.approve', ['id' => '__ID__']));
    const rejectUrl = @json(route('cms.portal-accounts.plan-upgrade-requests.reject', ['id' => '__ID__']));

    document.querySelectorAll('.approve-request-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('Approve this plan upgrade request?')) return;
            postAction(approveUrl.replace('__ID__', btn.dataset.id)).then(function (data) {
                if (data.success) window.location.reload();
            });
        });
    });

    document.querySelectorAll('.reject-request-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('Reject this plan upgrade request?')) return;
            postAction(rejectUrl.replace('__ID__', btn.dataset.id)).then(function (data) {
                if (data.success) window.location.reload();
            });
        });
    });
});
</script>
@endpush
