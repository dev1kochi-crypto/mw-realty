@extends('portal.crm._layout')

@section('title', 'Integrations')

{{-- CRM › Integrations (Portal\Crm\IntegrationController): Facebook Lead Ads → Leads. Super Admin
     connects Pages and assigns each to an agency / agent; agencies / independent agents connect their own. --}}
@push('styles')
@if($isAdmin && $pendingPages)
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css">
@endif
<style>
    .intg-card { border-radius: 16px; overflow: hidden; }
    .intg-card__head { display: flex; gap: 16px; align-items: center; padding: 20px 24px; border-bottom: 1px solid var(--portal-border); }
    .intg-logo { flex-shrink: 0; display: grid; place-items: center; width: 52px; height: 52px; border-radius: 14px; background: #1877f2; color: #fff; font-size: 1.6rem; box-shadow: 0 8px 18px rgba(24, 119, 242, .28); }
    .intg-card__title { font-weight: 700; color: var(--portal-primary-dark); margin: 0; }
    .intg-card__sub { font-size: .85rem; color: var(--portal-muted); margin: 2px 0 0; }
    .intg-card__body { padding: 20px 24px; }
    .intg-status { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; font-size: .75rem; font-weight: 700; }
    .intg-status--ok { background: #e7f6ee; color: #0f7a43; }
    .intg-status--warn { background: #fff4e0; color: #9a5b00; }
    .intg-status--off { background: #eef0f6; color: var(--portal-muted); }
    .intg-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; }
    .intg-step { padding: 14px; border: 1px solid var(--portal-border); border-radius: 12px; background: var(--portal-bg); font-size: .85rem; }
    .intg-step__num { display: inline-grid; place-items: center; width: 24px; height: 24px; border-radius: 50%; background: var(--portal-primary); color: #fff; font-size: .75rem; font-weight: 700; margin-bottom: 6px; }
    .intg-page { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--portal-border); border-radius: 12px; }
    .intg-page + .intg-page { margin-top: 10px; }
    .intg-page__pic { width: 40px; height: 40px; border-radius: 10px; object-fit: cover; background: #e7edf7; display: grid; place-items: center; color: #1877f2; flex-shrink: 0; }
    .intg-page__owner { flex: 1 1 280px; max-width: 420px; }
    .intg-copy { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8rem; word-break: break-all; }
    .select2-container--default .select2-selection--single { height: 36px; border-color: var(--portal-border, #dee2e6); border-radius: 8px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: .7rem; font-size: .85rem; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 34px; }
    .select2-dropdown { border-color: var(--portal-border, #dee2e6); border-radius: 10px; overflow: hidden; font-size: .85rem; }
</style>
@endpush

@section('crm-content')
<div class="mb-3">
    <div class="portal-section-title mb-0">Integrations</div>
    <p class="text-muted mb-0" style="font-size: 0.85rem;">Bring leads from other channels straight into the CRM.</p>
</div>

<div class="portal-card intg-card mb-3">
    <div class="intg-card__head">
        <span class="intg-logo" aria-hidden="true"><i class="fab fa-facebook-f"></i></span>
        <div class="flex-grow-1 min-w-0">
            <h5 class="intg-card__title">Facebook Lead Ads</h5>
            <p class="intg-card__sub">
                @if($isAdmin)
                Connect Facebook Pages and assign each one to an agency or agent — the Page's lead form leads go straight to their CRM. The ad's name becomes the lead's Source.
                @else
                Every lead from your Facebook / Instagram lead forms arrives in Leads automatically. The ad's name becomes the lead's Source.
                @endif
            </p>
        </div>
        @php $connectedCount = $isAdmin ? $connections->total() : $connections->count(); @endphp
        @if(!$configured)
        <span class="intg-status intg-status--off"><i class="fas fa-circle-pause"></i>Not set up</span>
        @elseif($connectedCount)
        <span class="intg-status intg-status--ok"><i class="fas fa-circle-check"></i>{{ $connectedCount }} page{{ $connectedCount === 1 ? '' : 's' }} connected</span>
        @else
        <span class="intg-status intg-status--off">Not connected</span>
        @endif
    </div>

    <div class="intg-card__body">
        @if(!$configured)
            @if($isAdmin)
            <div class="alert alert-warning small">
                <div class="fw-bold mb-1"><i class="fas fa-triangle-exclamation me-1"></i>Facebook app credentials missing</div>
                Add <code>FACEBOOK_APP_ID</code>, <code>FACEBOOK_APP_SECRET</code> and <code>FACEBOOK_WEBHOOK_VERIFY_TOKEN</code> to the <code>.env</code> file, then in the Meta app (developers.facebook.com):
                <ul class="mb-0 mt-2">
                    <li><strong>Facebook Login › Valid OAuth Redirect URIs:</strong> <span class="intg-copy">{{ $callbackUrl }}</span></li>
                    <li><strong>Webhooks › Page › Callback URL:</strong> <span class="intg-copy">{{ $webhookUrl }}</span> (verify token = <code>FACEBOOK_WEBHOOK_VERIFY_TOKEN</code>), subscribe to <strong>leadgen</strong>.</li>
                    <li><strong>Permissions:</strong> {{ implode(', ', \App\Services\Integrations\FacebookLeadAds::SCOPES) }}.</li>
                </ul>
            </div>
            @else
            <div class="alert alert-light border small mb-0"><i class="fas fa-circle-info me-1"></i>The Facebook integration isn't available yet — MW Realty is setting it up.</div>
            @endif
        @elseif(!$canManage)
            <div class="alert alert-light border small mb-0"><i class="fas fa-circle-info me-1"></i>Your agency manages integrations. Facebook leads it receives are shared with you like any other agency lead.</div>
        @else
            {{-- Back from Facebook Login: Super Admin assigns each Page to an account; an agency / agent ticks their own. --}}
            @if($pendingPages)
            <form method="POST" action="{{ route('portal.crm.integrations.facebook.pages.store') }}" class="mb-4 p-3 rounded-3" style="background: #f2f7ff; border: 1px solid #cfe0fb;">
                @csrf
                <div class="fw-bold mb-1">{{ $isAdmin ? 'Assign the Pages to agencies / agents' : 'Choose the Pages to connect' }}</div>
                <p class="small text-muted mb-3">
                    {{ $isAdmin ? 'Pick whose CRM receives each Page\'s leads. Leave a Page empty to skip it.' : 'Leads from the lead forms on these Pages will come into your CRM.' }}
                    A Page can be connected to one account only.
                </p>
                @foreach($pendingPages as $page)
                @php
                    $existing = $existingConnections->get($page['id']);
                    $mine = $existing && !$isAdmin && $existing->portal_user_id === $ownerId;
                    $blocked = $existing && !$mine;
                @endphp
                <div class="intg-page bg-white {{ $blocked ? 'opacity-75' : '' }}">
                    @unless($isAdmin)
                    <input type="checkbox" class="form-check-input mt-0" name="page_ids[]" value="{{ $page['id'] }}" id="fbPage{{ $loop->index }}" @checked(!$blocked) @disabled($blocked)>
                    @endunless
                    @if($page['picture'])<img src="{{ $page['picture'] }}" alt="" class="intg-page__pic">@else<span class="intg-page__pic"><i class="fab fa-facebook"></i></span>@endif
                    <label class="flex-grow-1 min-w-0 mb-0" @unless($isAdmin) for="fbPage{{ $loop->index }}" @endunless>
                        <span class="fw-semibold d-block text-truncate">{{ $page['name'] }}</span>
                        <span class="small {{ $blocked ? 'text-danger' : 'text-muted' }}">
                            @if($blocked && $isAdmin) Already connected to {{ $existing->owner?->displayName() ?? 'another account' }} — disconnect it first to assign it elsewhere
                            @elseif($blocked) Already connected to another MW Realty account
                            @elseif($mine) Connected — reconnecting refreshes its access
                            @else Page ID {{ $page['id'] }}
                            @endif
                        </span>
                    </label>
                    @if($isAdmin && !$blocked)
                    <div class="intg-page__owner">
                        <select name="owners[{{ $page['id'] }}]" class="js-account-picker" data-placeholder="Assign to agency / agent…">
                            <option value=""></option>
                        </select>
                    </div>
                    @endif
                </div>
                @endforeach
                <div class="d-flex gap-2 justify-content-end mt-3">
                    <button type="submit" form="cancelPagesForm" class="btn btn-sm portal-btn-ghost">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-portal-primary"><i class="fas fa-link me-1"></i>Connect Pages</button>
                </div>
            </form>
            <form method="POST" action="{{ route('portal.crm.integrations.facebook.pages.cancel') }}" id="cancelPagesForm" class="d-none">@csrf</form>
            @endif

            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
                <a href="{{ route('portal.crm.integrations.facebook.connect') }}" target="_blank" rel="noopener" class="btn btn-sm text-white" style="background: #1877f2;">
                    <i class="fab fa-facebook me-1"></i>{{ $connectedCount ? 'Connect another Page' : 'Connect Facebook Page' }}
                </a>
                @if($isAdmin && ($connectedCount || $search !== ''))
                <form method="GET" class="d-flex gap-2">
                    <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search Page or account…" style="min-width: 220px;">
                    <button class="btn btn-sm portal-btn-ghost" aria-label="Search"><i class="fas fa-search"></i></button>
                </form>
                @endif
            </div>
            <p class="small text-muted mb-3"><i class="fas fa-circle-info me-1"></i>Facebook Login opens in a new tab and comes back to this page with the Pages to choose from.</p>

            @if($connections->isNotEmpty())
            <div class="table-responsive mb-3">
                <table class="table portal-table align-middle mb-0">
                    <thead><tr><th>Page</th>@if($isAdmin)<th>Leads go to</th>@endif<th>Status</th><th class="text-center">Leads</th><th>Last lead</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @foreach($connections as $connection)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $connection->page_name }}</div>
                                <div class="small text-muted">Connected {{ $connection->created_at->format('d M Y') }}@if($connection->connected_by) by {{ $connection->connected_by }}@endif</div>
                            </td>
                            @if($isAdmin)
                            <td>
                                <div class="fw-semibold">{{ $connection->owner?->displayName() ?? '—' }}</div>
                                @if($connection->owner)<div class="small text-muted">{{ $connection->owner->isAgency() ? 'Agency' : 'Agent' }}</div>@endif
                            </td>
                            @endif
                            @include('portal.crm.integrations._connection_cells', ['connection' => $connection])
                            <td class="text-end text-nowrap">
                                <form method="POST" action="{{ route('portal.crm.integrations.facebook.sync', $connection->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm portal-btn-ghost" title="Fetch leads from the last 30 days (or since the last sync)"><i class="fas fa-rotate me-1"></i>Sync now</button>
                                </form>
                                <form method="POST" action="{{ route('portal.crm.integrations.facebook.destroy', $connection->id) }}" class="d-inline js-disconnect" data-page="{{ $connection->page_name }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Disconnect</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($isAdmin)<div>{{ $connections->links('pagination::bootstrap-5') }}</div>@endif
            @elseif($isAdmin && $search !== '')
            <p class="text-muted small mb-0">No connected Pages match “{{ $search }}”.</p>
            @endif
        @endif

        <div class="intg-steps mt-4">
            <div class="intg-step"><span class="intg-step__num">1</span><div class="fw-semibold">Connect the Page</div><div class="text-muted">Log in with Facebook and pick the Pages that run the lead ads — each Page goes to one account.</div></div>
            <div class="intg-step"><span class="intg-step__num">2</span><div class="fw-semibold">Leads arrive instantly</div><div class="text-muted">Each form submission becomes a lead — name, email, phone and answers included.</div></div>
            <div class="intg-step"><span class="intg-step__num">3</span><div class="fw-semibold">Sorted by ad</div><div class="text-muted">The ad's name is the lead's Source, so you can filter and report per campaign.</div></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if($isAdmin && $pendingPages)
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>
<script>
    // Agency / agent pickers: searched on the server, 20 per request, more on scroll.
    if (window.jQuery) {
        jQuery('.js-account-picker').each(function () {
            jQuery(this).select2({
                placeholder: this.dataset.placeholder,
                allowClear: true,
                width: '100%',
                ajax: {
                    url: "{{ route('portal.crm.integrations.accounts') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function (params) { return { q: params.term || '', page: params.page || 1 }; },
                    processResults: function (data) { return data; },
                },
            });
        });
    }
</script>
@endif
<script>
    // Confirm before disconnecting a Page.
    document.querySelectorAll('.js-disconnect').forEach(function (form) {
        form.addEventListener('submit', async function (e) {
            if (form.dataset.confirmed) return;
            e.preventDefault();
            if (await window.portalConfirm({ title: 'Disconnect ' + form.dataset.page + '?', message: 'New Facebook leads from this Page will stop coming in. Leads already in the CRM stay.', confirmText: 'Disconnect', tone: 'danger' })) {
                form.dataset.confirmed = '1';
                form.requestSubmit();
            }
        });
    });
</script>
@endpush
