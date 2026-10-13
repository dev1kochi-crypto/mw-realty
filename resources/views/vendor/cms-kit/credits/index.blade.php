@extends('cms-kit::layouts.cms')

@section('title', 'Credits')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Credits</li>
@endsection

@include('cms-kit::credits._styles')

@section('content')
@php($canEdit = $cmsUser->can('credits.edit'))

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 small">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

{{-- Header: what credits are + the on/off switch --}}
<div class="cr-panel p-4 mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div style="max-width: 640px;">
            <div class="cr-title fs-5"><i class="fas fa-coins"></i> Credits</div>
            <small class="text-muted">Credits are a pay-as-you-go top-up on top of a plan. Agents and companies buy them when they need more than their plan gives.</small>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <form action="{{ route('cms.credits.toggle') }}" method="POST" id="creditsToggleForm" class="cr-switch {{ $settings->enabled ? 'cr-switch--on' : 'cr-switch--off' }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="enabled" value="0">
                <div class="form-check form-switch m-0 p-0">
                    <input class="form-check-input ms-0" type="checkbox" role="switch" id="creditsEnabled" name="enabled" value="1" @checked($settings->enabled) @disabled(!$canEdit)>
                </div>
                <label for="creditsEnabled" class="mb-0" style="cursor: pointer;">
                    <span class="cr-switch-state {{ $settings->enabled ? 'text-success' : 'text-secondary' }}">Credits are {{ $settings->enabled ? 'ON' : 'OFF' }}</span>
                    <small class="d-block text-muted">{{ $settings->enabled ? 'Agents can buy and spend credits in the CRM.' : 'No buying — agents can only use credits they have.' }}</small>
                </label>
            </form>
            @if($cmsUser->can('credit-history.view'))
                <a href="{{ route('cms.credit-history.index') }}" class="btn btn-outline-primary"><i class="fas fa-history me-1"></i> Credit history</a>
            @endif
        </div>
    </div>
    @unless($settings->enabled)
        <div class="alert alert-secondary small mb-0 mt-3">
            <i class="fas fa-pause-circle me-1"></i> While credits are off, nobody can buy credits. Agents who already have credits still see them (the credits card, the Credits page and history)
            and can keep using them on extra listings and Premium until they expire. Agents with no credits left see nothing about credits in the CRM.
        </div>
    @endunless
</div>

{{-- Numbers --}}
<div class="row g-2 mb-3">
    @foreach([
        ['Money from credit sales (incl. VAT)', 'AED ' . number_format($sold, 2), 'fa-coins', 'linear-gradient(135deg, #264373, #3a5794)'],
        ['Credits agents still have', number_format($outstanding), 'fa-wallet', 'linear-gradient(135deg, #04a1cc, #2fc4e8)'],
        ['Credits added this month', number_format($inThisMonth), 'fa-arrow-down', 'linear-gradient(135deg, #0f9d58, #34c880)'],
        ['Credits spent this month', number_format($spentThisMonth), 'fa-arrow-up', 'linear-gradient(135deg, #e08e0b, #f5a623)'],
    ] as [$label, $value, $icon, $bg])
    <div class="col-6 col-lg-3">
        <div class="cr-stat">
            <span class="cr-stat-icon" style="background: {{ $bg }};"><i class="fas {{ $icon }}"></i></span>
            <div><div class="cr-stat-value">{{ $value }}</div><div class="cr-stat-label">{{ $label }}</div></div>
        </div>
    </div>
    @endforeach
</div>

