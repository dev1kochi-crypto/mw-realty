@extends('cms-kit::layouts.cms')

@section('title', 'Credits')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Credits</li>
@endsection

@section('content')
@php($canEdit = $cmsUser->can('plans.edit'))

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 small">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

{{-- What this page is about, in plain words --}}
<div class="card glass-card mb-3">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h5 class="fw-bold mb-1"><i class="fas fa-coins text-warning me-1"></i> Credits</h5>
                <small class="text-muted">Credits are a pay-as-you-go top-up on top of a plan. Agents and companies buy them when they need more than their plan gives.</small>
            </div>
            <span class="badge {{ $settings->enabled ? 'bg-success' : 'bg-secondary' }} fs-6">{{ $settings->enabled ? 'Credits are ON' : 'Credits are OFF' }}</span>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="p-3 rounded-3 bg-light h-100">
                    <div class="fw-bold mb-1"><span class="badge bg-primary me-1">1</span> They buy credits</div>
                    <small class="text-muted">In the CRM (avatar menu › <strong>Credits</strong> › Buy credits), paid by card at <strong>AED {{ number_format($settings->price_per_credit, 2) }}</strong> per credit + {{ (float) $settings->vat_percent }}% VAT.</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 rounded-3 bg-light h-100">
                    <div class="fw-bold mb-1"><span class="badge bg-primary me-1">2</span> They spend them on add-ons</div>
                    <small class="text-muted"><strong>Extra listing</strong> — when the plan's listing allowance is used up.<br><strong>Premium</strong> for 7, 15 or 30 days — without using the plan's premium quota.</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 rounded-3 bg-light h-100">
                    <div class="fw-bold mb-1"><span class="badge bg-primary me-1">3</span> Unused credits expire</div>
                    <small class="text-muted">Each purchase is valid for <strong>{{ $settings->validity_days }} days</strong>. Whatever is left after that is written off automatically.</small>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-sm-6">
                <small class="text-muted d-block">Money received from credit purchases (incl. VAT)</small>
                <span class="fs-5 fw-bold">AED {{ number_format($sold, 2) }}</span>
            </div>
            <div class="col-sm-6">
                <small class="text-muted d-block">Credits agents still have to spend</small>
                <span class="fs-5 fw-bold">{{ number_format($outstanding) }}</span>
            </div>
        </div>
    </div>
</div>

{{-- 1. Price --}}
<div class="card glass-card mb-3">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-1"><span class="badge bg-primary me-1">1</span> Price of a credit</h5>
        <small class="text-muted d-block mb-3">What one credit costs, how long credits last, and how many can be bought at once.</small>
        <form action="{{ route('cms.credits.settings') }}" method="POST">
            @csrf
            @method('PUT')
            <fieldset @disabled(!$canEdit)>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="creditsEnabled" name="enabled" value="1" @checked($settings->enabled)>
                    <label class="form-check-label" for="creditsEnabled"><strong>Turn credits on</strong> — when off, agents can't buy or spend credits and the Credits menu is hidden.</label>
                </div>
                <div class="row g-3">
                    <div class="col-sm-6 col-lg">
                        <label class="form-label small fw-bold" for="pricePerCredit">Price of 1 credit</label>
                        <div class="input-group"><span class="input-group-text">AED</span>
                            <input id="pricePerCredit" type="number" step="0.01" min="0.01" name="price_per_credit" class="form-control" value="{{ old('price_per_credit', $settings->price_per_credit) }}" required></div>
                    </div>
                    <div class="col-sm-6 col-lg">
                        <label class="form-label small fw-bold" for="vatPercent">VAT added on top</label>
                        <div class="input-group"><input id="vatPercent" type="number" step="0.01" min="0" max="100" name="vat_percent" class="form-control" value="{{ old('vat_percent', $settings->vat_percent) }}" required><span class="input-group-text">%</span></div>
                    </div>
                    <div class="col-sm-4 col-lg">
                        <label class="form-label small fw-bold" for="validityDays">Credits last for</label>
                        <div class="input-group"><input id="validityDays" type="number" min="1" name="validity_days" class="form-control" value="{{ old('validity_days', $settings->validity_days) }}" required><span class="input-group-text">days</span></div>
                    </div>
                    <div class="col-sm-4 col-lg">
                        <label class="form-label small fw-bold" for="minTopup">Minimum purchase</label>
                        <div class="input-group"><input id="minTopup" type="number" min="1" name="min_topup" class="form-control" value="{{ old('min_topup', $settings->min_topup) }}" required><span class="input-group-text">credits</span></div>
                    </div>
                    <div class="col-sm-4 col-lg">
                        <label class="form-label small fw-bold" for="maxTopup">Maximum purchase</label>
                        <div class="input-group"><input id="maxTopup" type="number" min="1" name="max_topup" class="form-control" value="{{ old('max_topup', $settings->max_topup) }}" required><span class="input-group-text">credits</span></div>
                    </div>
                </div>
                <small class="text-muted d-block mt-2" id="pricePreview"></small>
                @if($canEdit)
                    <button type="submit" class="btn btn-primary mt-3">Save price</button>
                @endif
            </fieldset>
        </form>
    </div>
