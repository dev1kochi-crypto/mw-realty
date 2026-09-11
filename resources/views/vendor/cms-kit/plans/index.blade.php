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
    @if($cmsUser->can('plans.create'))
    <a href="{{ route('cms.plans.create') }}" class="btn btn-sm btn-brand-add"><i class="fas fa-plus me-1"></i> Add Plan</a>
    @endif
</div>

<div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
    <i class="fas fa-info-circle text-primary me-2"></i>
    Assign a plan to an Agent or Company from <a href="{{ route('cms.portal-accounts.index') }}">Agents & Companies</a>.
    A plan's property limit is enforced when they add new listings from their portal.
</div>

<div class="row g-4" id="plansGrid">
    @forelse($plans as $plan)
    @php
        $priceLabel = $plan->billing_cycle === 'free' ? 'Free' : 'AED ' . number_format($plan->price);
        $suffix = ['monthly' => '/mo', 'yearly' => '/yr', 'one_time' => ' one-time'][$plan->billing_cycle] ?? '';
        $limitLabel = $plan->isUnlimited() ? 'Unlimited properties' : $plan->property_limit . ' properties';
    @endphp
    <div class="col-lg-3 col-md-6 plan-col" data-id="{{ $plan->id }}">
        <div class="plan-card {{ $plan->is_popular ? 'is-popular' : '' }} {{ $plan->status ? '' : 'is-inactive' }}">
            @if($canReorder)
            <div class="plan-drag-handle" title="Drag to reorder" draggable="true"><i class="fas fa-grip-lines"></i> Drag to reorder</div>
            @endif
            @if($plan->is_popular)
            <span class="plan-ribbon">Popular</span>
            @endif

            <div class="plan-name">{{ $plan->getTranslation('name') }}</div>
            <div><span class="plan-price">{{ $priceLabel }}</span> <span class="plan-price-suffix">{{ $suffix }}</span></div>
            <div class="plan-limit"><i class="fas fa-building"></i> {{ $limitLabel }}</div>

            <ul class="plan-features">
                @forelse($plan->features as $feature)
                <li><i class="fas fa-check-circle"></i> {{ $feature }}</li>
                @empty
                <li class="text-muted">No features listed.</li>
                @endforelse
            </ul>

            <div class="plan-meta-row">
                <a href="{{ route('cms.plans.show', $plan->id) }}" class="plan-subscribers text-decoration-none"><i class="fas fa-users"></i>{{ $plan->subscribers_count }} subscriber{{ $plan->subscribers_count === 1 ? '' : 's' }}</a>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input toggle-status" type="checkbox" data-id="{{ $plan->id }}" {{ $plan->status ? 'checked' : '' }} {{ $cmsUser->can('plans.edit') ? '' : 'disabled' }}>
                </div>
            </div>

            <div class="plan-actions-row">
                <a href="{{ route('cms.plans.show', $plan->id) }}" class="btn btn-sm btn-outline-secondary" title="View plan details"><i class="fas fa-eye"></i></a>
                @if($cmsUser->can('plans.edit'))
                <a href="{{ route('cms.plans.edit', $plan->id) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                @endif
                @if($cmsUser->can('plans.delete'))
                <button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="{{ $plan->id }}" data-name="{{ $plan->getTranslation('name') }}" data-subscribers="{{ $plan->subscribers_count }}"><i class="fas fa-trash"></i></button>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="text-center text-muted py-5">No plans yet. Add your first plan to get started.</div>
    </div>
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
