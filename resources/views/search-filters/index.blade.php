@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('crm.app', 'properties') }}">Properties</a></li>
    <li class="breadcrumb-item active" aria-current="page">Search Filters</li>
@endsection

@include('search-filters._styles')

@php
    $pageIcons = ['home' => 'fa-home', 'properties' => 'fa-building', 'commercial' => 'fa-briefcase', 'premium' => 'fa-crown', 'offplan' => 'fa-drafting-compass'];
    $pageUrls = ['home' => '/', 'properties' => '/properties', 'commercial' => '/commercial', 'premium' => '/premium-properties', 'offplan' => null];
    $canEdit = $cmsUser->can('filters.edit');
    // Home: fixed filters — edit labels / values only (no add, copy, reorder, on/off or delete).
    $isHome = $page === 'home';
    $canArrange = $canEdit && !$isHome;
@endphp

@section('content')
<div class="sf">
    <div class="sf-head">
        <div>
            <h4>Search Filters</h4>
            <p>Choose the filters visitors can use on each listing page, how each one looks and which values it offers. Every page has its own set.</p>
        </div>
        <div class="sf-actions">
            @if($cmsUser->can('filters.create') && !$isHome)
            <button type="button" class="sf-btn sf-btn--ghost" data-bs-toggle="modal" data-bs-target="#copyModal"><i class="fas fa-copy"></i> Copy from another page</button>
            <a href="{{ route('cms.filters.create', ['page' => $page]) }}" class="sf-btn sf-btn--primary"><i class="fas fa-plus"></i> Add filter</a>
            @endif
        </div>
    </div>

    <div class="sf-pages">
        @foreach(\App\Models\SearchFilter::PAGES as $key => $name)
            @php $c = $counts->get($key); @endphp
            <a href="{{ route('cms.filters.index', ['page' => $key]) }}" class="sf-page {{ $page === $key ? 'is-active' : '' }}">
                <span class="sf-page__icon"><i class="fas {{ $pageIcons[$key] }}"></i></span>
                <span>
                    <strong>{{ $name }}</strong>
                    <span>@if($key === 'home'){{ (int) ($c->total ?? 0) }} fixed filters @else{{ (int) ($c->total ?? 0) }} {{ (int) ($c->total ?? 0) === 1 ? 'filter' : 'filters' }} · {{ (int) ($c->active ?? 0) }} on @endif</span>
                </span>
            </a>
        @endforeach
    </div>

    <div class="sf-note">
        <i class="fas fa-info-circle"></i>
        <div>
            @if($isHome)
                The <strong>Home banner search</strong> always has these filters in this layout (Buy / Rent and Ready / Off-plan tabs, Property type, Bedrooms, Price).
                You can't add or remove them, but you can open one to change its <strong>labels</strong> and <strong>values</strong> — from the database or your own list — and the price slider's range.
            @elseif($pageUrls[$page])
                These filters show on the <strong>{{ \App\Models\SearchFilter::PAGES[$page] }}</strong> page (<a href="{{ url($pageUrls[$page]) }}" target="_blank" rel="noopener">{{ $pageUrls[$page] }}</a>) in this order. Drag a row to move it; switch one off to hide it without deleting it.
            @else
                The <strong>Off-plan</strong> set is ready for an off-plan listing page — the website has no separate off-plan page yet.
            @endif
            @unless($isHome) Each page's set is separate — the Home search has its own tab. @endunless
            Their database values (property types, locations, bedrooms, amenities, …) are managed in <a href="{{ route('crm.app', 'master/property-options') }}">CRM › Master › Property Options</a>.
        </div>
    </div>

    <div class="sf-card">
        <div class="sf-card__head">
            <div>
                <div class="sf-card__title">{{ \App\Models\SearchFilter::PAGES[$page] }} page filters</div>
                <div class="sf-card__sub">{{ $isHome ? 'Fixed filters — edit to change labels and values.' : 'Top to bottom = the order visitors see them.' }}</div>
            </div>
        </div>

        @if($filters->isEmpty())
            <div class="sf-empty">
                <div class="sf-empty__icon"><i class="fas fa-sliders-h"></i></div>
                <h6>No filters on this page yet</h6>
                <p>Add filters one by one, or copy another page's filters and adjust them.</p>
                @if($cmsUser->can('filters.create'))
                <div class="sf-actions justify-content-center">
                    <button type="button" class="sf-btn sf-btn--ghost" data-bs-toggle="modal" data-bs-target="#copyModal"><i class="fas fa-copy"></i> Copy from another page</button>
                    <a href="{{ route('cms.filters.create', ['page' => $page]) }}" class="sf-btn sf-btn--primary"><i class="fas fa-plus"></i> Add filter</a>
                </div>
                @endif
            </div>
        @else
            <ul class="sf-list" id="filterList">
                @foreach($filters as $filter)
                    @php
                        $field = $fields[$filter->field] ?? null;
                        [$typeName, , $typeIcon] = \App\Models\SearchFilter::TYPES[$filter->type] ?? [$filter->type, '', 'fa-filter'];
                    @endphp
                    <li class="sf-row {{ $filter->status ? '' : 'is-off' }}" data-id="{{ $filter->id }}">
                        <span class="sf-drag" title="{{ $isHome ? 'Fixed position' : 'Drag to reorder' }}">@if($canArrange)<i class="fas fa-grip-vertical"></i>@elseif($isHome)<i class="fas fa-lock" style="font-size:.75rem"></i>@endif</span>
                        <span class="sf-type-icon t-{{ $filter->type }}"><i class="fas {{ $typeIcon }}"></i></span>
                        <span class="sf-name">
                            <strong>{{ $filter->label() }}</strong>
                            <code>{{ $filter->field }}</code><small>{{ $field['label'] ?? 'Unknown field' }}</small>
                        </span>
                        <span class="sf-col-type"><span class="sf-chip"><i class="fas {{ $typeIcon }}"></i> {{ $typeName }}</span></span>
                        <span class="sf-col-options">
                            @if($filter->hasOptions())
                                @if($filter->options_source === 'custom')
                                    <span class="sf-chip is-custom"><i class="fas fa-list"></i> Custom · {{ $filter->options_count }} {{ $filter->options_count === 1 ? 'option' : 'options' }}</span>
                                @else
                                    <span class="sf-chip is-db"><i class="fas fa-database"></i> From the database</span>
                                @endif
                            @elseif($filter->type === 'text')
                                <span class="sf-chip"><i class="fas fa-search"></i> Contains the text</span>
                            @else
                                @php
                                    $min = $filter->setting('min', 0);
                                    $max = $filter->setting('max');
                                    $compare = ['min' => 'At least', 'max' => 'At most', 'exact' => 'Exactly'][$filter->setting('compare', 'min')] ?? 'At least';
                                @endphp
                                <span class="sf-chip">{{ $filter->type === 'number' ? $compare . ' · ' : '' }}{{ number_format((float) $min) }} – {{ $max ? number_format((float) $max) : 'auto' }}</span>
                            @endif
                        </span>
                        <span>
                            @unless($isHome)
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input js-toggle" type="checkbox" data-id="{{ $filter->id }}" {{ $filter->status ? 'checked' : '' }} {{ $canEdit ? '' : 'disabled' }} title="Show on the website">
                            </div>
                            @endunless
                        </span>
                        <span class="sf-row-actions">
                            @if($canEdit)
                            <a href="{{ route('cms.filters.edit', $filter) }}" class="sf-icon-btn" title="Edit"><i class="fas fa-pen"></i></a>
                            @endif
                            @if($cmsUser->can('filters.delete') && !$isHome)
                            <button type="button" class="sf-icon-btn is-danger js-delete" data-id="{{ $filter->id }}" data-name="{{ $filter->label() }}" title="Delete"><i class="fas fa-trash"></i></button>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="sf-api">
            <i class="fas fa-code"></i> API (key-value):
            <code>GET {{ url('/api/search-filters/' . $page) }}</code>
        </div>
    </div>
