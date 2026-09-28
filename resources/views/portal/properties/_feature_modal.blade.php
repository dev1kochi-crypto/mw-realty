{{--
    "Make a listing premium" popup — shared by the Properties / Commercial listing cards and the Featured menu.
    Books a start + end date through POST portal/properties/{id}/feature (plan quota enforced server-side,
    see FeaturedListingService). Open it with window.openFeatureModal({ id, title, thumb, ref }), or with no
    argument to show the listing picker (pass $pickerUrl — the JSON search endpoint — to include one).

    Expects: $isAdmin, $featuredQuota (null for Super Admin); optional $pickerUrl (portal.featured.eligible).
--}}
@php
    $maxDays = $isAdmin ? \App\Services\FeaturedListingService::MAX_DAYS : (($featuredQuota['max_days'] ?? null) ?: \App\Services\FeaturedListingService::MAX_DAYS);
    $perMonth = (bool) ($featuredQuota['per_month'] ?? false);
    $presets = array_values(array_filter([7, 15, 30, 60, 90], fn ($d) => $d <= $maxDays));
    if (!$isAdmin && ($featuredQuota['max_days'] ?? null) && !in_array($maxDays, $presets, true)) {
        $presets[] = $maxDays;
    }
    $hasPicker = !empty($pickerUrl);
    // How many listings the picker lets you tick at once: Super Admin up to the batch limit; an owner
    // up to the plan's free slots ("at a time" plans), or the monthly allowance (the server checks the
    // start month). The server re-checks all of this on save.
    $batchLimit = \App\Http\Controllers\Portal\PortalFeaturedController::BATCH_LIMIT;
    $pickLimit = $isAdmin
        ? $batchLimit
        : min($batchLimit, (int) ($perMonth ? ($featuredQuota['limit'] ?? 0) : ($featuredQuota['remaining'] ?? 0)));
