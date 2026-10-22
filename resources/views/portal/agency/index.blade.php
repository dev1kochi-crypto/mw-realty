@extends('portal.layouts.app')

@section('title', 'My Agency')

@section('content')
@php
    // What joining does to the agent's own plan (their request, or an agency's invitation — both switch).
    $ownPlan = $agent->plan;
    $ownIsPaid = $ownPlan && (float) $ownPlan->price > 0;
    $planNote = function (?string $agencyName = null) use ($agent, $ownPlan, $ownIsPaid) {
        $agencyPart = $agencyName ? e($agencyName) . "'s plan" : "the agency's plan";
        if (!$ownIsPaid) {
            return "Once approved you move onto {$agencyPart} — no plan to buy.";
        }
        $ends = $agent->hasStripeSubscription() && $agent->subscription_renews_at
            ? ' It won\'t renew — it ends ' . $agent->subscription_renews_at->format('d M Y') . ' and you won\'t be charged again.'
            : '';

        return "Once approved you move onto {$agencyPart}. Your own <strong>" . e($ownPlan->getTranslation('name')) . '</strong> plan stops — <strong>no refund</strong> for the current period.' . $ends;
    };
@endphp
<div class="portal-section-title mb-3">My Agency</div>

@foreach($invitations as $invitation)
<div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
        <i class="fas fa-envelope-open-text me-2"></i><strong>{{ $invitation->agency->displayName() }}</strong> invited you to join their agency ({{ $invitation->invited_at?->diffForHumans() }}).
        <div class="small mt-1"><i class="fas fa-layer-group me-1"></i>{!! $planNote($invitation->agency->displayName()) !!}</div>
    </div>
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
                @php $agencyPlan = $current->agency->plan; @endphp
                <div class="small mb-3 p-2 rounded" style="background: var(--portal-bg);">
                    <i class="fas fa-layer-group me-1"></i>
                    @if($agent->isOnAgencyPlan())
                        On <strong>{{ $current->agency->displayName() }}'s {{ $agencyPlan?->getTranslation('name') }}</strong> plan
                        @if($current->previousPlan) · switched from your own <strong>{{ $current->previousPlan->getTranslation('name') }}</strong> plan on {{ $current->plan_switched_at?->format('d M Y') }} (no refund)@endif
                    @else
                        On your own <strong>{{ $ownPlan?->getTranslation('name') ?? 'Free' }}</strong> plan — {{ $current->agency->displayName() }}'s plan doesn't include team agents.
                    @endif
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
                        @if($openRequest->status === \App\Models\AgencyAgent::PENDING)
                            <strong>{{ $openRequest->agency->displayName() }}</strong> accepted — waiting for Super Admin approval.
                        @else
                            Your request to join <strong>{{ $openRequest->agency->displayName() }}</strong> is waiting for the agency to accept.
                        @endif
                        @if($openRequest->initiated_by === 'agent')
                        <form method="POST" action="{{ route('portal.agency.requests.cancel', $openRequest->id) }}" class="d-inline">@csrf
                            <button class="btn btn-link btn-sm p-0 ms-1">Withdraw</button>
                        </form>
                        @endif
                        <div class="small mt-2"><i class="fas fa-layer-group me-1"></i>{!! $planNote($openRequest->agency->displayName()) !!}</div>
                    </div>
                @elseif($agent->isApproved())
                    <form method="POST" action="{{ route('portal.agency.request') }}" class="agency-join">
                        @csrf
                        <label class="form-label fw-semibold" for="agencyPicker">Request to join an agency</label>
                        <div class="agency-join__row">
                            <div class="agency-join__picker">
                                <select name="agency_id" id="agencyPicker" class="form-select" required></select>
                            </div>
                            <button type="submit" class="btn btn-portal-primary agency-join__btn"><i class="fas fa-paper-plane me-1"></i>Send request</button>
                        </div>
                        <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i>The agency accepts, then Super Admin approves. Your account, listings and history stay yours.</div>
                        <div class="form-text mt-1 {{ $ownIsPaid ? 'text-warning-emphasis' : '' }}"><i class="fas fa-layer-group me-1"></i>{!! $planNote() !!}</div>
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
<style>
    /* Picker and button on one line at the same height; stacks on narrow screens. */
    .agency-join__row { display: flex; gap: 0.6rem; align-items: stretch; }
    .agency-join__picker { flex: 1 1 auto; min-width: 0; }
    .agency-join__btn { flex: 0 0 auto; white-space: nowrap; height: 44px; padding: 0 1.25rem; display: inline-flex; align-items: center; border-radius: 10px; }
    .agency-join .select2-container--default .select2-selection--single {
        height: 44px; border: 1px solid var(--portal-border, #dee2e6); border-radius: 10px; display: flex; align-items: center;
    }
    .agency-join .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 42px; padding-left: 0.85rem; padding-right: 2rem; color: inherit; }
    .agency-join .select2-container--default .select2-selection--single .select2-selection__placeholder { color: var(--portal-muted, #6c757d); }
    .agency-join .select2-container--default .select2-selection--single .select2-selection__arrow { height: 42px; right: 0.5rem; }
    .agency-join .select2-container--default.select2-container--focus .select2-selection--single,
    .agency-join .select2-container--default.select2-container--open .select2-selection--single { border-color: var(--portal-primary, #244373); box-shadow: 0 0 0 3px rgba(36, 67, 115, 0.12); }
    .select2-dropdown { border-color: var(--portal-border, #dee2e6); border-radius: 10px; overflow: hidden; }
    .select2-search--dropdown .select2-search__field { border-radius: 8px; padding: 0.4rem 0.6rem; }
    @media (max-width: 575.98px) {
        .agency-join__row { flex-direction: column; }
        .agency-join__btn { justify-content: center; }
    }
</style>
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
