@extends('portal.layouts.app')

@section('title', 'My Agency')

@section('content')
<div class="portal-section-title mb-3">My Agency</div>

@foreach($invitations as $invitation)
<div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div><i class="fas fa-envelope-open-text me-2"></i><strong>{{ $invitation->agency->displayName() }}</strong> invited you to join their agency ({{ $invitation->invited_at?->diffForHumans() }}).</div>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('portal.agency.invitations.accept', $invitation->id) }}" class="m-0">@csrf
            <button class="btn btn-sm btn-success" @disabled($agent->isAgencyAgent())>Accept</button>
        </form>
        <form method="POST" action="{{ route('portal.agency.invitations.decline', $invitation->id) }}" class="m-0">@csrf
            <button class="btn btn-sm btn-outline-danger">Decline</button>
        </form>
    </div>
</div>
@endforeach

<div class="row g-3">
    <div class="col-lg-6">
        <div class="portal-card p-4 h-100">
            @if($current)
                <div class="portal-muted small">Current agency</div>
                <div class="fs-5 fw-bold mb-1">{{ $current->agency->displayName() }}</div>
                <div class="mb-3">
                    <span class="badge bg-{{ $current->statusTone() }}-subtle text-{{ $current->statusTone() }}-emphasis border">{{ $current->statusLabel() }}</span>
                    <span class="portal-muted small ms-2">since {{ $current->joined_at?->format('d M Y') }}</span>
                </div>
                <p class="portal-muted small">You work the agency's listings assigned to you and the enquiries routed to you. Listings you add now belong to the agency and use its plan.</p>
                <form method="POST" action="{{ route('portal.agency.leave') }}" onsubmit="return confirm('Leave {{ e($current->agency->displayName()) }}? You will become an independent agent. The agency keeps its listings and leads.');">
                    @csrf
                    <button class="btn btn-outline-danger btn-sm"><i class="fas fa-right-from-bracket me-1"></i> Leave agency</button>
                </form>
            @else
                <div class="portal-muted small">Current agency</div>
                <div class="fs-5 fw-bold mb-3">Independent agent</div>

                @if($openRequest)
                    <div class="alert alert-warning mb-0">
                        Your request to join <strong>{{ $openRequest->agency->displayName() }}</strong> is {{ strtolower($openRequest->statusLabel()) }}.
                        @if($openRequest->initiated_by === 'agent')
                        <form method="POST" action="{{ route('portal.agency.requests.cancel', $openRequest->id) }}" class="d-inline">@csrf
                            <button class="btn btn-link btn-sm p-0 ms-1">Withdraw</button>
                        </form>
                        @endif
                    </div>
                @elseif($agent->isApproved())
                    <form method="POST" action="{{ route('portal.agency.request') }}">
                        @csrf
                        <label class="form-label fw-semibold">Request to join an agency</label>
                        <div class="d-flex gap-2">
                            <select name="agency_id" id="agencyPicker" class="form-select" required style="max-width: 360px;"></select>
                            <button type="submit" class="btn btn-portal-primary">Send request</button>
                        </div>
                        <div class="form-text">The agency accepts, then Super Admin approves. Your account, listings and history stay yours.</div>
                    </form>
                @else
                    <p class="portal-muted small mb-0">Once your account is approved you can request to join an agency, or accept an agency's invitation.</p>
                @endif
            @endif
        </div>
    </div>

    <div class="col-lg-6">
        <div class="portal-card p-4 h-100">
            <div class="fw-bold mb-2">Agency history</div>
            <ul class="list-unstyled small mb-0">
                @forelse($history as $row)
                <li class="mb-2">
                    <strong>{{ $row->agency->displayName() }}</strong>
                    <span class="badge bg-{{ $row->statusTone() }}-subtle text-{{ $row->statusTone() }}-emphasis border ms-1">{{ $row->statusLabel() }}</span>
                    <div class="portal-muted">
                        {{ $row->created_at->format('d M Y') }}
                        @if($row->joined_at) · joined {{ $row->joined_at->format('d M Y') }}@endif
                        @if($row->left_at) · left {{ $row->left_at->format('d M Y') }}@endif
                    </div>
                </li>
                @empty
                <li class="portal-muted">No agency history yet.</li>
                @endforelse
            </ul>
            {{ $history->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@if($personalProperties && $personalProperties->total())
<div class="portal-card p-4 mt-3">
    <div class="fw-bold mb-1">Your personal listings</div>
    <div class="portal-muted small mb-3">These stayed yours when you joined. You can optionally move one into your agency (you remain its agent; it then counts toward the agency's plan and stays with the agency if you leave).</div>
    <table class="table portal-table mb-0">
        <thead><tr><th>Ref</th><th>Title</th><th class="text-end">Action</th></tr></thead>
        <tbody>
            @foreach($personalProperties as $property)
            <tr>
                <td>{{ $property->reference_no }}</td>
                <td>{{ $property->getTranslation('title') ?? '—' }}</td>
                <td class="text-end">
                    <form method="POST" action="{{ route('portal.agency.properties.transfer', $property->id) }}" class="d-inline" onsubmit="return confirm('Transfer this listing to your agency? This cannot be undone from your side.');">@csrf
                        <button class="btn btn-sm portal-btn-ghost">Transfer to agency</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="mt-2">{{ $personalProperties->links('pagination::bootstrap-5') }}</div>
</div>
@endif
@endsection

@push('scripts')
{{-- Agency picker searches the server 20 at a time and loads more on scroll — never the full list. --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>
<script>
    (function () {
        var picker = document.getElementById('agencyPicker');
        if (!picker || !window.jQuery) return;
        jQuery(picker).select2({
            placeholder: 'Search agencies…',
            width: '100%',
            ajax: {
                url: "{{ route('portal.agency.search') }}",
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term || '', page: params.page || 1 }; },
                processResults: function (data) { return data; },
            },
        });
    })();
</script>
@endpush
