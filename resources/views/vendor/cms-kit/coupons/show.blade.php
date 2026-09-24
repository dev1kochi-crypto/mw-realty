@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.coupons.index') }}">Coupons</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $coupon->code }}</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1" style="letter-spacing: 0.04em;"><i class="fas fa-ticket-alt text-primary me-2"></i>{{ $coupon->code }}</h4>
        <div class="text-muted">
            {{ $coupon->discountLabel() }} {{ $coupon->durationLabel() }}
            &middot; {{ $coupon->redemptions_count }}{{ $coupon->max_uses ? ' / ' . $coupon->max_uses : '' }} used
            @if($coupon->description)&middot; {{ $coupon->description }}@endif
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('cms.coupons.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        @if($cmsUser->can('coupons.edit'))
        <a href="{{ route('cms.coupons.edit', $coupon->id) }}" class="btn btn-primary btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
        @endif
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold">Redemptions</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Account</th>
                        <th>Plan</th>
                        <th>Discount / Payment</th>
                        <th>Payments Discounted</th>
                        <th>Remaining</th>
                        <th>Redeemed</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($redemptions as $r)
                    <tr>
                        <td class="ps-4">
                            @if($r->portalUser)
                            <a href="{{ route('cms.portal-accounts.show', ['id' => $r->portalUser->id, 'type' => $r->portalUser->type]) }}" class="fw-semibold text-decoration-none">{{ $r->portalUser->displayName() }}</a>
                            <div class="text-muted small">{{ ucfirst($r->portalUser->type) }}</div>
                            @else — @endif
                        </td>
                        <td>{{ $r->plan?->getTranslation('name') ?? '—' }}</td>
                        <td>AED {{ number_format($r->discount_amount, 2) }}</td>
                        <td>{{ $r->used_payments }}</td>
                        <td>{{ $r->remaining_payments === null ? 'Every payment' : $r->remaining_payments }}</td>
                        <td>{{ $r->redeemed_at->format('d M Y H:i') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Not redeemed yet. A coupon counts as used once Super Admin approves the plan request it was entered on.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="mt-3">{{ $redemptions->links('pagination::bootstrap-5') }}</div>
@endsection
