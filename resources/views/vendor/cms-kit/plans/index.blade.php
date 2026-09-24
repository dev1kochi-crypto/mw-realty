@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Plans</li>
@endsection

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --dash-navy: var(--secondary-color, #264373);
        --dash-teal: var(--primary-color, #04a1cc);
        --dash-green: #0f9d58;
        --dash-green-2: #34c880;
        --dash-amber: #e08e0b;
        --dash-amber-2: #f5a623;
        --dash-slate: #5b6478;
        --dash-slate-2: #8891a8;
        --dash-ink: #1c2340;
        --dash-muted: #838aa3;
        --dash-bg-soft: #f7f8fc;
        --dash-border: rgba(28, 35, 64, 0.07);
    }
    .fill-teal  { background: linear-gradient(135deg, var(--dash-teal), #2fc4e8); }
    .fill-navy  { background: linear-gradient(135deg, var(--dash-navy), #3a5794); }
    .fill-green { background: linear-gradient(135deg, var(--dash-green), var(--dash-green-2)); }
    .fill-amber { background: linear-gradient(135deg, var(--dash-amber), var(--dash-amber-2)); }

    .stat-mini {
        border-radius: 14px; border: 1px solid var(--dash-border); background: #fff;
        box-shadow: 0 4px 14px rgba(28,35,64,0.05);
        padding: 0.9rem 1rem; display: flex; align-items: center; gap: 0.75rem; height: 100%;
    }
    .stat-mini-icon { width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1rem; }
    .stat-mini-value { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.2rem; line-height: 1.1; color: var(--dash-ink); }
    .stat-mini-label { font-size: 0.68rem; font-weight: 700; color: var(--dash-muted); text-transform: uppercase; letter-spacing: 0.02em; }

    .plan-card {
        position: relative; display: flex; flex-direction: column; height: 100%;
        border-radius: 18px; border: 1px solid var(--dash-border); background: #fff;
        box-shadow: 0 4px 14px rgba(28,35,64,0.05); padding: 1.75rem 1.5rem 1.5rem;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .plan-card.is-popular { border: 2px solid var(--dash-teal); box-shadow: 0 14px 32px rgba(4,161,204,0.16); }
    .plan-card.is-inactive { opacity: 0.6; }
    .plan-ribbon {
        position: absolute; top: 2.15rem; right: 1.5rem;
        background: linear-gradient(135deg, var(--dash-amber), var(--dash-amber-2)); color: #fff;
        font-weight: 800; font-size: 0.66rem; letter-spacing: 0.04em; text-transform: uppercase;
        padding: 0.35rem 0.85rem; border-radius: 0 0 8px 8px;
    }
    .plan-name { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.05rem; color: var(--dash-navy); margin-bottom: 0.6rem; }
    .plan-price { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 2.1rem; color: var(--dash-ink); line-height: 1; }
    .plan-price-suffix { font-size: 0.85rem; color: var(--dash-muted); font-weight: 700; }
    .plan-limit { font-size: 0.78rem; color: var(--dash-muted); font-weight: 700; margin: 0.5rem 0 1.25rem; display: flex; align-items: center; gap: 0.4rem; }
    .plan-limit i { color: var(--dash-teal); }
    .plan-features { list-style: none; padding: 0; margin: 0 0 1.25rem; flex: 1; }
    .plan-features li { display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.83rem; color: #4b5065; margin-bottom: 0.65rem; }
    .plan-features li i { color: var(--dash-green); margin-top: 0.2rem; font-size: 0.78rem; }
    .plan-features .text-muted { font-size: 0.8rem; }

    .plan-meta-row { display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; border-top: 1px solid var(--dash-border); margin-top: auto; }
    .plan-subscribers { font-size: 0.78rem; font-weight: 700; color: var(--dash-navy); }
    .plan-subscribers i { color: var(--dash-muted); margin-right: 0.3rem; }

    .plan-actions-row { display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem; }

    .plan-col { transition: opacity 0.15s ease; }
    .plan-col.is-dragging { opacity: 0.35; }
    .plan-drag-handle {
        cursor: grab; display: flex; align-items: center; justify-content: center; gap: 0.4rem;
        background: var(--dash-bg-soft); color: var(--dash-slate);
        font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;
        padding: 0.4rem 0.5rem; margin: -1.75rem -1.5rem 1rem -1.5rem;
        border-radius: 18px 18px 0 0; border-bottom: 1px solid var(--dash-border);
        -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none;
        -webkit-user-drag: element;
    }
    .plan-drag-handle:active { cursor: grabbing; }
    .plan-drag-handle i { font-size: 0.85rem; pointer-events: none; }
    .plans-hint { font-size: 0.8rem; color: var(--dash-muted); font-weight: 600; }
    .plans-hint i { color: var(--dash-teal); }

    .btn-brand-add {
        background: linear-gradient(135deg, var(--dash-teal), var(--dash-navy));
        border: none; color: #fff; font-weight: 700; font-size: 0.85rem;
        padding: 0.5rem 1.1rem; border-radius: 10px; box-shadow: 0 4px 12px rgba(4,161,204,0.25);
    }
    .btn-brand-add:hover { color: #fff; opacity: 0.92; }

    .plan-modal-content { border: none; border-radius: 20px; box-shadow: 0 24px 60px rgba(28,35,64,0.22); }
    .plan-modal-icon {
        width: 60px; height: 60px; border-radius: 50%; margin: 0 auto 1.1rem;
        display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
    }
    .plan-modal-icon.tone-danger { background: rgba(220,53,69,0.1); color: #dc3545; }
    .plan-modal-icon.tone-warning { background: rgba(224,142,11,0.12); color: var(--dash-amber); }
    #planModalTitle { font-family: 'Plus Jakarta Sans', sans-serif; }
</style>
@endpush

@section('content')
<div class="row g-2 mb-3">
    <div class="col-6 col-md-4">
        <div class="stat-mini">
            <span class="stat-mini-icon fill-navy"><i class="fas fa-layer-group"></i></span>
            <div><div class="stat-mini-value">{{ $planStats['total_plans'] }}</div><div class="stat-mini-label">Total Plans</div></div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-mini">
            <span class="stat-mini-icon fill-green"><i class="fas fa-user-check"></i></span>
            <div><div class="stat-mini-value">{{ $planStats['active_subscribers'] }}</div><div class="stat-mini-label">Active Subscribers</div></div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-mini">
            <span class="stat-mini-icon fill-amber"><i class="fas fa-coins"></i></span>
            <div><div class="stat-mini-value">AED {{ number_format($planStats['monthly_revenue']) }}</div><div class="stat-mini-label">Est. Monthly Revenue</div></div>
        </div>
    </div>
</div>

@php $canReorder = $cmsUser->can('plans.edit') && $plans->count() > 1; @endphp

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0">Plans</h5>
        @if($canReorder)
        <span class="plans-hint"><i class="fas fa-arrows-alt me-1"></i> Grab a card by its "Drag to reorder" bar and drop it where you want it.</span>
        @endif
    </div>
    <div class="d-flex align-items-center gap-2">
        @if($cmsUser->can('plans.edit') || $cmsUser->can('plans.delete'))
        <div class="dropdown" id="bulkActions" style="display: none;">
            <button class="btn btn-outline-danger btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                Bulk Actions (<span id="selectedCount">0</span>)
            </button>
            <ul class="dropdown-menu">
                @if($cmsUser->can('plans.edit'))
                <li><button class="dropdown-item" type="button" onclick="planBulkAction('active')"><i class="fas fa-check-circle text-success me-2"></i> Mark Active</button></li>
                <li><button class="dropdown-item" type="button" onclick="planBulkAction('inactive')"><i class="fas fa-times-circle text-secondary me-2"></i> Mark Inactive</button></li>
                @endif
                @if($cmsUser->can('plans.edit') && $cmsUser->can('plans.delete'))
                <li><hr class="dropdown-divider"></li>
                @endif
                @if($cmsUser->can('plans.delete'))
                <li><button class="dropdown-item" type="button" onclick="planBulkAction('delete')"><i class="fas fa-trash text-danger me-2"></i> Delete Selected</button></li>
                @endif
            </ul>
        </div>
        @endif
        @if($cmsUser->can('plans.create'))
        <a href="{{ route('cms.plans.create') }}" class="btn btn-sm btn-brand-add"><i class="fas fa-plus me-1"></i> Add Plan</a>
        @endif
    </div>
</div>

<div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
    <i class="fas fa-info-circle text-primary me-2"></i>
    Assign a plan to an Agent or Company from <a href="{{ route('cms.portal-accounts.index') }}">Agents & Companies</a>.
    Plan limits (listings, featured, team agents, reports) are enforced in their portal. Paid plans are sold monthly or yearly by card through Stripe; changes to prices create new Stripe prices automatically for new subscribers.
</div>

@push('styles')
<style>
    #plansGrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 1.25rem; }
    .pc { position: relative; display: flex; flex-direction: column; height: 100%; border-radius: 18px; background: #fff; border: 1px solid var(--dash-border); box-shadow: 0 6px 20px rgba(28,35,64,0.06); overflow: hidden; transition: transform .15s ease, box-shadow .15s ease; }
    .pc:hover { transform: translateY(-3px); box-shadow: 0 16px 34px rgba(28,35,64,0.12); }
    .pc.is-popular { border: 2px solid var(--dash-teal); }
    .pc.is-inactive { opacity: .6; }
    .pc .plan-drag-handle { margin: 0; border-radius: 0; }
    .pc-head { position: relative; padding: 1.25rem 1.25rem 1rem; color: #fff; background: linear-gradient(135deg, var(--pc-a), var(--pc-b)); }
    .pc-head::after { content: ''; position: absolute; right: -40px; top: -40px; width: 120px; height: 120px; border-radius: 50%; background: rgba(255,255,255,.1); }
    .pc-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,.18); font-size: 1rem; }
    .pc-name { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.15rem; margin: .6rem 0 .1rem; }
    .pc-desc { font-size: .78rem; opacity: .85; min-height: 1.2em; }
    .pc-badges { position: absolute; top: 1rem; right: 1rem; display: flex; gap: .35rem; z-index: 1; }
    .pc-badge { font-size: .62rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; padding: .25rem .55rem; border-radius: 50px; background: rgba(255,255,255,.2); color: #fff; }
    .pc-badge--popular { background: linear-gradient(135deg, var(--dash-amber), var(--dash-amber-2)); }
    .pc-badge--off { background: rgba(0,0,0,.25); }
    .pc-prices { display: grid; grid-template-columns: 1fr 1fr; border-bottom: 1px solid var(--dash-border); }
    .pc-price { padding: .85rem 1rem; min-width: 0; }
    .pc-price + .pc-price { border-left: 1px solid var(--dash-border); }
    .pc-price__label { font-size: .64rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--dash-muted); }
    .pc-price__value { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: clamp(1rem, 1.1vw + .45rem, 1.2rem); color: var(--dash-ink); line-height: 1.2; white-space: nowrap; }
    .pc-price__value small { font-size: .7rem; color: var(--dash-muted); font-weight: 700; }
    .pc-price__save { font-size: .68rem; font-weight: 800; color: var(--dash-green); }
    .pc-stats { display: flex; flex-direction: column; gap: .4rem; padding: 1rem 1.25rem .5rem; }
    .pc-stat { display: flex; align-items: flex-start; gap: .55rem; padding: .5rem .7rem; border-radius: 10px; background: var(--dash-bg-soft); font-size: .78rem; font-weight: 700; color: #3d4460; line-height: 1.35; overflow-wrap: anywhere; }
    .pc-stat i { margin-top: .12rem; flex-shrink: 0; }
    .pc-stat i { width: 1rem; text-align: center; color: var(--dash-teal); }
    .pc-stat.is-off { color: var(--dash-slate-2); text-decoration: line-through; }
    .pc-stat.is-off i { color: var(--dash-slate-2); }
    .pc-features { list-style: none; padding: .5rem 1.25rem 0; margin: 0 0 1rem; flex: 1; }
    .pc-features li { display: flex; gap: .45rem; font-size: .8rem; color: #4b5065; margin-bottom: .45rem; }
    .pc-features li i { color: var(--dash-green); margin-top: .2rem; font-size: .72rem; }
    .pc-foot { padding: .85rem 1.25rem; border-top: 1px solid var(--dash-border); background: var(--dash-bg-soft); }
    .pc-subs { display: flex; justify-content: space-between; align-items: center; font-size: .76rem; font-weight: 700; color: var(--dash-navy); }
    .pc-subs span { color: var(--dash-muted); font-weight: 600; }
    .pc-actions { display: flex; align-items: center; justify-content: flex-end; gap: .4rem; margin-top: .75rem; }
    .pc-stripe { font-size: .66rem; font-weight: 700; color: #635bff; }
</style>
@endpush

@php
    $tones = [['#264373', '#3a5794'], ['#04a1cc', '#2fc4e8'], ['#6d28d9', '#8b5cf6'], ['#0f9d58', '#34c880'], ['#b45309', '#f5a623']];
    $icons = ['fa-seedling', 'fa-paper-plane', 'fa-rocket', 'fa-crown', 'fa-gem'];
@endphp

<div id="plansGrid">
    @forelse($plans as $plan)
    @php
        $tone = $tones[$loop->index % count($tones)];
        $isFree = (float) $plan->price <= 0;
        $lines = $plan->entitlementLines();
        $yearlySubs = $plan->subscribers()->where('billing_interval', 'yearly')->count();
    @endphp
    <div class="plan-col" data-id="{{ $plan->id }}">
        <div class="pc {{ $plan->is_popular ? 'is-popular' : '' }} {{ $plan->status ? '' : 'is-inactive' }}">
            @if($canReorder)
            <div class="plan-drag-handle" title="Drag to reorder" draggable="true"><i class="fas fa-grip-lines"></i> Drag to reorder</div>
            @endif

            <div class="pc-head" style="--pc-a: {{ $tone[0] }}; --pc-b: {{ $tone[1] }};">
                <div class="pc-badges">
                    @if($plan->is_popular)<span class="pc-badge pc-badge--popular">Popular</span>@endif
                    @unless($plan->status)<span class="pc-badge pc-badge--off">Inactive</span>@endunless
                </div>
                <div class="pc-icon"><i class="fas {{ $icons[min($loop->index, count($icons) - 1)] }}"></i></div>
                <div class="pc-name">{{ $plan->getTranslation('name') }}</div>
                <div class="pc-desc">{{ $plan->getTranslation('description') }}</div>
            </div>

            <div class="pc-prices">
                @if($isFree)
                <div class="pc-price" style="grid-column: span 2;">
                    <div class="pc-price__label">Price</div>
                    <div class="pc-price__value">Free <small>forever</small></div>
                </div>
                @else
                <div class="pc-price">
                    <div class="pc-price__label">Monthly</div>
                    <div class="pc-price__value">AED {{ number_format($plan->price) }}<small>/mo</small></div>
                </div>
                <div class="pc-price">
                    <div class="pc-price__label">Yearly</div>
                    @if($plan->hasYearly())
                    <div class="pc-price__value">AED {{ number_format($plan->yearly_price) }}<small>/yr</small></div>
                    @if($plan->yearlySavingsPercent() > 0)<div class="pc-price__save">Save {{ $plan->yearlySavingsPercent() }}%</div>@endif
                    @else
                    <div class="pc-price__value text-muted" style="font-size: .9rem;">Not offered</div>
                    @endif
                </div>
                @endif
            </div>

            <div class="pc-stats">
                @foreach([['fa-building', $lines[0]], ['fa-star', $lines[1]], ['fa-users', $lines[2]], ['fa-chart-line', $lines[3]]] as [$icon, [$text, $included]])
                <div class="pc-stat {{ $included ? '' : 'is-off' }}" title="{{ $text }}"><i class="fas {{ $icon }}"></i><span>{{ $text }}</span></div>
                @endforeach
            </div>

            <ul class="pc-features">
                @forelse($plan->features as $feature)
                <li><i class="fas fa-check-circle"></i> {{ $feature }}</li>
                @empty
                <li class="text-muted">No extra features listed.</li>
                @endforelse
            </ul>

            <div class="pc-foot">
                <div class="pc-subs">
                    <a href="{{ route('cms.plans.show', $plan->id) }}" class="text-decoration-none"><i class="fas fa-users me-1 text-muted"></i>{{ $plan->subscribers_count }} subscriber{{ $plan->subscribers_count === 1 ? '' : 's' }}</a>
                    @if(!$isFree)<span>{{ $plan->subscribers_count - $yearlySubs }} monthly · {{ $yearlySubs }} yearly</span>@endif
                </div>
                <div class="pc-actions">
                    @if($cmsUser->can('plans.edit') || $cmsUser->can('plans.delete'))
                    <div class="form-check mb-0 me-auto">
                        <input class="form-check-input plan-select-checkbox" type="checkbox" value="{{ $plan->id }}" title="Select">
                    </div>
                    @endif
                    @if($plan->stripe_product_id)<span class="pc-stripe me-1" title="Synced to Stripe"><i class="fab fa-stripe-s"></i> Stripe</span>@endif
                    <div class="form-check form-switch mb-0 me-1" title="Active">
                        <input class="form-check-input toggle-status" type="checkbox" data-id="{{ $plan->id }}" {{ $plan->status ? 'checked' : '' }} {{ $cmsUser->can('plans.edit') ? '' : 'disabled' }}>
                    </div>
                    <a href="{{ route('cms.plans.show', $plan->id) }}" class="btn btn-sm btn-outline-secondary" title="View plan details"><i class="fas fa-eye"></i></a>
                    @if($cmsUser->can('plans.edit'))
                    <a href="{{ route('cms.plans.edit', $plan->id) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                    @endif
                    @if($cmsUser->can('plans.delete'))
                    <button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="{{ $plan->id }}" data-name="{{ $plan->getTranslation('name') }}" data-subscribers="{{ $plan->subscribers_count }}" title="Delete"><i class="fas fa-trash"></i></button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="text-center text-muted py-5" style="grid-column: 1 / -1;">No plans yet. Add your first plan to get started.</div>
    @endforelse
</div>

<div class="modal fade" id="planConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content plan-modal-content">
            <div class="modal-body text-center p-4 pb-2">
                <div class="plan-modal-icon" id="planModalIcon"></div>
                <h5 class="fw-bold mb-2" id="planModalTitle"></h5>
                <p class="text-muted mb-0" id="planModalMessage"></p>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4 pt-3" id="planModalFooter"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function() {
        const base = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/plans/";

        $(document).on('change', '.toggle-status', function() {
            const id = $(this).data('id');
            $.post(base + id + '/toggle-status', { _token: '{{ csrf_token() }}' })
                .done(() => window.location.reload());
        });

        function updatePlanBulkVisibility() {
            const checkedCount = $('.plan-select-checkbox:checked').length;
            $('#selectedCount').text(checkedCount);
            $('#bulkActions').toggle(checkedCount > 0);
        }
        $(document).on('change', '.plan-select-checkbox', updatePlanBulkVisibility);

        window.planBulkAction = function(action) {
            if (action === 'delete' && !confirm('Are you sure you want to delete the selected plans?')) return;
            const ids = $('.plan-select-checkbox:checked').map(function() { return $(this).val(); }).get();
            $.post("{{ route('cms.plans.bulk-action') }}", { _token: '{{ csrf_token() }}', action: action, ids: ids })
                .done(function(res) {
                    if (res.success === false) { alert(res.message || 'Action failed.'); return; }
                    window.location.reload();
                })
                .fail(function(xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Action failed.';
                    alert(msg);
                });
        };

        // Custom confirm/blocked modal — replaces the native browser confirm() popup
        const planModalEl = document.getElementById('planConfirmModal');
        const planModal = new bootstrap.Modal(planModalEl);
        let pendingDeleteId = null;

        function renderPlanModal(kind, opts) {
            const icon = document.getElementById('planModalIcon');
            const title = document.getElementById('planModalTitle');
            const message = document.getElementById('planModalMessage');
            const footer = document.getElementById('planModalFooter');

            if (kind === 'blocked') {
                icon.className = 'plan-modal-icon tone-warning';
                icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                title.textContent = 'Can\'t delete this plan';
                message.textContent = opts.message || (opts.subscribers + ' account' + (opts.subscribers === 1 ? ' is' : 's are') + ' currently on "' + opts.name + '". Move ' + (opts.subscribers === 1 ? 'it' : 'them') + ' to another plan first.');
                footer.innerHTML = '<button type="button" class="btn btn-brand-add px-4" data-bs-dismiss="modal">Got it</button>';
            } else {
                icon.className = 'plan-modal-icon tone-danger';
                icon.innerHTML = '<i class="fas fa-trash"></i>';
                title.textContent = 'Delete "' + opts.name + '"?';
                message.textContent = 'This action can\'t be undone.';
                footer.innerHTML = '<button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>'
                    + '<button type="button" class="btn btn-danger px-4" id="planModalConfirmBtn">Delete Plan</button>';
            }
            planModal.show();
        }

        $(document).on('click', '.delete-item', function() {
            const id = $(this).data('id');
            const name = $(this).data('name') || 'this plan';
            const subscribers = parseInt($(this).data('subscribers'), 10) || 0;

            if (subscribers > 0) {
                pendingDeleteId = null;
                renderPlanModal('blocked', { name: name, subscribers: subscribers });
            } else {
                pendingDeleteId = id;
                renderPlanModal('confirm', { name: name });
            }
        });

        $(document).on('click', '#planModalConfirmBtn', function() {
            if (!pendingDeleteId) return;
            $.ajax({ url: base + pendingDeleteId, type: 'DELETE', data: { _token: '{{ csrf_token() }}' } })
                .done(() => window.location.reload())
                .fail(function(xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Could not delete this plan.';
                    renderPlanModal('blocked', { message: msg });
                });
        });

        // Drag-and-drop reorder
        const grid = document.getElementById('plansGrid');
        let dragEl = null;

        if (grid) {
            grid.addEventListener('dragstart', function (e) {
                const col = e.target.closest('.plan-col');
                if (!col) return;
                dragEl = col;
                e.dataTransfer.effectAllowed = 'move';
                // Show the whole card as the drag ghost, not just the small handle bar being dragged.
                e.dataTransfer.setDragImage(col, col.offsetWidth / 2, 20);
                setTimeout(() => col.classList.add('is-dragging'), 0);
            });

            grid.addEventListener('dragend', function () {
                if (dragEl) dragEl.classList.remove('is-dragging');
                dragEl = null;
            });

            grid.addEventListener('dragover', function (e) {
                e.preventDefault();
                if (!dragEl) return;
                const target = e.target.closest('.plan-col');
                if (!target || target === dragEl) return;
                const rect = target.getBoundingClientRect();
                const isAfter = (e.clientX - rect.left) > rect.width / 2;
                if (isAfter) {
                    target.after(dragEl);
                } else {
                    target.before(dragEl);
                }
            });

            grid.addEventListener('drop', function (e) {
                e.preventDefault();
                const order = Array.from(grid.querySelectorAll('.plan-col')).map(col => col.dataset.id);
                $.post("{{ route('cms.plans.reorder') }}", { _token: '{{ csrf_token() }}', order: order });
            });
        }
    });
</script>
@endpush
