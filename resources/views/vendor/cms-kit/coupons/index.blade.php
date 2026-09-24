@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Coupons</li>
@endsection

@push('styles')
<style>
    .cp-stat { border-radius: 14px; border: 1px solid rgba(28,35,64,0.07); background: #fff; box-shadow: 0 4px 14px rgba(28,35,64,0.05); padding: 0.9rem 1rem; display: flex; align-items: center; gap: 0.75rem; height: 100%; }
    .cp-stat-icon { width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; }
    .cp-stat-value { font-weight: 800; font-size: 1.2rem; line-height: 1.1; color: #1c2340; }
    .cp-stat-label { font-size: 0.68rem; font-weight: 700; color: #838aa3; text-transform: uppercase; letter-spacing: 0.02em; }
    .cp-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; letter-spacing: 0.04em; background: #f1f5ff; color: #264373; border: 1px dashed #9fb3dd; border-radius: 8px; padding: 0.25rem 0.6rem; display: inline-block; }
    .cp-usage { height: 6px; border-radius: 6px; background: #eef0f6; overflow: hidden; width: 90px; margin-top: 4px; }
    .cp-usage > span { display: block; height: 100%; background: linear-gradient(90deg, #04a1cc, #264373); }
</style>
@endpush

@section('content')
<div class="row g-2 mb-3">
    @foreach([
        ['Total Coupons', $stats['total'], 'fa-ticket-alt', 'linear-gradient(135deg, #264373, #3a5794)'],
        ['Active', $stats['active'], 'fa-check-circle', 'linear-gradient(135deg, #0f9d58, #34c880)'],
        ['Redemptions', $stats['redemptions'], 'fa-user-check', 'linear-gradient(135deg, #04a1cc, #2fc4e8)'],
        ['Discount Given', 'AED ' . number_format($stats['saved']), 'fa-coins', 'linear-gradient(135deg, #e08e0b, #f5a623)'],
    ] as [$label, $value, $icon, $bg])
    <div class="col-6 col-md-3">
        <div class="cp-stat">
            <span class="cp-stat-icon" style="background: {{ $bg }};"><i class="fas {{ $icon }}"></i></span>
            <div><div class="cp-stat-value">{{ $value }}</div><div class="cp-stat-label">{{ $label }}</div></div>
        </div>
    </div>
    @endforeach
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
        <div>
            <h5 class="mb-0">Coupons</h5>
            <span class="text-muted small">Discount codes Agents &amp; Companies can apply when requesting a paid plan.</span>
        </div>
        <div class="d-flex gap-2">
            <form method="GET" class="d-flex">
                <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Search code">
            </form>
            @if($cmsUser->can('coupons.create'))
            <a href="{{ route('cms.coupons.create') }}" class="btn btn-primary btn-sm px-3"><i class="fas fa-plus me-1"></i> Add Coupon</a>
            @endif
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Code</th>
                        <th>Discount</th>
                        <th>Plans</th>
                        <th>Validity</th>
                        <th>Usage</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($coupons as $coupon)
                    @php
                        $state = $coupon->state($coupon->redemptions_count);
                        $stateBadge = [
                            'active' => ['bg-success', 'Active'], 'scheduled' => ['bg-info', 'Scheduled'],
                            'expired' => ['bg-secondary', 'Expired'], 'exhausted' => ['bg-warning text-dark', 'Used up'],
                            'inactive' => ['bg-light text-muted border', 'Inactive'],
                        ][$state];
                    @endphp
                    <tr>
                        <td class="ps-4">
                            <span class="cp-code">{{ $coupon->code }}</span>
                            @if($coupon->description)<div class="text-muted small mt-1">{{ $coupon->description }}</div>@endif
                        </td>
                        <td>
                            <div class="fw-bold">{{ $coupon->discountLabel() }}</div>
                            <div class="text-muted small">{{ ucfirst($coupon->durationLabel()) }}</div>
                        </td>
                        <td class="small">
                            @if(empty($coupon->plan_ids))
                                <span class="text-muted">All paid plans</span>
                            @else
                                {{ collect($coupon->plan_ids)->map(fn ($id) => $plans[$id]?->getTranslation('name'))->filter()->implode(', ') ?: '—' }}
                            @endif
                        </td>
                        <td class="small">
                            <div>{{ $coupon->starts_at?->format('d M Y') ?? 'Now' }} &rarr; {{ $coupon->expires_at?->format('d M Y') ?? 'No expiry' }}</div>
                            <span class="badge {{ $stateBadge[0] }} mt-1">{{ $stateBadge[1] }}</span>
                        </td>
                        <td class="small">
                            <a href="{{ route('cms.coupons.show', $coupon->id) }}" class="text-decoration-none fw-semibold">{{ $coupon->redemptions_count }}{{ $coupon->max_uses ? ' / ' . $coupon->max_uses : '' }} used</a>
                            @if($coupon->max_uses)
                            <div class="cp-usage"><span style="width: {{ min(100, $coupon->redemptions_count / $coupon->max_uses * 100) }}%;"></span></div>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($cmsUser->can('coupons.edit'))
                            <div class="form-check form-switch d-inline-block">
                                <input class="form-check-input toggle-coupon" type="checkbox" data-id="{{ $coupon->id }}" @checked($coupon->status)>
                            </div>
                            @else
                            {{ $coupon->status ? 'On' : 'Off' }}
                            @endif
                        </td>
                        <td class="text-end pe-4 text-nowrap">
                            <a href="{{ route('cms.coupons.show', $coupon->id) }}" class="btn btn-sm btn-outline-secondary" title="Usage"><i class="fas fa-chart-bar"></i></a>
                            @if($cmsUser->can('coupons.edit'))
                            <a href="{{ route('cms.coupons.edit', $coupon->id) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                            @endif
                            @if($cmsUser->can('coupons.delete'))
                            <button type="button" class="btn btn-sm btn-outline-danger delete-coupon" data-id="{{ $coupon->id }}" data-code="{{ $coupon->code }}" title="Delete"><i class="fas fa-trash"></i></button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="fas fa-ticket-alt fa-2x mb-2 d-block opacity-50"></i>
                            No coupons yet.
                            @if($cmsUser->can('coupons.create'))<a href="{{ route('cms.coupons.create') }}">Create your first coupon</a>.@endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $coupons->links('pagination::bootstrap-5') }}</div>
@endsection

@push('scripts')
<script>
    const couponBase = @json(url(config('cms-kit.common.auth.prefix', 'admin') . '/coupons'));
    const csrf = '{{ csrf_token() }}';

    document.addEventListener('change', function (e) {
        const toggle = e.target.closest('.toggle-coupon');
        if (!toggle) return;
        fetch(couponBase + '/' + toggle.dataset.id + '/toggle-status', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
            .then(r => { if (!r.ok) throw new Error(); location.reload(); })
            .catch(() => { toggle.checked = !toggle.checked; alert('Could not update the coupon.'); });
    });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.delete-coupon');
        if (!btn) return;
        if (!confirm('Delete coupon ' + btn.dataset.code + '? Accounts already using it stop getting the discount on future payments.')) return;
        fetch(couponBase + '/' + btn.dataset.id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
            .then(r => { if (!r.ok) throw new Error(); location.reload(); })
            .catch(() => alert('Could not delete the coupon.'));
    });
</script>
@endpush