{{-- How it works --}}
<div class="row g-2 mb-3">
    <div class="col-md-4">
        <div class="cr-step">
            <span class="cr-step-num">1</span>
            <div><div class="fw-bold">They buy credits</div>
                <small class="text-muted">In the CRM (avatar menu › <strong>Credits</strong> › Buy credits), by card at <strong>AED {{ number_format($settings->price_per_credit, 2) }}</strong> per credit + {{ (float) $settings->vat_percent }}% VAT.</small></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="cr-step">
            <span class="cr-step-num">2</span>
            <div><div class="fw-bold">They spend them on add-ons</div>
                <small class="text-muted"><strong>Extra listing</strong> when the plan's allowance is used up, or <strong>Premium</strong> for 7, 15 or 30 days without using the plan's premium quota.</small></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="cr-step">
            <span class="cr-step-num">3</span>
            <div><div class="fw-bold">Unused credits expire</div>
                <small class="text-muted">Each purchase is valid for <strong>{{ $settings->validity_days }} days</strong>. Whatever is left after that is written off automatically.</small></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    {{-- Price --}}
    <div class="col-xl-7">
        <div class="cr-panel p-4 h-100">
            <div class="cr-title"><i class="fas fa-tag"></i> Price of a credit</div>
            <small class="text-muted d-block mb-3">What one credit costs, how long credits last, and how many can be bought at once.</small>
            <form action="{{ route('cms.credits.settings') }}" method="POST">
                @csrf
                @method('PUT')
                <fieldset @disabled(!$canEdit)>
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <label class="form-label" for="pricePerCredit">Price of 1 credit</label>
                            <div class="input-group"><span class="input-group-text">AED</span>
                                <input id="pricePerCredit" type="number" step="0.01" min="0.01" name="price_per_credit" class="form-control" value="{{ old('price_per_credit', $settings->price_per_credit) }}" required></div>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label" for="vatPercent">VAT added on top</label>
                            <div class="input-group"><input id="vatPercent" type="number" step="0.01" min="0" max="100" name="vat_percent" class="form-control" value="{{ old('vat_percent', $settings->vat_percent) }}" required><span class="input-group-text">%</span></div>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label" for="validityDays">Credits last for</label>
                            <div class="input-group"><input id="validityDays" type="number" min="1" name="validity_days" class="form-control" value="{{ old('validity_days', $settings->validity_days) }}" required><span class="input-group-text">days</span></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="minTopup">Minimum purchase</label>
                            <div class="input-group"><input id="minTopup" type="number" min="1" name="min_topup" class="form-control" value="{{ old('min_topup', $settings->min_topup) }}" required><span class="input-group-text">credits</span></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="maxTopup">Maximum purchase</label>
                            <div class="input-group"><input id="maxTopup" type="number" min="1" name="max_topup" class="form-control" value="{{ old('max_topup', $settings->max_topup) }}" required><span class="input-group-text">credits</span></div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                        <small class="text-muted" id="pricePreview"></small>
                        @if($canEdit)
                            <button type="submit" class="btn btn-primary">Save price</button>
                        @endif
                    </div>
                </fieldset>
            </form>
        </div>
    </div>

    {{-- Manual credits --}}
    <div class="col-xl-5">
        <div class="cr-panel p-4 h-100">
            <div class="cr-title"><i class="fas fa-hand-holding-usd"></i> Give credits manually</div>
            <small class="text-muted d-block mb-3">
                Only for credits added <strong>without a card payment</strong> — a bank transfer or cash payment, a promotion, or making up for a problem.
                They expire after {{ $settings->validity_days }} days like bought credits. An agent in an agency shares the agency's credits, so they go to the agency.
            </small>
            @if($canEdit)
                <form action="{{ route('cms.credits.grant') }}" method="POST" class="row g-3" id="grantForm">
                    @csrf
                    <div class="col-12">
                        <label class="form-label" for="grantEmail">Agent / company login email</label>
                        <input id="grantEmail" type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="name@agency.com" autocomplete="off" required>
                        <small id="grantWho" class="d-block mt-1 text-muted">Type the email — we'll show who it belongs to.</small>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label" for="grantCredits">Credits to add</label>
                        <input id="grantCredits" type="number" min="1" name="credits" class="form-control" value="{{ old('credits') }}" required>
                    </div>
                    <div class="col-sm-8">
                        <label class="form-label" for="grantNote">Note the agent will see</label>
                        <input id="grantNote" type="text" name="note" maxlength="120" class="form-control" value="{{ old('note') }}" placeholder="e.g. Bank transfer 12 Oct">
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add credits</button>
                    </div>
                </form>
            @else
                <p class="text-muted small mb-0">You need permission to edit credits to give credits.</p>
            @endif
        </div>
    </div>
