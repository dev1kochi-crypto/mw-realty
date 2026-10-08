@extends('portal.crm._layout')

@section('title', 'Leads')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<style>
    /* Loading state while filters / pages / sorting fetch (#leadsListingWrapper.leads-loading). */
    .portal-leads-table-card { position: relative; }
    .portal-leads-loader { display: none; position: absolute; inset: 0; z-index: 6; align-items: center; justify-content: center; pointer-events: none; }
    .portal-leads-loader__box { display: inline-flex; align-items: center; gap: .6rem; padding: .55rem 1.1rem; border-radius: 999px; background: #fff; box-shadow: 0 8px 28px rgba(15, 23, 42, .14); font-size: .85rem; font-weight: 600; color: #1e3a5f; }
    .portal-leads-loader__spin { width: 18px; height: 18px; border-radius: 50%; border: 2.5px solid rgba(30, 58, 95, .18); border-top-color: #c8102e; animation: portalLeadsSpin .7s linear infinite; }
    .portal-leads-table-card::before { content: ''; position: absolute; top: 0; left: 0; height: 3px; width: 35%; border-radius: 3px; background: linear-gradient(90deg, transparent, #c8102e, transparent); opacity: 0; z-index: 7; }
    #leadsListingWrapper.leads-loading .portal-leads-loader { display: flex; animation: portalLeadsFade .15s ease-out; }
    #leadsListingWrapper.leads-loading .portal-leads-table-card::before { opacity: 1; animation: portalLeadsBar 1s ease-in-out infinite; }
    #leadsListingWrapper.leads-loading .portal-leads-table-card table tbody { opacity: .35; transition: opacity .15s; }
    #leadsListingWrapper.leads-loading .portal-leads-table-card table,
    #leadsListingWrapper.leads-loading .portal-lead-quick,
    #leadsListingWrapper.leads-loading .dataTables_paginate { pointer-events: none; }
    #leadsListingWrapper.leads-loading .portal-filter-submit i::before { content: '\f110'; }
    #leadsListingWrapper.leads-loading .portal-filter-submit i { animation: portalLeadsSpin .8s linear infinite; }
    @keyframes portalLeadsSpin { to { transform: rotate(360deg); } }
    @keyframes portalLeadsBar { 0% { left: -35%; } 100% { left: 100%; } }
    @keyframes portalLeadsFade { from { opacity: 0; } to { opacity: 1; } }
</style>
@endpush

@section('crm-content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <div class="d-flex align-items-center gap-3">
            <div class="portal-section-title mb-0">{{ $isAdmin ? 'All Leads' : 'My Leads' }}</div>
            <span class="portal-selected-badge d-none" id="leadSelectedBadge">Selected Leads <span id="leadSelectedBadgeCount">0</span></span>
        </div>
        <p class="text-muted mb-0" style="font-size: 0.88rem;">Every enquiry a visitor sends about {{ $isAdmin ? 'any' : 'your' }} listing lands here. <a href="{{ route('portal.crm.leads.trashed') }}" class="portal-link-muted ms-1"><i class="fas fa-trash-can-arrow-up me-1"></i>View deleted leads</a></p>
    </div>
    {{-- Icon toolbar: each button shows its label on hover / keyboard focus (.portal-xbtn).
         [data-needs-selection] buttons stay disabled until leads are selected (updateBulkActionsBar). --}}
    <div class="portal-xbtn-bar">
        <button type="button" data-open-master="stages" data-needs-selection class="portal-xbtn" aria-label="Assign stage" title="Select leads first" disabled><i class="fas fa-layer-group"></i><span>Assign Stage</span></button>
        <button type="button" data-open-master="tags" data-needs-selection class="portal-xbtn" aria-label="Assign tag" title="Select leads first" disabled><i class="fas fa-tags"></i><span>Assign Tag</span></button>
        <button type="button" id="openLeadExportModal" data-needs-selection class="portal-xbtn" aria-label="Export" title="Select leads first" disabled><i class="fas fa-file-arrow-down"></i><span>Export</span></button>
        <button type="button" id="bulkDeleteLeadsBtn" data-needs-selection class="portal-xbtn portal-xbtn-danger" aria-label="Delete leads" title="Select leads first" disabled><i class="fas fa-trash-can"></i><span>Delete Leads</span></button>
        <span class="portal-xbtn-sep" aria-hidden="true"></span>
        <button type="button" id="openLeadTableFieldsModal" class="portal-xbtn" aria-label="Table fields"><i class="fas fa-table-columns"></i><span>Table Fields</span></button>
        <button type="button" id="openLeadImportModal" class="portal-xbtn" aria-label="Import"><i class="fas fa-cloud-arrow-up"></i><span>Import</span></button>
        @if($isAgencyViewer && $assignSetting)
        <button type="button" class="portal-xbtn" data-bs-toggle="modal" data-bs-target="#leadAssignModal" aria-label="Lead assignment: {{ $assignSetting->isManual() ? 'manual' : 'automatic round robin' }}"><i class="fas {{ $assignSetting->isManual() ? 'fa-hand-pointer' : 'fa-rotate' }}"></i><span>Lead Assignment<em class="lassign-xbtn-state">{{ $assignSetting->isManual() ? 'Manual' : 'Auto' }}</em></span></button>
        @endif
        @if($isAgencyViewer && $unassignedCount > 0 && $agencyAgents->isNotEmpty())
        <form method="POST" action="{{ route('portal.crm.leads.distribute') }}" class="m-0" onsubmit="return confirm('Round-robin all {{ $unassignedCount }} unassigned lead(s) across your active agents?');">
            @csrf
            <button type="submit" class="portal-xbtn" aria-label="Distribute {{ $unassignedCount }} unassigned leads"><i class="fas fa-shuffle"></i><span>Distribute {{ $unassignedCount }} unassigned</span></button>
        </form>
        @endif
        <button type="button" id="openCreateLeadModal" class="portal-xbtn portal-xbtn-primary" aria-label="Add lead"><i class="fas fa-plus"></i><span>Add Lead</span></button>
    </div>
</div>

@if(session('success'))
{{-- Server-side flash (e.g. "Lead deleted." after leaving a lead page) → top-right toast. --}}
<script>document.addEventListener('DOMContentLoaded', function () { window.portalToast('success', @json(session('success'))); });</script>
@endif

{{-- A background lead import (running, or opened from its "finished" notification) — LeadImportTracker fills and updates it. --}}
<div id="leadImportBanner" class="portal-card p-3 mb-3 {{ $leadImport ? '' : 'd-none' }}" data-lead-import-view="page"></div>
@if($leadImport)
@push('scripts')
<script>document.addEventListener('DOMContentLoaded', function () { window.LeadImportTracker.track(@json($leadImport->toProgress())); });</script>
@endpush
@endif

<div id="leadsListingWrapper">
    @include('portal.crm.leads._listing')
</div>

@include('portal.crm.leads._lead_form_modal')
@include('portal.crm.leads._lead_tags_modal')
@include('portal.crm.leads._lead_tag_overflow_popover')
@include('portal.crm.leads._lead_table_fields_modal')
@include('portal.crm.leads._lead_delete_modal')
@include('portal.crm.leads._lead_import_modal')
@include('portal.crm.leads._lead_export_modal')
@include('portal.crm.leads._stage_quick_add_modal')
@include('portal.crm.leads._lead_master_picker_modal')
@if($isAgencyViewer && $assignSetting)
@include('portal.agents._lead_assignment', ['assignSetting' => $assignSetting, 'agentCount' => $agencyAgents->count()])
@endif

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const defaultMasterData = JSON.parse(document.getElementById('leadFormDefaults').textContent);
        let currentMasterData = {
            stages: defaultMasterData.stages.slice(),
            sources: defaultMasterData.sources.slice(),
            tags: defaultMasterData.tags.slice(),
        };
        let selectedLeadIds = new Set();
        let excludedLeadIds = new Set();
        let selectAllMode = false;
        let leadsDataTable = null;
        let leadTableColumns = new Set(@json($leadTableColumns));
        let leadToReopenAfterEdit = null;

        const leadFormModalEl = document.getElementById('leadFormModal');
        const leadFormModal = new bootstrap.Modal(leadFormModalEl);
        const leadForm = document.getElementById('leadForm');
        const leadFormTitle = document.getElementById('leadFormModalTitle');
        const leadFormSubmitLabel = document.getElementById('leadFormSubmitLabel');
        const leadFormSpinner = document.getElementById('leadFormSpinner');
        const leadFormSubmitBtn = document.getElementById('leadFormSubmitBtn');
        const leadFormGeneralError = document.getElementById('leadFormGeneralError');

        const leadTagsModal = new bootstrap.Modal(document.getElementById('leadTagsModal'));
        const deleteModal = new bootstrap.Modal(document.getElementById('leadDeleteModal'));
        const leadTagsForm = document.getElementById('leadTagsForm');
        const leadTagsChoices = document.getElementById('leadTagsChoices');
        const leadTagsError = document.getElementById('leadTagsError');
        const leadTagsSaveBtn = document.getElementById('leadTagsSaveBtn');
        const leadTagsSpinner = document.getElementById('leadTagsSpinner');
        const tagOverflowPopover = document.getElementById('leadTagOverflowPopover');
        const tagOverflowList = document.getElementById('leadTagOverflowList');
        let currentOverflowLeadId = null;
        let currentOverflowAnchor = null;
        const leadTableFieldsModal = new bootstrap.Modal(document.getElementById('leadTableFieldsModal'));
        const leadTableFieldsForm = document.getElementById('leadTableFieldsForm');
        const leadTableFieldsError = document.getElementById('leadTableFieldsError');
        const leadTableFieldsSaveBtn = document.getElementById('leadTableFieldsSaveBtn');
        const leadTableFieldsSpinner = document.getElementById('leadTableFieldsSpinner');

        function syncTableFieldsForm() {
            leadTableFieldsForm.querySelectorAll('input[name="columns[]"]').forEach(function (input) {
                input.checked = leadTableColumns.has(input.value);
            });
        }

        function selectedTableFieldsFromForm() {
            const selected = new Set(['lead', 'phone']);
            leadTableFieldsForm.querySelectorAll('input[name="columns[]"]:checked').forEach(function (input) {
                selected.add(input.value);
            });

            return selected;
        }

        function updateLeadTableScrollHint() {
            const table = document.getElementById('leadsDataTable');
            const hint = document.getElementById('leadTableScrollHint');
            const responsiveWrapper = table?.closest('.table-responsive');
            if (!table || !hint || !responsiveWrapper) return;

            hint.classList.toggle('d-none', responsiveWrapper.scrollWidth <= responsiveWrapper.clientWidth + 1);
        }

        function applyLeadTableColumns() {
            const table = document.getElementById('leadsDataTable');
            if (!table) return;

            table.classList.toggle('portal-table-is-wide', leadTableColumns.size >= 8);
            requestAnimationFrame(updateLeadTableScrollHint);
        }

        function initialiseLeadsDataTable() {
            if (!window.jQuery || !$.fn.DataTable) return;

            const table = document.getElementById('leadsDataTable');
            if (!table) return;
            if (table.dataset.hasRows !== 'true') {
                applyLeadTableColumns();
                return;
            }

            const headers = Array.from(table.querySelectorAll('thead th'));
            const sortColumn = headers.findIndex(function (header) {
                return header.dataset.columnKey === table.dataset.sort;
            });

            // Server-side: the server renders only the page on screen (the first one arrives with
            // the listing, deferLoading); paging, sorting and Quick search fetch the next page's
            // rows from LeadController::index (dt=1). The listing's state (per_page / sort / dir / q)
            // is kept in the URL, so reloads, "select all" and exports see the same leads.
            let tableRequest = null;
            let lastQuickSearch = table.dataset.q || '';

            leadsDataTable = $(table).DataTable({
                autoWidth: false,
                serverSide: true,
                deferLoading: [Number(table.dataset.totalRows || 0), Number(table.dataset.listingTotal || 0)],
                searchDelay: 400,
                search: { search: table.dataset.q || '' },
                pageLength: Number(table.dataset.perPage) || 10,
                ajax: function (data, callback) {
                    const params = new URLSearchParams(window.location.search);
                    const sorted = data.order && data.order[0] ? headers[data.order[0].column] : null;
                    const quickSearch = String(data.search.value || '').trim();
                    params.set('per_page', data.length);
                    params.set('sort', sorted && sorted.dataset.columnKey ? sorted.dataset.columnKey : 'received');
                    params.set('dir', data.order && data.order[0] ? data.order[0].dir : 'desc');
                    quickSearch ? params.set('q', quickSearch) : params.delete('q');
                    params.delete('page');
                    history.replaceState(null, '', window.location.pathname + '?' + params.toString());

                    // A different Quick search means a different set of leads — start the selection over.
                    if (quickSearch !== lastQuickSearch) {
                        lastQuickSearch = quickSearch;
                        clearSelection();
                    }

                    params.set('page', Math.floor(data.start / data.length) + 1);
                    params.set('dt', '1');

                    if (tableRequest) tableRequest.abort();
                    const request = tableRequest = new AbortController();
                    const wrapper = document.getElementById('leadsListingWrapper');
                    wrapper.classList.add('leads-loading');

                    fetch(window.location.pathname + '?' + params.toString(), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        signal: request.signal,
                    })
                        .then(function (r) {
                            if (!r.ok) throw new Error('Could not load the leads.');
                            return r.json();
                        })
                        .then(function (json) {
                            table.dataset.totalRows = json.filtered;
                            // Rows come back as HTML (_lead_rows); each one keeps its <tr>/<td>
                            // attributes for createdRow below.
                            const tbody = document.createElement('tbody');
                            tbody.innerHTML = json.rows;
                            const rows = Array.from(tbody.rows).map(function (tr) {
                                const cells = Array.from(tr.cells).map(function (td) { return td.innerHTML; });
                                cells.sourceRow = tr;
                                return cells;
                            });
                            callback({ draw: data.draw, recordsTotal: json.total, recordsFiltered: json.filtered, data: rows });
                        })
                        .catch(function (error) {
                            if (error.name === 'AbortError') return;
                            showFlash('error', error.message);
                            callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                        })
                        .finally(function () {
                            if (tableRequest === request) {
                                tableRequest = null;
                                wrapper.classList.remove('leads-loading');
                            }
                        });
                },
                createdRow: function (row, data) {
                    const source = data.sourceRow;
                    if (!source) return;
                    Array.from(source.attributes).forEach(function (attr) { row.setAttribute(attr.name, attr.value); });
                    Array.from(source.cells).forEach(function (td, i) {
                        if (!row.cells[i]) return;
                        Array.from(td.attributes).forEach(function (attr) { row.cells[i].setAttribute(attr.name, attr.value); });
                    });
                },
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                // One top row: Show N leads · quick filter chips · Quick search (no empty gap between).
                dom: "<'portal-leads-dt-top'l<'portal-leads-dt-quick'>f>" +
                    "<'row'<'col-sm-12'tr>>" +
                    "<'row align-items-center mt-2'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                initComplete: function () {
                    const slot = this.api().table().container().querySelector('.portal-leads-dt-quick');
                    const chips = document.getElementById('leadQuickFilters');
                    if (slot && chips) slot.appendChild(chips);
                },
                order: sortColumn >= 0 ? [[sortColumn, table.dataset.dir === 'asc' ? 'asc' : 'desc']] : [],
                columnDefs: [
                    { orderable: false, targets: [0, 1] },
                    { searchable: false, targets: [1] },
                ],
                // "#" column: 1, 2, 3… in the order shown, continuing across pages.
                drawCallback: function () {
                    const api = this.api();
                    const start = api.page.info().start;
                    api.column(1, { page: 'current' }).nodes().each(function (cell, i) { cell.textContent = start + i + 1; });
                },
                language: {
                    search: 'Quick search:',
                    searchPlaceholder: 'Search displayed leads',
                    lengthMenu: 'Show _MENU_ leads',
                    info: 'Showing _START_ to _END_ of _TOTAL_ leads',
                    infoFiltered: '(filtered from _MAX_)',
                    infoEmpty: 'No leads to show',
                    zeroRecords: 'No matching leads found',
                },
            });

            // Page changes, sorting and quick search redraw rows — re-tick the selected ones.
            $(table).on('draw.dt', syncPageCheckboxes);

            applyLeadTableColumns();
        }

        // fallbackName covers a lead whose actual stage/source belongs to a
        // different owner than the Admin master-data list now shown (Super
        // Admin editing another owner's lead) — without this, saving without
        // touching the field would silently clear it since it's just missing
        // from the options rather than merely unselected.
        function namesMatch(left, right) {
            return String(left || '').trim().toLocaleLowerCase() === String(right || '').trim().toLocaleLowerCase();
        }

        function renderStageOptions(selectedId, fallbackName) {
            const select = document.getElementById('leadFormStage');
            select.innerHTML = '<option value="">No stage</option>';
            let found = false;
            currentMasterData.stages.forEach(function (stage) {
                const opt = document.createElement('option');
                opt.value = stage.id;
                opt.textContent = stage.name;
                if (selectedId && (String(stage.id) === String(selectedId) || namesMatch(stage.name, fallbackName))) {
                    opt.selected = true;
                    found = true;
                }
                select.appendChild(opt);
            });
            if (selectedId && !found && fallbackName) {
                const opt = document.createElement('option');
                opt.value = selectedId;
                opt.textContent = fallbackName + ' (current)';
                opt.selected = true;
                select.appendChild(opt);
            }
        }

        function renderSourceOptions(selectedId, fallbackName) {
            const select = document.getElementById('leadFormSource');
            select.innerHTML = '<option value="">No source</option>';
            let found = false;
            currentMasterData.sources.forEach(function (source) {
                const opt = document.createElement('option');
                opt.value = source.id;
                opt.textContent = source.name;
                if (selectedId && (String(source.id) === String(selectedId) || namesMatch(source.name, fallbackName))) {
                    opt.selected = true;
                    found = true;
                }
                select.appendChild(opt);
            });
            if (selectedId && !found && fallbackName) {
                const opt = document.createElement('option');
                opt.value = selectedId;
                opt.textContent = fallbackName + ' (current)';
                opt.selected = true;
                select.appendChild(opt);
            }
        }

        function renderTagCheckboxes(selectedIds, currentTags) {
            const container = document.getElementById('leadFormTags');
            container.innerHTML = '';
            const selected = (selectedIds || []).map(String);
            const selectedNames = new Set((currentTags || [])
                .filter(function (tag) { return selected.includes(String(tag.id)); })
                .map(function (tag) { return String(tag.name).trim().toLocaleLowerCase(); }));

            const tags = currentMasterData.tags.slice();
            const knownNames = tags.map(function (tag) { return String(tag.name).trim().toLocaleLowerCase(); });
            (currentTags || []).forEach(function (tag) {
                if (!knownNames.includes(String(tag.name).trim().toLocaleLowerCase())) tags.push(tag);
            });

            if (!tags.length) {
                container.innerHTML = '<span class="text-muted small">No tags yet — add some under Master &gt; Tag.</span>';
                return;
            }

            tags.forEach(function (tag) {
                const label = document.createElement('label');
                label.className = 'portal-tag-chip-check';
                label.style.borderColor = tag.color;

                const input = document.createElement('input');
                input.type = 'checkbox';
                input.name = 'tags[]';
                input.value = tag.id;
                if (selected.includes(String(tag.id)) || selectedNames.has(String(tag.name).trim().toLocaleLowerCase())) input.checked = true;

                label.appendChild(input);
                label.appendChild(document.createTextNode(' ' + tag.name));
                container.appendChild(label);
            });
        }

        // Lead tags popup: the account's tags are searched and paged on the server (masterOptions,
        // 20 a time, more on scroll). The ticked tags live in tagPicker.selected (id → tag), so
        // saving keeps every ticked tag even when it isn't in the list currently loaded.
        const tagPicker = { selected: new Map(), search: '', page: 1, next: null, loading: false, request: null, timer: null };
        const leadTagsSearch = document.getElementById('leadTagsSearch');
        const leadTagsStatus = document.getElementById('leadTagsStatus');
        const leadTagsSelected = document.getElementById('leadTagsSelected');
        const leadTagsCount = document.getElementById('leadTagsCount');

        function escapeTagHtml(value) {
            return String(value).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
        }

        function renderSelectedTags() {
            leadTagsSelected.innerHTML = '';
            tagPicker.selected.forEach(function (tag) {
                const chip = document.createElement('span');
                chip.className = 'lead-tags-selected__chip';
                chip.style.background = tag.color || '#14b8a6';
                chip.innerHTML = escapeTagHtml(tag.name) + ' <button type="button" aria-label="Remove ' + escapeTagHtml(tag.name) + '">&times;</button>';
                chip.querySelector('button').addEventListener('click', function () {
                    tagPicker.selected.delete(String(tag.id));
                    const box = leadTagsChoices.querySelector('input[value="' + tag.id + '"]');
                    if (box) box.checked = false;
                    renderSelectedTags();
                });
                leadTagsSelected.appendChild(chip);
            });
            const n = tagPicker.selected.size;
            leadTagsCount.textContent = n ? n + ' tag' + (n === 1 ? '' : 's') + ' selected' : 'No tags selected';
        }

        function appendTagChoice(tag) {
            const label = document.createElement('label');
            label.className = 'portal-tag-chip-check';
            label.style.borderColor = tag.color || '#14b8a6';
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.value = tag.id;
            input.checked = tagPicker.selected.has(String(tag.id));
            input.addEventListener('change', function () {
                if (input.checked) tagPicker.selected.set(String(tag.id), tag);
                else tagPicker.selected.delete(String(tag.id));
                renderSelectedTags();
            });
            label.appendChild(input);
            label.appendChild(document.createTextNode(' ' + tag.name));
            leadTagsChoices.appendChild(label);
        }

        function loadTagChoices(reset) {
            if (reset) {
                tagPicker.page = 1;
                tagPicker.next = null;
                leadTagsChoices.innerHTML = '';
            } else if (!tagPicker.next || tagPicker.loading) {
                return;
            }
            if (tagPicker.request) tagPicker.request.abort(); // only the latest search counts
            tagPicker.request = new AbortController();
            tagPicker.loading = true;
            leadTagsStatus.textContent = 'Loading tags…';

            const page = reset ? 1 : tagPicker.next;
            const url = "{{ url('portal/crm/leads/options/tags') }}?" + new URLSearchParams({ search: tagPicker.search, page: page });
            fetch(url, { headers: { 'Accept': 'application/json' }, signal: tagPicker.request.signal })
                .then(function (r) {
                    if (!r.ok) throw new Error('Could not load tags.');
                    return r.json();
                })
                .then(function (data) {
                    (data.data || []).forEach(appendTagChoice);
                    tagPicker.next = data.next_page;
                    tagPicker.loading = false;
                    if (!leadTagsChoices.children.length) {
                        leadTagsStatus.textContent = '';
                        leadTagsChoices.innerHTML = '<span class="text-muted small">' + (tagPicker.search ? 'No tags match “' + escapeTagHtml(tagPicker.search) + '”.' : 'No tags available yet. Add them under Master &gt; Tag.') + '</span>';
                    } else {
                        leadTagsStatus.textContent = data.total > leadTagsChoices.children.length
                            ? 'Showing ' + leadTagsChoices.children.length + ' of ' + data.total + ' — scroll for more'
                            : data.total + ' tag' + (data.total === 1 ? '' : 's');
                    }
                })
                .catch(function (error) {
                    if (error.name === 'AbortError') return;
                    tagPicker.loading = false;
                    leadTagsStatus.textContent = error.message;
                });
        }

        leadTagsSearch.addEventListener('input', function () {
            clearTimeout(tagPicker.timer);
            tagPicker.timer = setTimeout(function () {
                tagPicker.search = leadTagsSearch.value.trim();
                loadTagChoices(true);
            }, 300);
        });
        leadTagsChoices.addEventListener('scroll', function () {
            if (leadTagsChoices.scrollTop + leadTagsChoices.clientHeight >= leadTagsChoices.scrollHeight - 40) loadTagChoices(false);
        });

        function openTagPicker(currentTags) {
            tagPicker.selected = new Map((currentTags || []).map(function (tag) { return [String(tag.id), tag]; }));
            tagPicker.search = '';
            leadTagsSearch.value = '';
            renderSelectedTags();
            loadTagChoices(true);
        }

        function clearValidationErrors() {
            leadForm.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
            leadForm.querySelectorAll('[data-error-for]').forEach(function (el) { el.textContent = ''; });
            leadFormGeneralError.classList.add('d-none');
            leadFormGeneralError.textContent = '';
        }

        function showValidationErrors(errors) {
            Object.keys(errors).forEach(function (field) {
                const baseField = field.replace(/\.\d+$/, '');
                const input = leadForm.querySelector('[name="' + baseField + '"], [name="' + baseField + '[]"]');
                const errorEl = leadForm.querySelector('[data-error-for="' + baseField + '"]');
                const message = errors[field][0];

                if (input) input.classList.add('is-invalid');
                if (errorEl) {
                    errorEl.textContent = message;
                } else {
                    leadFormGeneralError.textContent = message;
                    leadFormGeneralError.classList.remove('d-none');
                }
            });
        }

        function setLeadFormHeading(icon, subtitle) {
            document.getElementById('leadFormModalIcon').className = 'fas ' + icon;
            document.getElementById('leadFormModalSubtitle').textContent = subtitle;
        }

        function setSubmitting(isSubmitting) {
            leadFormSubmitBtn.disabled = isSubmitting;
            leadFormSpinner.classList.toggle('d-none', !isSubmitting);
        }

        // Owner/Status/Stage/Tags only matter once a lead exists to manage —
        // Create hides them (their fields still carry sensible defaults, so
        // they're submitted anyway) and only Edit exposes them for changing.
        function setManagementFieldsVisible(editing) {
            const ownerColumn = document.getElementById('leadFormOwnerCol');
            const sourceColumn = document.getElementById('leadFormSourceCol');
            const statusColumn = document.getElementById('leadFormStatusCol');

            statusColumn?.classList.toggle('d-none', !editing);
            // Source is picked when creating a lead; afterwards it's fixed (disabled = not submitted).
            const sourceSelect = document.getElementById('leadFormSource');
            if (sourceSelect) sourceSelect.disabled = editing;
            sourceColumn?.classList.toggle('col-sm-6', editing || !!ownerColumn);
            sourceColumn?.classList.toggle('col-sm-12', !editing && !ownerColumn);
            ['leadFormStageCol', 'leadFormTagsGroup'].forEach(function (id) {
                document.getElementById(id)?.classList.toggle('d-none', !editing);
            });
        }

        function openCreateModal() {
            currentMasterData = {
                stages: defaultMasterData.stages.slice(),
                sources: defaultMasterData.sources.slice(),
                tags: defaultMasterData.tags.slice(),
            };

            clearValidationErrors();
            leadForm.reset();
            leadFormTitle.textContent = 'Add Lead';
            setLeadFormHeading('fa-user-plus', 'Add an enquiry you received by phone, email or in person.');
            leadFormSubmitLabel.textContent = 'Create Lead';
            setManagementFieldsVisible(false);
            renderStageOptions(defaultMasterData.defaultStageId);
            renderSourceOptions(defaultMasterData.defaultSourceId);
            renderTagCheckboxes([]);

            const ownerSelect = document.getElementById('leadFormOwner');
            if (ownerSelect) ownerSelect.value = defaultMasterData.currentOwnerId || '';
            document.getElementById('leadFormStatus').value = 'active';
            window.portalPhone.setCode(document.getElementById('leadFormPhone'), defaultMasterData.defaultPhoneCountryCode);

            leadForm.dataset.mode = 'create';
            leadForm.dataset.leadId = '';
            leadForm.dataset.ownerId = defaultMasterData.currentOwnerId || '';
            leadFormModal.show();
        }

        function fetchLead(id) {
            return fetch("{{ url('portal/crm/leads') }}/" + id, { headers: { 'Accept': 'application/json' } })
                .then(function (r) {
                    if (!r.ok) throw new Error('not found');
                    return r.json();
                });
        }

        function openEditModal(id, returnToView) {
            if (!returnToView) leadToReopenAfterEdit = null;

            fetchLead(id).then(function (data) {
                currentMasterData = {
                    stages: data.stages,
                    sources: data.sources,
                    tags: data.tags_master,
                };

                clearValidationErrors();
                leadForm.reset();
                leadFormTitle.textContent = 'Edit Lead';
                setLeadFormHeading('fa-user-pen', 'Update the contact details, owner, stage and tags.');
                leadFormSubmitLabel.textContent = 'Save Changes';
                setManagementFieldsVisible(true);

                document.getElementById('leadFormName').value = data.name || '';
                document.getElementById('leadFormEmail').value = data.email || '';
                document.getElementById('leadFormPhone').value = data.phone || '';
                window.portalPhone.setCode(document.getElementById('leadFormPhone'), data.phone_country_code || defaultMasterData.defaultPhoneCountryCode);
                document.getElementById('leadFormMessage').value = data.message || '';
                document.getElementById('leadFormStatus').value = data.status || 'active';

                const ownerSelect = document.getElementById('leadFormOwner');
                if (ownerSelect) ownerSelect.value = data.owner_id || '';

                renderStageOptions(data.stage_id, data.stage_name);
                renderSourceOptions(data.source_id, data.source_name);
                renderTagCheckboxes(data.tag_ids, data.tags);

                leadForm.dataset.mode = 'edit';
                leadForm.dataset.leadId = id;
                leadForm.dataset.ownerId = data.master_data_owner_id || '';
                leadFormModal.show();
            }).catch(function () {
                alert('Could not load this lead. Please try again.');
            });
        }

        function openTagsModal(id) {
            fetchLead(id).then(function (data) {
                leadTagsForm.dataset.leadId = id;
                document.getElementById('leadTagsModalTitle').textContent = 'Tags for ' + (data.name || 'lead');
                leadTagsError.classList.add('d-none');
                leadTagsError.textContent = '';
                openTagPicker(data.tags);
                leadTagsModal.show();
            }).catch(function () {
                alert('Could not load tags for this lead. Please try again.');
            });
        }

        function closeTagOverflowPopover() {
            tagOverflowPopover.classList.add('d-none');
            currentOverflowLeadId = null;
            if (currentOverflowAnchor) currentOverflowAnchor.setAttribute('aria-expanded', 'false');
            currentOverflowAnchor = null;
        }

        function openTagOverflowPopover(anchor, id) {
            fetchLead(id).then(function (data) {
                currentOverflowLeadId = id;
                currentOverflowAnchor = anchor;
                const otherTags = (data.tags || []).slice(1);
                tagOverflowList.innerHTML = '';

                if (!otherTags.length) {
                    tagOverflowList.innerHTML = '<span class="text-muted small">No more tags.</span>';
                } else {
                    otherTags.forEach(function (tag) {
                        const chip = document.createElement('span');
                        chip.className = 'portal-tag-popover-chip';
                        chip.style.background = (tag.color || '#14b8a6') + '22';
                        chip.style.color = tag.color || '#14b8a6';
                        chip.textContent = tag.name;

                        const removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'portal-tag-popover-remove';
                        removeBtn.dataset.tagId = tag.id;
                        removeBtn.setAttribute('aria-label', 'Remove ' + tag.name);
                        removeBtn.innerHTML = '<i class="fas fa-xmark" aria-hidden="true"></i>';
                        chip.appendChild(removeBtn);
                        tagOverflowList.appendChild(chip);
                    });
                }

                const rect = anchor.getBoundingClientRect();
                tagOverflowPopover.style.top = (rect.bottom + window.scrollY + 6) + 'px';
                tagOverflowPopover.style.left = (rect.left + window.scrollX) + 'px';
                tagOverflowPopover.classList.remove('d-none');
                anchor.setAttribute('aria-expanded', 'true');
            }).catch(function () {
                alert('Could not load tags for this lead. Please try again.');
            });
        }

        function removeLeadTag(leadId, tagId) {
            fetchLead(leadId).then(function (data) {
                const remainingIds = (data.tag_ids || []).map(String).filter(function (id) { return id !== String(tagId); });
                const body = new URLSearchParams();
                remainingIds.forEach(function (id) { body.append('tags[]', id); });

                return fetch("{{ url('portal/crm/leads') }}/" + leadId + '/tags', {
                    method: 'PATCH',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: body,
                });
            })
            .then(async function (response) {
                const data = await response.json().catch(function () { return {}; });
                if (!response.ok) throw new Error(data.message || 'Could not remove tag.');

                showFlash('success', 'Tag removed.');
                closeTagOverflowPopover();
                reloadLeadsListing();
            })
            .catch(function (error) {
                alert(error.message || 'Could not remove tag. Please try again.');
            });
        }

        tagOverflowList.addEventListener('click', function (e) {
            const removeBtn = e.target.closest('.portal-tag-popover-remove');
            if (!removeBtn || !currentOverflowLeadId) return;
            removeLeadTag(currentOverflowLeadId, removeBtn.dataset.tagId);
        });

        document.addEventListener('click', function (e) {
            if (tagOverflowPopover.classList.contains('d-none')) return;
            if (e.target.closest('.portal-tag-popover') || e.target.closest('.portal-tag-overflow')) return;
            closeTagOverflowPopover();
        });

        // A lead opens on its own detail page (portal.crm.leads.show) — details, activity,
        // notes, source history and editing all live there now.
        function openViewModal(id) {
            window.location.href = "{{ url('portal/crm/leads') }}/" + id;
        }


        // Top-right toast (portal layout) — same as the lead page.
        function showFlash(type, message) {
            window.portalToast(type, message);
        }

        window.reloadLeadsListing = function () {
            if (leadsDataTable) {
                leadsDataTable.destroy();
                leadsDataTable = null;
            }

            return fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) {
                    if (!r.ok) throw new Error('Could not refresh the leads list.');
                    return r.text();
                })
                .then(function (html) {
                    document.getElementById('leadsListingWrapper').innerHTML = html;
                    clearSelection();
                    initialiseLeadsDataTable();
                });
        };

        // Live filters: typing in Search (debounced) or changing any filter reloads the listing via
        // AJAX. Each new request cancels the one still running, so only the latest result is shown.
        let leadFilterRequest = null;
        let leadSearchTimer = null;

        function runLeadFilters(url) {
            const wrapper = document.getElementById('leadsListingWrapper');
            const form = wrapper.querySelector('.portal-filter-bar__form');
            if (!url) {
                const params = new URLSearchParams();
                new FormData(form).forEach(function (value, key) {
                    if (String(value).trim() !== '') params.append(key, String(value).trim());
                });
                // Keep the table's page size and sort order across filter changes.
                const current = new URLSearchParams(window.location.search);
                ['per_page', 'sort', 'dir'].forEach(function (key) {
                    if (current.get(key)) params.set(key, current.get(key));
                });
                url = form.getAttribute('action') || window.location.pathname;
                url = url.split('?')[0] + (params.toString() ? '?' + params.toString() : '');
            }

            if (leadFilterRequest) leadFilterRequest.abort();
            leadFilterRequest = new AbortController();

            // Remember where the cursor was, so typing carries on after the list is swapped in.
            const active = document.activeElement;
            const focusName = active && wrapper.contains(active) ? active.name : null;
            const caret = active && typeof active.selectionStart === 'number' ? active.selectionStart : null;
            wrapper.classList.add('leads-loading');

            return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: leadFilterRequest.signal })
                .then(function (r) {
                    if (!r.ok) throw new Error('Could not filter the leads.');
                    return r.text();
                })
                .then(function (html) {
                    if (leadsDataTable) {
                        leadsDataTable.destroy();
                        leadsDataTable = null;
                    }
                    wrapper.innerHTML = html;
                    history.replaceState(null, '', url);
                    clearSelection();
                    initialiseLeadsDataTable();
                    if (focusName) {
                        const field = wrapper.querySelector('.portal-filter-bar__form [name="' + focusName + '"]');
                        if (field) {
                            field.focus();
                            if (caret !== null && typeof field.setSelectionRange === 'function') field.setSelectionRange(caret, caret);
                        }
                    }
                })
                .catch(function (error) {
                    if (error.name !== 'AbortError') showFlash('error', error.message);
                })
                .finally(function () {
                    wrapper.classList.remove('leads-loading');
                });
        }

        const leadsWrapper = document.getElementById('leadsListingWrapper');
        leadsWrapper.addEventListener('input', function (e) {
            if (!e.target.matches('.portal-filter-bar__form [name="search"]')) return;
            clearTimeout(leadSearchTimer);
            leadSearchTimer = setTimeout(function () { runLeadFilters(); }, 350);
        });
        leadsWrapper.addEventListener('change', function (e) {
            if (!e.target.closest('.portal-filter-bar__form') || e.target.name === 'search') return;
            clearTimeout(leadSearchTimer);
            runLeadFilters();
        });
        leadsWrapper.addEventListener('submit', function (e) {
            if (!e.target.matches('.portal-filter-bar__form')) return;
            e.preventDefault();
            clearTimeout(leadSearchTimer);
            runLeadFilters();
        });
        // Stat cards: filter to that card (or back to everything when it's already selected / Total).
        leadsWrapper.addEventListener('click', function (e) {
            const card = e.target.closest('[data-lead-quick]');
            if (!card) return;
            const input = leadsWrapper.querySelector('.portal-filter-bar__form [name="quick"]');
            input.value = card.getAttribute('aria-pressed') === 'true' ? '' : card.dataset.leadQuick;
            clearTimeout(leadSearchTimer);
            runLeadFilters();
        });
        leadsWrapper.addEventListener('click', function (e) {
            const clear = e.target.closest('.js-clear-lead-filters');
            if (!clear) return;
            e.preventDefault();
            clearTimeout(leadSearchTimer);
            runLeadFilters(clear.href);
        });
        window.runLeadFilters = runLeadFilters;

        document.getElementById('openCreateLeadModal').addEventListener('click', openCreateModal);
        document.getElementById('openLeadTableFieldsModal').addEventListener('click', function () {
            leadTableFieldsError.classList.add('d-none');
            leadTableFieldsError.textContent = '';
            syncTableFieldsForm();
            leadTableFieldsModal.show();
        });

        leadTableFieldsForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const selectedColumns = selectedTableFieldsFromForm();
            const body = new URLSearchParams();
            selectedColumns.forEach(function (column) {
                body.append('columns[]', column);
            });

            leadTableFieldsError.classList.add('d-none');
            leadTableFieldsSaveBtn.disabled = true;
            leadTableFieldsSpinner.classList.remove('d-none');

            fetch("{{ route('portal.crm.leads.table-columns.update') }}", {
                method: 'PUT',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: body,
            })
            .then(async function (response) {
                const data = await response.json().catch(function () { return {}; });
                if (!response.ok) throw new Error(data.message || 'Could not save table fields.');

                return data;
            })
            .then(function (data) {
                leadTableColumns = new Set(data.columns || []);
                leadTableFieldsModal.hide();
                return reloadLeadsListing().then(function () {
                    showFlash('success', data.message || 'Table fields saved.');
                });
            })
            .catch(function (error) {
                leadTableFieldsError.textContent = error.message || 'Could not save table fields. Please try again.';
                leadTableFieldsError.classList.remove('d-none');
            })
            .finally(function () {
                leadTableFieldsSaveBtn.disabled = false;
                leadTableFieldsSpinner.classList.add('d-none');
            });
        });

        document.addEventListener('click', function (e) {
            const editBtn = e.target.closest('.edit-lead-btn');
            if (editBtn) { openEditModal(editBtn.dataset.id, false); return; }

            const viewBtn = e.target.closest('.view-lead-btn');
            if (viewBtn) { openViewModal(viewBtn.dataset.id); return; }

            const tagsPicker = e.target.closest('.portal-tags-picker');
            if (tagsPicker) { openTagsModal(tagsPicker.dataset.id); return; }

            const tagOverflowBtn = e.target.closest('.portal-tag-overflow');
            if (tagOverflowBtn) {
                const isOpenForSameLead = currentOverflowLeadId === tagOverflowBtn.dataset.id && !tagOverflowPopover.classList.contains('d-none');
                closeTagOverflowPopover();
                if (!isOpenForSameLead) openTagOverflowPopover(tagOverflowBtn, tagOverflowBtn.dataset.id);
                return;
            }

            const stagePicker = e.target.closest('.portal-stage-picker');
            if (stagePicker) {
                const container = stagePicker.closest('.portal-inline-stage');
                const select = container.querySelector('.portal-stage-select');
                stagePicker.classList.add('d-none');
                select.classList.remove('d-none');
                select.focus();
                return;
            }

            // Row clicks intentionally exclude every native or CRM-specific
            // control so selecting leads, changing stages, and managing tags
            // cannot accidentally open the detail modal.
            if (e.target.closest('button, a, input, select, textarea, label, [data-bs-toggle], [data-lead-selection], [data-column-key="stage"], [data-column-key="tags"]')) return;

            const leadRow = e.target.closest('tr.portal-lead-row');
            if (leadRow) openViewModal(leadRow.dataset.leadId);
        });

        document.addEventListener('keydown', function (e) {
            const leadRow = e.target.closest('tr.portal-lead-row');
            if (!leadRow || e.target !== leadRow || !['Enter', ' '].includes(e.key)) return;

            e.preventDefault();
            openViewModal(leadRow.dataset.leadId);
        });

        document.addEventListener('change', function (e) {
            const select = e.target.closest('.portal-stage-select');
            if (!select) return;

            const container = select.closest('.portal-inline-stage');
            const spinner = container.querySelector('.portal-stage-spinner');
            select.disabled = true;
            spinner.classList.remove('d-none');

            fetch("{{ url('portal/crm/leads') }}/" + select.dataset.leadId + '/stage', {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: new URLSearchParams({ stage_id: select.value }),
            })
            .then(async function (response) {
                const data = await response.json().catch(function () { return {}; });
                if (!response.ok) throw new Error(data.message || 'Could not update the stage.');

                showFlash('success', data.message || 'Stage updated.');
                reloadLeadsListing();
            })
            .catch(function (error) {
                alert(error.message || 'Could not update the stage. Please try again.');
                select.disabled = false;
                spinner.classList.add('d-none');
            });
        });

        document.addEventListener('focusout', function (e) {
            const select = e.target.closest('.portal-stage-select');
            if (!select) return;

            setTimeout(function () {
                if (!select.disabled) {
                    select.classList.add('d-none');
                    select.closest('.portal-inline-stage').querySelector('.portal-stage-picker').classList.remove('d-none');
                }
            }, 150);
        });

        /*
         * Selection. Two modes:
         *  - ticked: selectedLeadIds holds exactly the ticked leads (across pages);
         *  - all:    "Select all N leads" — every lead matching the filters, on every page, minus
         *            excludedLeadIds (rows unticked afterwards). The server resolves it from
         *            select_all=1 + the filters (LeadService::selectedQuery), never an id list.
         * Delete / Export / Assign Stage / Assign Tag all read it via window.getLeadSelection().
         */
        function totalMatchingLeads() {
            return Number(document.getElementById('leadsDataTable')?.dataset.totalRows || 0);
        }

        function selectionCount() {
            return selectAllMode ? Math.max(0, totalMatchingLeads() - excludedLeadIds.size) : selectedLeadIds.size;
        }

        function isRowSelected(id) {
            return selectAllMode ? !excludedLeadIds.has(String(id)) : selectedLeadIds.has(String(id));
        }

        window.getLeadSelection = function () {
            return { all: selectAllMode, ids: Array.from(selectedLeadIds), exclude: Array.from(excludedLeadIds), count: selectionCount() };
        };

        // Adds the selection to a request body: the ids, or select_all + the listing's filters.
        window.appendLeadSelection = function (body) {
            if (selectAllMode) {
                body.append('select_all', '1');
                new URLSearchParams(window.location.search).forEach(function (value, key) {
                    if (value !== '' && key !== 'lead') body.append(key, value);
                });
                excludedLeadIds.forEach(function (id) { body.append('exclude_ids[]', id); });
            } else {
                selectedLeadIds.forEach(function (id) { body.append('ids[]', id); });
            }
            return body;
        };

        function clearSelection() {
            selectAllMode = false;
            selectedLeadIds.clear();
            excludedLeadIds.clear();
            document.querySelectorAll('.lead-select-checkbox').forEach(function (box) { box.checked = false; });
            updateBulkActionsBar();
        }

        function updateBulkActionsBar() {
            const count = selectionCount();
            const total = totalMatchingLeads();

            // Delete / Export / Assign Stage / Assign Tag only make sense with leads selected.
            document.getElementById('leadSelectedBadge').classList.toggle('d-none', count === 0);
            document.getElementById('leadSelectedBadgeCount').textContent = count;
            document.querySelectorAll('[data-needs-selection]').forEach(function (btn) {
                btn.disabled = count === 0;
                btn.title = count === 0 ? 'Select leads first' : btn.getAttribute('aria-label') + ' — ' + count + (count === 1 ? ' lead' : ' leads');
            });

            // Header checkbox = every lead (all pages): ticked when all are selected, a dash when
            // only some are (e.g. select-all, then a few unticked).
            const selectAll = document.getElementById('selectAllLeads');
            if (selectAll) {
                selectAll.checked = total > 0 && count === total;
                selectAll.indeterminate = count > 0 && count < total;
            }
        }

        // Re-tick rows as DataTables draws each page (so page changes keep the selection).
        function syncPageCheckboxes() {
            document.querySelectorAll('.lead-select-checkbox').forEach(function (box) { box.checked = isRowSelected(box.value); });
            updateBulkActionsBar();
        }

        document.addEventListener('change', function (e) {
            if (e.target.matches('.lead-select-checkbox')) {
                const id = String(e.target.value);
                if (selectAllMode) {
                    e.target.checked ? excludedLeadIds.delete(id) : excludedLeadIds.add(id);
                } else {
                    e.target.checked ? selectedLeadIds.add(id) : selectedLeadIds.delete(id);
                }
                updateBulkActionsBar();
                return;
            }

            // Header checkbox selects every matching lead on every page (the server resolves them
            // from the filters); unticking it clears the selection.
            if (e.target.id === 'selectAllLeads') {
                if (!e.target.checked) { clearSelection(); return; }
                selectAllMode = true;
                selectedLeadIds.clear();
                excludedLeadIds.clear();
                syncPageCheckboxes();
            }
        });

        document.addEventListener('click', function (e) {
            if (e.target.closest('#bulkDeleteLeadsBtn')) {
                const count = selectionCount();
                if (!count) return;
                document.getElementById('leadDeleteCount').textContent = count === 1 ? '1 lead' : count + ' leads';
                document.getElementById('leadDeleteCountPlural').textContent = count === 1 ? 'it' : 'them';
                deleteModal.show();
            }
        });

        leadForm.addEventListener('submit', function (e) {
            e.preventDefault();
            clearValidationErrors();
            setSubmitting(true);

            const mode = leadForm.dataset.mode;
            const leadId = leadForm.dataset.leadId;
            const url = mode === 'edit'
                ? "{{ url('portal/crm/leads') }}/" + leadId
                : "{{ route('portal.crm.leads.store') }}";

            // A real PUT/POST verb via fetch (no HTML <form> involved, so no
            // need for Laravel's _method spoofing) — body is url-encoded since
            // this form has no file inputs and PHP only auto-parses multipart
            // bodies for POST, not PUT.
            const body = new URLSearchParams(new FormData(leadForm));

            fetch(url, {
                method: mode === 'edit' ? 'PUT' : 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: body,
            })
            .then(async function (response) {
                const data = await response.json().catch(function () { return {}; });

                if (response.status === 422) {
                    showValidationErrors(data.errors || {});
                    return;
                }
                if (!response.ok) {
                    leadFormGeneralError.textContent = data.message || 'Something went wrong. Please try again.';
                    leadFormGeneralError.classList.remove('d-none');
                    return;
                }

                showFlash('success', data.message || 'Saved.');
                const returnToViewId = mode === 'edit' ? leadToReopenAfterEdit : null;

                if (returnToViewId) {
                    const reopenLeadView = function () {
                        leadFormModalEl.removeEventListener('hidden.bs.modal', reopenLeadView);
                        leadToReopenAfterEdit = null;
                        reloadLeadsListing().then(function () {
                            openViewModal(returnToViewId);
                        });
                    };

                    leadFormModalEl.addEventListener('hidden.bs.modal', reopenLeadView);
                    leadFormModal.hide();
                } else {
                    leadFormModal.hide();
                    reloadLeadsListing();
                }
            })
            .catch(function () {
                leadFormGeneralError.textContent = 'Something went wrong. Please try again.';
                leadFormGeneralError.classList.remove('d-none');
            })
            .finally(function () {
                setSubmitting(false);
            });
        });

        leadTagsForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const leadId = leadTagsForm.dataset.leadId;
            if (!leadId) return;

            leadTagsError.classList.add('d-none');
            leadTagsSaveBtn.disabled = true;
            leadTagsSpinner.classList.remove('d-none');

            fetch("{{ url('portal/crm/leads') }}/" + leadId + '/tags', {
                method: 'PATCH',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                // Every ticked tag — loaded in the list or not (the endpoint replaces the lead's tags).
                body: new URLSearchParams(Array.from(tagPicker.selected.keys()).map(function (id) { return ['tags[]', id]; })),
            })
            .then(async function (response) {
                const data = await response.json().catch(function () { return {}; });
                if (!response.ok) throw new Error(data.message || 'Could not update tags.');

                leadTagsModal.hide();
                showFlash('success', data.message || 'Tags updated.');
                reloadLeadsListing();
            })
            .catch(function (error) {
                leadTagsError.textContent = error.message || 'Could not update tags. Please try again.';
                leadTagsError.classList.remove('d-none');
            })
            .finally(function () {
                leadTagsSaveBtn.disabled = false;
                leadTagsSpinner.classList.add('d-none');
            });
        });

        document.getElementById('leadDeleteConfirmBtn').addEventListener('click', function () {
            if (!selectionCount()) return;
            const btn = this;
            const spinner = document.getElementById('leadDeleteSpinner');
            btn.disabled = true;
            spinner.classList.remove('d-none');

            // POST + _method spoofing: a raw DELETE body is dropped by some servers/proxies,
            // which left the selection empty ("The ids field is required").
            const body = new URLSearchParams();
            body.append('_method', 'DELETE');
            window.appendLeadSelection(body);

            fetch("{{ route('portal.crm.leads.bulk-delete') }}", {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: body,
            })
            .then(async function (response) {
                const data = await response.json().catch(function () { return {}; });
                if (!response.ok) {
                    alert(data.message || 'Could not delete the selected leads.');
                    return;
                }
                deleteModal.hide();
                showFlash('success', data.message || 'Leads deleted.');
                reloadLeadsListing();
            })
            .finally(function () {
                btn.disabled = false;
                spinner.classList.add('d-none');
                pendingDeleteId = null;
            });
        });

        document.addEventListener('stage:created', function (e) {
            defaultMasterData.stages.push(e.detail);
            if (!currentMasterData.stages.some(function (s) { return String(s.id) === String(e.detail.id); })) {
                currentMasterData.stages.push(e.detail);
            }
        });

        // A tag added from the Tags popup is offered straight away in the Add / Edit lead form too.
        document.addEventListener('tag:created', function (e) {
            defaultMasterData.tags.push(e.detail);
            if (!currentMasterData.tags.some(function (t) { return String(t.id) === String(e.detail.id); })) {
                currentMasterData.tags.push(e.detail);
            }
        });

        const params = new URLSearchParams(window.location.search);
        if (params.get('lead')) {
            openViewModal(params.get('lead'));
        }

        initialiseLeadsDataTable();
    });
</script>
@endpush
@endsection