</div>

@unless($isHome)
{{-- Copy one page's filters onto another --}}
<div class="modal fade" id="copyModal" tabindex="-1" aria-labelledby="copyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content sf" method="POST" action="{{ route('cms.filters.copy') }}" style="border-radius: 18px;">
            @csrf
            <input type="hidden" name="to" value="{{ $page }}">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="copyModalLabel">Copy filters to {{ \App\Models\SearchFilter::PAGES[$page] }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Copy from</label>
                <div class="sf-choices mb-3" style="grid-template-columns: 1fr 1fr;">
                    @foreach(\App\Models\SearchFilter::listingPages() as $key => $name)
                        @continue($key === $page)
                        <label class="sf-choice">
                            <input type="radio" name="from" value="{{ $key }}" {{ $key === ($page === 'properties' ? 'commercial' : 'properties') ? 'checked' : '' }} required>
                            <i class="fas {{ $pageIcons[$key] }} sf-choice__icon"></i>
                            <span><strong>{{ $name }}</strong><span>{{ (int) ($counts->get($key)->total ?? 0) }} filters</span></span>
                        </label>
                    @endforeach
                </div>
                <label class="form-label">What happens to this page's filters</label>
                <div class="sf-choices" style="grid-template-columns: 1fr;">
                    <label class="sf-choice">
                        <input type="radio" name="mode" value="merge" checked>
                        <i class="fas fa-plus sf-choice__icon"></i>
                        <span><strong>Keep them, add the missing ones</strong><span>Filters this page already has stay as they are.</span></span>
                    </label>
                    <label class="sf-choice">
                        <input type="radio" name="mode" value="replace">
                        <i class="fas fa-sync-alt sf-choice__icon"></i>
                        <span><strong>Replace them with an exact copy</strong><span>This page's current filters are removed first.</span></span>
                    </label>
                </div>
                <p class="sf-help mt-3 mb-0">After copying, each page's filters are separate — editing one page never changes the other.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="sf-btn sf-btn--ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="sf-btn sf-btn--primary"><i class="fas fa-copy"></i> Copy filters</button>
            </div>
        </form>
    </div>
</div>
@endunless
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
    $(function () {
        const token = '{{ csrf_token() }}';
        const base = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/filters/";

        const list = document.getElementById('filterList');
        const canEdit = @json($canArrange);
        if (list && canEdit) {
            Sortable.create(list, {
                handle: '.sf-drag',
                animation: 160,
                onEnd: function () {
                    const ids = Array.from(list.querySelectorAll('.sf-row')).map((row) => row.dataset.id);
                    $.post("{{ route('cms.filters.reorder') }}", { _token: token, ids: ids })
                        .fail(() => alert('The new order could not be saved. Please reload and try again.'));
                },
            });
        }

        $('.js-toggle').on('change', function () {
            const input = this;
            $.post(base + input.dataset.id + '/toggle-status', { _token: token })
                .done((res) => $(input).closest('.sf-row').toggleClass('is-off', !res.status))
                .fail(() => { input.checked = !input.checked; alert('Could not change the status.'); });
        });

        $('.js-delete').on('click', function () {
            const btn = this;
            if (!confirm('Delete the "' + btn.dataset.name + '" filter from this page?')) return;
            $.ajax({ url: base + btn.dataset.id, type: 'DELETE', data: { _token: token } })
                .done(() => $(btn).closest('.sf-row').slideUp(180, function () { $(this).remove(); }))
                .fail(() => alert('Could not delete the filter.'));
        });
    });
</script>
@endpush