@endphp
<div class="modal fade fm" id="featureModal" tabindex="-1" aria-labelledby="featureModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered {{ $hasPicker ? 'modal-lg' : '' }}">
        <form class="modal-content fm__content" id="featureForm" novalidate>
            <div class="fm__head">
                <span class="fm__head-icon"><i class="fas fa-star"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <h5 class="fm__title" id="featureModalTitle">Make a listing premium</h5>
                    <p class="fm__subtitle">Premium listings get a badge and appear in the Premium section on the website.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="fm__body">
                <div class="fm__chips">
                    @if($isAdmin)
                        <span class="fm__chip"><i class="fas fa-shield-alt"></i> Super Admin &middot; no plan limits</span>
                        <span class="fm__chip"><i class="far fa-clock"></i> Up to {{ $maxDays }} days, or no end date</span>
                    @else
                        <span class="fm__chip {{ ($featuredQuota['remaining'] ?? 0) === 0 ? 'is-warn' : '' }}">
                            <i class="fas fa-layer-group"></i>
                            {{ $featuredQuota['used'] }} of {{ $featuredQuota['limit'] }} {{ $perMonth ? 'used this month' : 'in use' }}
                        </span>
                        <span class="fm__chip"><i class="far fa-clock"></i> {{ ($featuredQuota['max_days'] ?? null) ? 'Max ' . $featuredQuota['max_days'] . ' days each' : 'No limit on length' }}</span>
                        @if($perMonth)
                        <span class="fm__chip"><i class="fas fa-redo"></i> Resets {{ now()->addMonthNoOverflow()->startOfMonth()->format('d M') }}</span>
                        @endif
                    @endif
                </div>

                {{-- Listing: picker (Featured menu) or the preselected card (listing pages) --}}
                <div class="fm__section">
                    <div class="fm__label d-flex justify-content-between align-items-center">
                        <span id="featureListingLabel">Listing</span>
                        @if($hasPicker)
                        <span class="fm__pick-count" id="featurePickCount" aria-live="polite"></span>
                        @endif
                    </div>
                    @if($hasPicker)
                        <div class="fm__picker" id="featurePicker">
                            <div class="fm__picker-search">
                                <i class="fas fa-search"></i>
                                <input type="search" class="form-control" id="featurePickerSearch" placeholder="Search by title, reference or location{{ $isAdmin ? ', agent / agency' : '' }}" autocomplete="off">
                            </div>
                            {{-- Filled from $pickerUrl 20 at a time; the next page loads as the list scrolls.
                                 Tick several to book them together, up to the plan's free premium slots. --}}
                            <div class="fm__picker-list" id="featurePickerList" role="group" aria-label="Choose listings" aria-busy="false"></div>
                            <div class="fm__picker-status" id="featurePickerStatus" aria-live="polite"></div>
                        </div>
                        <div class="fm__picked d-none" id="featurePicked" aria-label="Selected listings"></div>
                    @endif
                    <div class="fm__selected {{ $hasPicker ? 'd-none' : '' }}" id="featureSelected">
                        <img src="" alt="" id="featureSelectedThumb">
                        <div class="min-w-0">
                            <div class="fm__option-title" id="featureSelectedTitle"></div>
                            <div class="fm__option-meta" id="featureSelectedRef"></div>
                        </div>
                    </div>
                </div>

                {{-- Dates --}}
                <div class="fm__section">
                    <div class="fm__label d-flex justify-content-between align-items-center">
                        <span>Premium period</span>
                        <span class="fm__presets" id="featurePresets">
                            @foreach($presets as $d)
                            <button type="button" class="fm__preset" data-days="{{ $d }}">{{ $d }} days</button>
                            @endforeach
                        </span>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fm__field-label" for="featureStart">Start date</label>
                            <input type="date" class="form-control" id="featureStart" required>
                            <div class="form-text d-none" id="featureStartHint">Already live — the start date can't change.</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fm__field-label" for="featureEnd">End date</label>
                            <input type="date" class="form-control" id="featureEnd" {{ $isAdmin ? '' : 'required' }}>
                        </div>
                    </div>
                    @if($isAdmin)
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="featureNoEnd">
                        <label class="form-check-label small" for="featureNoEnd">No end date — keep it premium until I stop it</label>
                    </div>
                    @endif

                    <div class="fm__summary" id="featureSummary">
                        <i class="far fa-calendar-check"></i>
                        <span id="featureSummaryText">Pick the dates.</span>
                    </div>
                </div>

                @unless($isAdmin)
                <p class="fm__note">
                    <i class="fas fa-info-circle"></i>
                    @if($perMonth)
                        Uses 1 premium from the start date's month, even if you stop it early. Cancelling before it starts gives it back.
                    @else
                        Uses 1 premium slot for the whole period. The slot frees up when it ends or you stop it.
                    @endif
                </p>
                @endunless

                <div class="alert alert-danger small mb-0 d-none" id="featureError" role="alert"></div>
            </div>

            <div class="fm__foot">
                <button type="button" class="btn btn-portal-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn fm__submit btn-sm" id="featureSubmit"><i class="fas fa-star me-1"></i><span id="featureSubmitText">Make premium now</span></button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('featureModal');
    if (!modalEl) return;
    const modal = new bootstrap.Modal(modalEl);
    const MAX_DAYS = {{ (int) $maxDays }};
    const IS_ADMIN = @json((bool) $isAdmin);
    const HAS_PICKER = @json($hasPicker);
    const form = document.getElementById('featureForm');
    const startEl = document.getElementById('featureStart');
    const endEl = document.getElementById('featureEnd');
    const noEndEl = document.getElementById('featureNoEnd');
    const errorBox = document.getElementById('featureError');
    const submitBtn = document.getElementById('featureSubmit');
    const submitText = document.getElementById('featureSubmitText');
    const summary = document.getElementById('featureSummary');
    const summaryText = document.getElementById('featureSummaryText');
    const selectedBox = document.getElementById('featureSelected');
    const titleEl = document.getElementById('featureModalTitle');
    const startHint = document.getElementById('featureStartHint');
    const pickerBox = document.getElementById('featurePicker');
    let propertyId = null;
    // 'create' books a new feature (POST); 'edit' changes the dates of an existing one (PUT).
    let mode = 'create';
    // When editing a feature that is already live: its fixed start date (yyyy-mm-dd), else null.
    let liveStart = null;

    // Local-date helpers — <input type="date"> works in yyyy-mm-dd without a timezone.
    const pad = n => String(n).padStart(2, '0');
    const toIso = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    const fromIso = s => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
    const addDays = (d, n) => { const c = new Date(d); c.setDate(c.getDate() + n); return c; };
    const fmt = d => d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
    const today = () => { const t = new Date(); return new Date(t.getFullYear(), t.getMonth(), t.getDate()); };
    const dayCount = (a, b) => Math.round((b - a) / 86400000) + 1;

    function syncBounds() {
        const todayIso = toIso(today());
        if (liveStart) {
            // Live feature: the start is fixed (it may be in the past); the end can't be before today.
            startEl.removeAttribute('min');
            startEl.value = liveStart;
        } else {
            startEl.min = todayIso;
            if (startEl.value && startEl.value < todayIso) startEl.value = todayIso;
        }
        const start = startEl.value ? fromIso(startEl.value) : today();
        endEl.min = liveStart && liveStart < todayIso ? todayIso : toIso(start);
        endEl.max = toIso(addDays(start, MAX_DAYS - 1));
        if (endEl.value && (endEl.value < endEl.min || endEl.value > endEl.max)) {
            endEl.value = endEl.value < endEl.min ? endEl.min : endEl.max;
        }
    }

    function setPreset(days) {
        const start = startEl.value ? fromIso(startEl.value) : today();
        if (!startEl.value) startEl.value = toIso(start);
        if (noEndEl) noEndEl.checked = false;
        endEl.disabled = false;
        endEl.value = toIso(addDays(start, Math.min(days, MAX_DAYS) - 1));
        render();
    }

    function render() {
        syncBounds();
        const noEnd = noEndEl && noEndEl.checked;
        endEl.disabled = !!noEnd;
        const start = startEl.value ? fromIso(startEl.value) : null;
        const end = !noEnd && endEl.value ? fromIso(endEl.value) : null;
        const future = !liveStart && start && start > today();

        document.querySelectorAll('.fm__preset').forEach(btn => {
            btn.classList.toggle('is-active', !!(start && end && dayCount(start, end) === Number(btn.dataset.days)));
        });

        summary.classList.toggle('is-scheduled', !!future);
        summary.classList.remove('is-invalid');
        if (!start) {
            summaryText.textContent = 'Pick the dates.';
        } else if (noEnd) {
            summaryText.textContent = (liveStart ? 'Live since ' + fmt(start) : future ? 'Starts ' + fmt(start) : 'Starts now') + ' · no end date';
        } else if (!end) {
            summaryText.textContent = 'Pick an end date.';
        } else {
            const days = dayCount(start, end);
            const from = liveStart ? 'live since ' + fmt(start) : future ? fmt(start) : 'today';
            summaryText.textContent = days + ' day' + (days === 1 ? '' : 's') + ' in total · ' + from + ' → ' + fmt(end)
                + (future ? ' · scheduled, goes live automatically on the start date' : '');
            if (days > MAX_DAYS) summary.classList.add('is-invalid');
        }
        submitText.textContent = mode === 'edit' ? 'Save dates' : future ? 'Schedule premium' : 'Make premium now';
    }

    function showListing(item) {
        propertyId = item ? item.id : null;
        if (!item) { selectedBox.classList.add('d-none'); return; }
        document.getElementById('featureSelectedThumb').src = item.thumb || 'https://placehold.co/96x72?text=No+Image';
        document.getElementById('featureSelectedTitle').textContent = item.title || '';
        document.getElementById('featureSelectedRef').textContent = (HAS_PICKER ? 'Selected · ' : '') + (item.ref ? 'Ref: ' + item.ref : '');
        // With the picker, this keeps the choice visible even after searching for something else.
        selectedBox.classList.remove('d-none');
    }

    function setMode(next, live) {
        mode = next;
        liveStart = live || null;
        startEl.disabled = !!liveStart;
        startHint.classList.toggle('d-none', !liveStart);
        pickerBox?.classList.toggle('d-none', mode === 'edit');
        picker?.setHidden(mode === 'edit');
        titleEl.textContent = mode === 'edit' ? 'Edit premium dates' : 'Make a listing premium';
        errorBox.classList.add('d-none');
        submitBtn.disabled = false;
        form.reset();
    }

    /** New feature: item = { id, title, thumb, ref }, or nothing to pick from the list. */
    window.openFeatureModal = function (item) {
        setMode('create');
        startEl.value = toIso(today());
        endEl.value = '';
        showListing(item || null);
        if (HAS_PICKER) picker.reset();
        setPreset(Math.min(30, MAX_DAYS));
        modal.show();
    };

    /**
     * Change an existing feature's dates: item = { id, title, thumb, ref, live, start, end } with
     * start/end as yyyy-mm-dd (end empty = no end date, Super Admin only).
     */
    window.openFeatureEditModal = function (item) {
        setMode('edit', item.live ? item.start : null);
        showListing(item);
        startEl.value = item.start || toIso(today());
        endEl.value = item.end || '';
        if (noEndEl) noEndEl.checked = !item.end;
        render();
        modal.show();
    };

    document.getElementById('featurePresets')?.addEventListener('click', e => {
        const btn = e.target.closest('.fm__preset');
        if (btn) setPreset(Number(btn.dataset.days));
    });
    [startEl, endEl].forEach(el => el.addEventListener('change', render));
    noEndEl?.addEventListener('change', render);

    /**
     * Listing picker, searched and paged on the server (portal.featured.eligible): 20 results per
     * request, the next page loads when the list is scrolled near the bottom, and typing searches
     * the database (debounced). Nothing is rendered up front, so it scales to any number of listings.
     *
     * Several listings can be ticked (up to PICK_LIMIT — the plan's free premium slots) and are
     * booked together with the same dates. Ticks survive searching and scrolling; the chips under
     * the list show what's picked.
     */
    const PICK_LIMIT = {{ (int) $pickLimit }};
    const picker = (function () {
        if (!HAS_PICKER) return null;
        const URL_BASE = @json($pickerUrl ?? '');
        const search = document.getElementById('featurePickerSearch');
        const list = document.getElementById('featurePickerList');
        const status = document.getElementById('featurePickerStatus');
        const picked = document.getElementById('featurePicked');
        const countEl = document.getElementById('featurePickCount');
        const listingLabel = document.getElementById('featureListingLabel');
        let term = '';
        let nextPage = 1;
        let loading = false;
        let requestId = 0; // drops responses from searches that have since been replaced
        let debounce = null;
        const items = new Map();    // loaded rows, id => item
        const selected = new Map(); // ticked listings, id => item (kept across searches)

        function setStatus(text) { status.textContent = text; status.classList.toggle('d-none', !text); }
        const full = () => selected.size >= PICK_LIMIT;

        function option(item) {
            const label = document.createElement('label');
            label.className = 'fm__option';
            const box = Object.assign(document.createElement('input'), { type: 'checkbox', name: 'feature_property[]', value: item.id });
            box.checked = selected.has(String(item.id));
            box.disabled = !box.checked && full();
            const img = Object.assign(document.createElement('img'), { src: item.thumb || 'https://placehold.co/96x72?text=No+Image', alt: '', loading: 'lazy' });
            const text = document.createElement('span');
            text.className = 'fm__option-text';
            const title = Object.assign(document.createElement('span'), { className: 'fm__option-title', textContent: item.title || '—' });
            const meta = Object.assign(document.createElement('span'), {
                className: 'fm__option-meta',
                textContent: [item.ref, item.place, IS_ADMIN ? item.owner : null].filter(Boolean).join(' · '),
            });
            text.append(title, meta);
            const commercial = item.segment === 'commercial';
            const tag = Object.assign(document.createElement('span'), { className: 'fm__tag' + (commercial ? ' fm__tag--commercial' : ''), textContent: commercial ? 'Commercial' : 'Property' });
            label.append(box, img, text, tag);
            if (!item.active) label.append(Object.assign(document.createElement('span'), { className: 'fm__tag fm__tag--muted', textContent: 'Inactive' }));
            label.classList.toggle('is-disabled', box.disabled);
            return label;
        }

        /** Chips, counter, disabled rows and the submit label, after any change to the selection. */
        function refresh() {
            const n = selected.size;
            countEl.textContent = PICK_LIMIT > 0
                ? n + ' of ' + PICK_LIMIT + ' selected' + (IS_ADMIN ? '' : ' · plan limit')
                : @json($isAdmin ? '' : 'No premium slots free on your plan');
            countEl.classList.toggle('is-full', PICK_LIMIT > 0 && full());
            listingLabel.textContent = PICK_LIMIT > 1 ? 'Listings' : 'Listing';

            list.querySelectorAll('input[type=checkbox]').forEach(box => {
                box.checked = selected.has(box.value);
                box.disabled = !box.checked && full();
                box.closest('.fm__option').classList.toggle('is-disabled', box.disabled);
            });

            picked.replaceChildren(...[...selected.values()].map(item => {
                const chip = document.createElement('span');
                chip.className = 'fm__chip-pick';
                chip.append(
                    Object.assign(document.createElement('img'), { src: item.thumb || 'https://placehold.co/48x36?text=%20', alt: '' }),
                    Object.assign(document.createElement('span'), { className: 'fm__chip-pick-text', textContent: item.title || item.ref || '—', title: [item.title, item.ref].filter(Boolean).join(' · ') }),
                );
                const remove = Object.assign(document.createElement('button'), { type: 'button', className: 'fm__chip-pick-remove', innerHTML: '&times;' });
                remove.setAttribute('aria-label', 'Remove ' + (item.title || 'listing'));
                remove.dataset.id = item.id;
                chip.append(remove);
                return chip;
            }));
            picked.classList.toggle('d-none', n === 0);
            if (mode === 'create') render();
        }

        function load() {
            if (loading || !nextPage) return;
            loading = true;
            const id = ++requestId;
            list.setAttribute('aria-busy', 'true');
            setStatus(nextPage === 1 ? 'Loading…' : 'Loading more…');
            const url = new URL(URL_BASE, location.origin);
            url.searchParams.set('page', nextPage);
            if (term) url.searchParams.set('q', term);
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(r => { if (!r.ok) throw new Error(); return r.json(); })
                .then(data => {
                    if (id !== requestId) return;
                    data.items.forEach(item => { items.set(String(item.id), item); list.append(option(item)); });
                    nextPage = data.next_page;
                    if (!list.children.length) {
                        setStatus(term ? 'No listings match “' + term + '”.' : @json($isAdmin ? 'No listings available to make premium.' : 'No listings available to make premium. Only active listings that aren\'t already premium or scheduled can be picked.'));
                    } else {
                        setStatus(nextPage ? '' : (list.children.length > 20 ? 'All ' + list.children.length + ' listings loaded.' : ''));
                    }
                    // A short first page may not fill the box enough to scroll — keep loading.
                    if (nextPage && list.scrollHeight <= list.clientHeight) setTimeout(load, 0);
                })
                .catch(() => { if (id === requestId) setStatus('Could not load listings. Scroll or type to try again.'); })
                .finally(() => { if (id === requestId) { loading = false; list.setAttribute('aria-busy', 'false'); } });
        }

        function restart() {
            requestId++; // cancel whatever is in flight
            loading = false;
            nextPage = 1;
            items.clear();
            list.replaceChildren();
            list.scrollTop = 0;
            load();
        }

        search.addEventListener('input', () => {
            clearTimeout(debounce);
            debounce = setTimeout(() => {
                const value = search.value.trim();
                if (value === term) return;
                term = value;
                restart();
            }, 300);
        });
        // Enter in the search box searches straight away instead of submitting the booking.
        search.addEventListener('keydown', e => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            clearTimeout(debounce);
            term = search.value.trim();
            restart();
        });
        list.addEventListener('scroll', () => {
            if (list.scrollTop + list.clientHeight >= list.scrollHeight - 80) load();
        });
        list.addEventListener('change', e => {
            const box = e.target.closest('input[type=checkbox]');
            if (!box) return;
            if (box.checked && !selected.has(box.value)) {
                if (full()) { box.checked = false; return; }
                selected.set(box.value, items.get(box.value) || { id: box.value });
            } else if (!box.checked) {
                selected.delete(box.value);
            }
            refresh();
        });
        picked.addEventListener('click', e => {
            const btn = e.target.closest('.fm__chip-pick-remove');
            if (!btn) return;
            selected.delete(String(btn.dataset.id));
            refresh();
        });

        return {
            reset() {
                clearTimeout(debounce);
                search.value = '';
                term = '';
                selected.clear();
                restart();
                refresh();
            },
            ids: () => [...selected.keys()].map(Number),
            count: () => selected.size,
            setHidden(hidden) {
                picked.classList.toggle('d-none', hidden || selected.size === 0);
                countEl.classList.toggle('d-none', hidden);
            },
        };
    })();

    const usingPicker = () => HAS_PICKER && mode === 'create';

    // Submit label says how many listings will be booked.
    const baseRender = render;
    render = function () {
        baseRender();
        if (usingPicker() && picker.count() > 1) {
            submitText.textContent = (startEl.value && fromIso(startEl.value) > today() ? 'Schedule ' : 'Make ') + picker.count() + ' listings premium';
        }
    };

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errorBox.classList.add('d-none');
        const noEnd = noEndEl && noEndEl.checked;
        let problem = null;
        if (usingPicker() ? picker.count() === 0 : !propertyId) problem = usingPicker() ? 'Tick at least one listing to make premium.' : 'Choose a listing to make premium.';
        else if (!startEl.value) problem = 'Choose a start date.';
        else if (!noEnd && !endEl.value) problem = IS_ADMIN ? 'Choose an end date, or tick "No end date".' : 'Choose an end date.';
        else if (!noEnd && dayCount(fromIso(startEl.value), fromIso(endEl.value)) > MAX_DAYS) problem = 'Premium can run for at most ' + MAX_DAYS + ' days.';
        if (problem) { errorBox.textContent = problem; errorBox.classList.remove('d-none'); return; }

        const dates = { start_date: liveStart ? null : startEl.value, end_date: noEnd ? null : endEl.value };
        // Picker (Featured menu): one batch request for every ticked listing, all-or-nothing.
        // Otherwise: book (POST) or re-date (PUT) the single listing.
        const request = usingPicker()
            ? { url: @json(route('portal.featured.store')), method: 'POST', body: { ...dates, property_ids: picker.ids() } }
            : { url: "{{ url('portal/properties') }}/" + propertyId + '/feature', method: mode === 'edit' ? 'PUT' : 'POST', body: dates };

        submitBtn.disabled = true;
        fetch(request.url, {
            method: request.method,
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(request.body),
        })
            .then(async r => {
                const data = await r.json().catch(() => ({}));
                if (!r.ok) throw new Error(Object.values(data.errors || {})[0]?.[0] || data.message || (mode === 'edit' ? 'Could not change the dates.' : 'Could not make the listing(s) premium.'));
                location.reload();
            })
            .catch(err => { errorBox.textContent = err.message; errorBox.classList.remove('d-none'); submitBtn.disabled = false; });
    });
})();
</script>
@endpush