</div>

{{-- Add-on costs --}}
<div class="cr-panel p-4">
    <div class="cr-title"><i class="fas fa-list-ol"></i> How many credits each add-on costs</div>
    <small class="text-muted d-block mb-2">
        Each row is a price list for one kind of listing. When an agent spends credits, the row that matches their listing is used — the CRM calculator shows the same numbers.
    </small>
    <ul class="small text-muted mb-3 ps-3">
        <li><strong>Any</strong> means the row applies to every value. Usually you only need <em>Category</em> and <em>Offering</em>, e.g. "Residential · Rent".</li>
        <li>If several rows match, the most specific one wins (a row naming a property type beats one that doesn't).</li>
        <li>Keep one row with everything on <strong>Any</strong> — it's the fallback price for listings no other row covers.</li>
    </ul>
    <form action="{{ route('cms.credits.rates') }}" method="POST">
        @csrf
        @method('PUT')
        <div class="table-responsive">
            <table class="table premium-table align-middle mb-0">
                <thead>
                    <tr>
                        <th colspan="5" class="text-muted small border-0 pb-0">Which listings</th>
                        <th colspan="4" class="text-muted small border-0 pb-0 border-start">Cost in credits</th>
                        @if($canEdit)<th class="border-0"></th>@endif
                    </tr>
                    <tr>
                        <th>Category</th>
                        <th>Offering</th>
                        <th>Property type</th>
                        <th>Price from (AED)</th>
                        <th>Price up to (AED)</th>
                        <th class="border-start">Extra listing</th>
                        <th>Premium 7 days</th>
                        <th>Premium 15 days</th>
                        <th>Premium 30 days</th>
                        @if($canEdit)<th></th>@endif
                    </tr>
                </thead>
                <tbody id="creditRatesBody">
                    @foreach($rates as $i => $rate)
                        @include('cms-kit::credits._rate_row', ['i' => $i, 'rate' => $rate])
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($canEdit)
            <div class="d-flex gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary" id="addCreditRate"><i class="fas fa-plus me-1"></i> Add a row</button>
                <button type="submit" class="btn btn-primary">Save costs</button>
            </div>
            <template id="creditRateTemplate">@include('cms-kit::credits._rate_row', ['i' => '__I__', 'rate' => null])</template>
        @endif
    </form>
</div>

{{-- Confirm popup (on/off switch, giving credits) — replaces the browser's confirm() --}}
<div class="modal fade" id="creditConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content cr-modal-content">
            <div class="modal-body text-center p-4 pb-2">
                <div class="cr-modal-icon" id="creditModalIcon"></div>
                <h5 class="fw-bold mb-2" id="creditModalTitle"></h5>
                <p class="text-muted mb-0" id="creditModalMessage"></p>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4 pt-3">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn px-4" id="creditModalConfirm"></button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        // Confirm popup: confirmAction({ tone, icon, title, message, button, buttonClass }, onConfirm, onCancel).
        const modalEl = document.getElementById('creditConfirmModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const confirmBtn = document.getElementById('creditModalConfirm');
        let onConfirm = null, onCancel = null;
        function confirmAction(opts, yes, no) {
            document.getElementById('creditModalIcon').className = 'cr-modal-icon tone-' + opts.tone;
            document.getElementById('creditModalIcon').innerHTML = '<i class="fas ' + opts.icon + '"></i>';
            document.getElementById('creditModalTitle').textContent = opts.title;
            document.getElementById('creditModalMessage').textContent = opts.message;
            confirmBtn.className = 'btn px-4 ' + opts.buttonClass;
            confirmBtn.textContent = opts.button;
            onConfirm = yes; onCancel = no || null;
            modal.show();
        }
        confirmBtn.addEventListener('click', function () {
            const yes = onConfirm;
            onConfirm = onCancel = null;
            confirmBtn.disabled = true;
            if (yes) yes();
        });
        modalEl.addEventListener('hidden.bs.modal', function () {
            if (onCancel) onCancel();
            onConfirm = onCancel = null;
            confirmBtn.disabled = false;
        });

        // On/off switch: saves straight away once confirmed.
        const toggle = document.getElementById('creditsEnabled');
        if (toggle) {
            toggle.addEventListener('change', function () {
                const on = toggle.checked;
                confirmAction(on ? {
                    tone: 'success', icon: 'fa-toggle-on', title: 'Turn credits on?', buttonClass: 'btn-success', button: 'Turn on',
                    message: 'Agents and companies will be able to buy credits and spend them on extra listings and Premium in the CRM.',
                } : {
                    tone: 'warning', icon: 'fa-pause-circle', title: 'Turn credits off?', buttonClass: 'btn-warning', button: 'Turn off',
                    message: 'Nobody will be able to buy credits. Agents who already have credits can keep using them until they expire; agents with none won\'t see credits in the CRM.',
                }, function () { document.getElementById('creditsToggleForm').submit(); }, function () { toggle.checked = !on; });
            });
        }

        // Rate table: add / remove rows.
        const body = document.getElementById('creditRatesBody');
        const template = document.getElementById('creditRateTemplate');
        const add = document.getElementById('addCreditRate');
        if (body && template && add) {
            let next = body.children.length;
            add.addEventListener('click', function () {
                body.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__I__', String(next++)));
            });
            body.addEventListener('click', function (e) {
                const remove = e.target.closest('[data-remove-rate]');
                if (remove) remove.closest('tr').remove();
            });
        }

        // Price preview: what a typical purchase costs.
        const price = document.getElementById('pricePerCredit');
        const vat = document.getElementById('vatPercent');
        const preview = document.getElementById('pricePreview');
        function renderPreview() {
            const p = parseFloat(price.value) || 0, v = parseFloat(vat.value) || 0;
            const total = 1000 * p * (1 + v / 100);
            preview.textContent = 'Example: 1,000 credits = AED ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' including VAT.';
        }
        if (price && vat && preview) { price.addEventListener('input', renderPreview); vat.addEventListener('input', renderPreview); renderPreview(); }

        // Manual credits: show who the email belongs to.
        const email = document.getElementById('grantEmail');
        const who = document.getElementById('grantWho');
        let timer = null;
        function lookup() {
            const value = email.value.trim();
            who.dataset.name = '';
            if (!/^\S+@\S+\.\S+$/.test(value)) { who.className = 'd-block mt-1 text-muted'; who.textContent = "Type the email — we'll show who it belongs to."; return; }
            fetch(@json(route('cms.credits.lookup')) + '?email=' + encodeURIComponent(value), { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.found) { who.className = 'd-block mt-1 text-danger'; who.textContent = 'No agent or company has this email.'; return; }
                    const target = d.wallet || d.name;
                    who.dataset.name = target;
                    who.className = 'd-block mt-1 text-success';
                    who.textContent = '✓ ' + d.name + ' (' + d.type + ')' + (d.wallet ? ' — credits go to their agency, ' + d.wallet : '') + ' · current balance ' + Number(d.balance).toLocaleString('en-US') + ' credits';
                })
                .catch(function () {});
        }
        if (email && who) { email.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(lookup, 400); }); if (email.value) lookup(); }

        // Give credits: confirm who gets them first.
        const grantForm = document.getElementById('grantForm');
        if (grantForm) {
            let confirmed = false;
            grantForm.addEventListener('submit', function (e) {
                if (confirmed) return;
                e.preventDefault();
                const credits = Number(document.getElementById('grantCredits').value || 0).toLocaleString('en-US');
                confirmAction({
                    tone: 'primary', icon: 'fa-hand-holding-usd', title: 'Add ' + credits + ' credits?', buttonClass: 'btn-primary', button: 'Add credits',
                    message: 'They go to ' + (who.dataset.name || email.value.trim()) + ' straight away and expire after {{ $settings->validity_days }} days.',
                }, function () { confirmed = true; grantForm.requestSubmit(); });
            });
        }
    })();
</script>
@endpush