</div>

{{-- 2. Add-on costs --}}
<div class="card glass-card mb-3">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-1"><span class="badge bg-primary me-1">2</span> How many credits each add-on costs</h5>
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
</div>

{{-- 3. Manual credits --}}
<div class="card glass-card mb-3">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-1"><span class="badge bg-primary me-1">3</span> Give credits manually</h5>
        <small class="text-muted d-block mb-3">
            Normally agents buy credits themselves by card. Use this only when credits should be added <strong>without a card payment</strong> — for example the agent paid
            you by bank transfer or cash, you're giving free credits as a promotion, or you're making up for a problem. They work exactly like bought credits and expire after {{ $settings->validity_days }} days.
            An agent who belongs to an agency shares the agency's credits, so they're added to the agency.
        </small>
        @if($canEdit)
            <form action="{{ route('cms.credits.grant') }}" method="POST" class="row g-3 align-items-start">
                @csrf
                <div class="col-lg-5">
                    <label class="form-label small fw-bold" for="grantEmail">Agent / company login email</label>
                    <input id="grantEmail" type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="name@agency.com" autocomplete="off" required>
                    <small id="grantWho" class="d-block mt-1 text-muted">Type the email — we'll show who it belongs to.</small>
                </div>
                <div class="col-sm-4 col-lg-2">
                    <label class="form-label small fw-bold" for="grantCredits">Credits to add</label>
                    <input id="grantCredits" type="number" min="1" name="credits" class="form-control" value="{{ old('credits') }}" required>
                </div>
                <div class="col-sm-8 col-lg-3">
                    <label class="form-label small fw-bold" for="grantNote">Note the agent will see</label>
                    <input id="grantNote" type="text" name="note" maxlength="120" class="form-control" value="{{ old('note') }}" placeholder="e.g. Bank transfer 12 Oct">
                </div>
                <div class="col-lg-2">
                    <label class="form-label small d-none d-lg-block">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100" onclick="return confirm('Add these credits to ' + (document.getElementById('grantWho').dataset.name || 'this account') + '?');">Add credits</button>
                </div>
            </form>
        @else
            <p class="text-muted small mb-0">You need permission to edit plans to give credits.</p>
        @endif
    </div>
</div>

{{-- 4. History --}}
<div class="card glass-card">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-0"><span class="badge bg-primary me-1">4</span> Credit history</h5>
                <small class="text-muted">Every purchase, spend, refund and expiry, across all accounts. <span class="text-success fw-bold">+</span> = credits in, − = credits used.</small>
            </div>
            <form method="GET" class="d-flex gap-2">
                <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Agent, email or listing reference" style="width: 260px;">
                <button class="btn btn-sm btn-light" type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table premium-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Account</th>
                        <th>What happened</th>
                        <th>Done by</th>
                        <th class="text-end">Credits</th>
                        <th class="text-end">Balance after</th>
                        <th>Listing</th>
                        <th class="text-end">Paid</th>
                    </tr>
                </thead>
                <tbody>
                    @php($typeLabels = ['topup' => ['Bought', 'bg-success'], 'grant' => ['Given by admin', 'bg-info'], 'spend' => ['Spent', 'bg-primary'], 'refund' => ['Refunded', 'bg-warning text-dark'], 'expire' => ['Expired', 'bg-secondary']])
                    @forelse($transactions as $t)
                        <tr>
                            <td class="small text-nowrap">{{ $t->created_at->format('d M Y, h:i a') }}</td>
                            <td>{{ $t->account?->displayName() ?? '—' }}<small class="d-block text-muted">{{ $t->account?->email }}</small></td>
                            <td class="small">
                                <span class="badge {{ $typeLabels[$t->type][1] ?? 'bg-light text-dark' }}">{{ $typeLabels[$t->type][0] ?? ucfirst($t->type) }}</span>
                                <span class="d-block mt-1">{{ $t->description }}</span>
                            </td>
                            <td class="small">{{ $t->actor_name ?: 'System' }}</td>
                            <td class="text-end fw-bold {{ $t->credits > 0 ? 'text-success' : '' }}">{{ $t->credits > 0 ? '+' : '' }}{{ number_format($t->credits) }}</td>
                            <td class="text-end">{{ number_format($t->balance_after) }}</td>
                            <td class="small">{{ $t->reference }}</td>
                            <td class="text-end small">{{ $t->total !== null ? 'AED ' . number_format($t->total, 2) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No credit activity{{ $search !== '' ? ' matches “' . $search . '”' : ' yet' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $transactions->links() }}</div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
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
    })();
</script>
@endpush
