{{-- Filters + stats cards + table + pagination — reloaded via AJAX (LeadController::index
     with an XHR header) after Create/Edit/Delete so the page never has to fully reload. --}}
@php($activeFilterCount = collect(['search', 'owner_id', 'agent_id', 'stage_id', 'source_id', 'tag_id', 'date_from', 'date_to', 'quick'])->filter(fn ($key) => filled($filters[$key] ?? null))->count())
@php($filterSet = fn (string ...$keys) => collect($keys)->contains(fn ($key) => filled(request($key))) ? 'is-set' : '')
@php($quick = $filters['quick'] ?? '')
<div class="portal-card portal-filter-bar mb-3">
    <form method="GET" class="portal-filter-bar__form">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <input type="hidden" name="quick" value="{{ $quick }}">
        <div class="portal-filter-field portal-filter-field--search {{ $filterSet('search') }}">
            <label class="visually-hidden" for="leadFilterSearch">Search</label>
            <div class="portal-filter-control">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="leadFilterSearch" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name, email or phone" autocomplete="off">
            </div>
        </div>
        @if($isAdmin)
        <div class="portal-filter-field {{ $filterSet('owner_id') }}">
            <label class="visually-hidden" for="leadFilterOwner">Owner</label>
            <div class="portal-filter-control">
                <i class="fas fa-building-user" aria-hidden="true"></i>
                <select id="leadFilterOwner" name="owner_id" class="form-select">
                    <option value="">All owners</option>
                    @foreach($owners as $owner)
                    <option value="{{ $owner->id }}" {{ (string) request('owner_id') === (string) $owner->id ? 'selected' : '' }}>{{ $owner->displayName() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @endif
        @if($agencyAgents->isNotEmpty() || $isAgencyViewer)
        <div class="portal-filter-field {{ $filterSet('agent_id') }}">
            <label class="visually-hidden" for="leadFilterAgent">Agent</label>
            <div class="portal-filter-control">
                <i class="fas fa-user-tie" aria-hidden="true"></i>
                <select id="leadFilterAgent" name="agent_id" class="form-select">
                    <option value="">All agents</option>
                    <option value="unassigned" {{ request('agent_id') === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
                    @foreach($agencyAgents as $agencyAgent)
                    <option value="{{ $agencyAgent->id }}" {{ (string) request('agent_id') === (string) $agencyAgent->id ? 'selected' : '' }}>{{ $agencyAgent->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @endif
        <div class="portal-filter-field {{ $filterSet('stage_id') }}">
            <label class="visually-hidden" for="leadFilterStage">Stage</label>
            <div class="portal-filter-control">
                <i class="fas fa-layer-group" aria-hidden="true"></i>
                <select id="leadFilterStage" name="stage_id" class="form-select">
                    <option value="">All stages</option>
                    @foreach($stages as $stage)
                    <option value="{{ $stage->id }}" {{ (string) request('stage_id') === (string) $stage->id ? 'selected' : '' }}>{{ $stage->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="portal-filter-field {{ $filterSet('source_id') }}">
            <label class="visually-hidden" for="leadFilterSource">Source</label>
            <div class="portal-filter-control">
                <i class="fas fa-share-nodes" aria-hidden="true"></i>
                <select id="leadFilterSource" name="source_id" class="form-select">
                    <option value="">All sources</option>
                    @foreach($sources as $source)
                    <option value="{{ $source->id }}" {{ (string) request('source_id') === (string) $source->id ? 'selected' : '' }}>{{ $source->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="portal-filter-field {{ $filterSet('tag_id') }}">
            <label class="visually-hidden" for="leadFilterTag">Tag</label>
            <div class="portal-filter-control">
                <i class="fas fa-tag" aria-hidden="true"></i>
                <select id="leadFilterTag" name="tag_id" class="form-select">
                    <option value="">All tags</option>
                    @foreach($tags as $tag)
                    <option value="{{ $tag->id }}" {{ (string) request('tag_id') === (string) $tag->id ? 'selected' : '' }}>{{ $tag->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="portal-filter-field portal-filter-field--range {{ $filterSet('date_from', 'date_to') }}">
            <label class="visually-hidden" for="leadFilterFrom">Received</label>
            <div class="portal-filter-control portal-filter-control--range" title="Received between">
                <i class="far fa-calendar" aria-hidden="true"></i>
                <input type="date" id="leadFilterFrom" name="date_from" value="{{ request('date_from') }}" class="form-control" aria-label="Received from">
                <span class="portal-filter-range-sep">to</span>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control" aria-label="Received to">
            </div>
        </div>
        {{-- Filters apply as you type / choose (index.blade.php, runLeadFilters); the button is a fallback. --}}
        <div class="portal-filter-actions">
            <button type="submit" class="portal-filter-submit" title="Apply filters" aria-label="Apply filters"><i class="fas fa-filter" aria-hidden="true"></i></button>
            @if($activeFilterCount)
            <a href="{{ route('portal.crm.leads.index', ['status' => request('status')]) }}" class="portal-filter-bar__clear js-clear-lead-filters" title="Clear all filters"><i class="fas fa-xmark" aria-hidden="true"></i> Clear <span class="portal-filter-bar__badge">{{ $activeFilterCount }}</span></a>
            @endif
        </div>
    </form>
</div>


<div class="portal-card p-3 portal-leads-table-card">
    {{-- Quick filters — counts follow the filters above; clicking one also filters the list to it
         (quick=…, LeadService::QUICK_FILTERS), clicking it again or Total shows everything. The table
         script moves this into its top row, between "Show N leads" and Quick search. --}}
    <div class="portal-lead-quick" id="leadQuickFilters" role="group" aria-label="Quick filters">
        @foreach([
            ['', 'Total', 'fa-users', 'indigo', 'All received leads', $quickCounts['total']],
            ['active_24h', 'Active 24h', 'fa-bolt', 'teal', 'Received in the last 24 hours, or on the website then', $quickCounts['active_24h']],
            ['last_7d', 'Last 7 days', 'fa-calendar-week', 'amber', 'Received in the last 7 days', $quickCounts['last_7d']],
            ['website', 'Website Insights', 'fa-chart-line', 'rose', 'Came from a tracked website visitor', $quickCounts['website']],
        ] as [$key, $label, $icon, $tone, $hint, $count])
        <button type="button" class="portal-lead-quick__chip tone-{{ $tone }} {{ $quick === $key ? 'is-active' : '' }}" data-lead-quick="{{ $key }}" aria-pressed="{{ $quick === $key ? 'true' : 'false' }}" title="{{ $hint }}">
            <i class="fas {{ $icon }}" aria-hidden="true"></i>{{ $label }}<strong>{{ number_format($count) }}</strong>
        </button>
        @endforeach
    </div>
    {{-- Shown while filters / pages / sorting load (#leadsListingWrapper.leads-loading). --}}
    <div class="portal-leads-loader" role="status" aria-live="polite"><span class="portal-leads-loader__box"><span class="portal-leads-loader__spin" aria-hidden="true"></span>Loading leads…</span></div>
    <div class="table-responsive">
        {{-- Server-side DataTable (index.blade.php): data-total-rows = leads matching everything incl. the
             Quick search (what "select all" covers); data-listing-total = before the Quick search. --}}
        <table class="table portal-table mb-0" id="leadsDataTable" data-has-rows="{{ $listingTotal ? 'true' : 'false' }}" data-total-rows="{{ $leads->total() }}" data-listing-total="{{ $listingTotal }}" data-sort="{{ $listing['sort'] }}" data-dir="{{ $listing['dir'] }}" data-per-page="{{ $listing['per_page'] }}" data-q="{{ $listing['q'] }}">
            <thead>
                <tr>
                    <th style="width: 2.5rem;"><input type="checkbox" class="form-check-input" id="selectAllLeads"></th>
                    {{-- Row number in the current order / page — filled in by the table script. --}}
                    <th class="portal-lead-sn">#</th>
                    {{-- The viewer's shown fields in their saved order — drag a header (grip) to reorder; saved per user. --}}
                    @foreach($leadTableColumns as $column)
                    <th data-column-key="{{ $column }}" @if(!($leadTableFields[$column]['sortable'] ?? true) || $column === 'tags') data-orderable="false" @endif @if($column !== 'lead') class="portal-th-draggable" @endif>
                        @if($column !== 'lead')<span class="portal-th-grip" title="Drag to move this column" aria-hidden="true"><i class="fas fa-grip-vertical"></i></span>@endif{{ $leadTableFields[$column]['label'] ?? ucfirst($column) }}
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @if($leads->isNotEmpty())
                @include('portal.crm.leads._lead_rows')
                @elseif(!$listingTotal)
                {{-- With leads but no Quick search match, DataTables shows its own "No matching leads". --}}
                <tr>
                    <td colspan="{{ count($leadTableColumns) + 2 }}" class="portal-empty" data-empty-cell>
                        <div class="portal-empty-icon"><i class="fas fa-address-book"></i></div>
                        <div class="fw-semibold mb-1">No leads {{ $activeFilterCount ? 'match these filters' : 'yet' }}</div>
                        <div style="font-size: 0.85rem;">They'll show up here the moment a visitor enquires about {{ $isAdmin ? 'a' : 'one of your' }} listing{{ $isAdmin ? '' : 's' }}.</div>
                    </td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
