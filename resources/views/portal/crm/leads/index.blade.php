@extends('portal.crm._layout')

@section('title', 'Leads')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
@endpush

@section('crm-content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
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
        <button type="button" id="openLeadExportModal" data-needs-selection class="portal-xbtn" aria-label="Export" title="Select leads first" disabled><i class="fas fa-file-export"></i><span>Export</span></button>
        <button type="button" id="bulkDeleteLeadsBtn" data-needs-selection class="portal-xbtn portal-xbtn-danger" aria-label="Delete leads" title="Select leads first" disabled><i class="fas fa-trash-can"></i><span>Delete Leads</span></button>
        <span class="portal-xbtn-sep" aria-hidden="true"></span>
        <button type="button" id="openLeadTableFieldsModal" class="portal-xbtn" aria-label="Table fields"><i class="fas fa-table-columns"></i><span>Table Fields</span></button>
        <button type="button" id="openLeadImportModal" class="portal-xbtn" aria-label="Import"><i class="fas fa-file-import"></i><span>Import</span></button>
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

@if(session('importResult'))
@php($result = session('importResult'))
<div class="portal-card p-3 mb-3">
    <div class="fw-semibold mb-1">Import finished — {{ $result['imported'] }} imported, {{ $result['updated'] ?? 0 }} existing leads updated, {{ $result['skipped'] }} skipped.</div>
    @if(!empty($result['rowErrors']))
    <ul class="mb-0 small text-muted" style="max-height: 180px; overflow-y: auto;">
        @foreach($result['rowErrors'] as $rowError)
        <li>Row {{ $rowError['row'] }}: {{ implode(' ', $rowError['errors']) }}</li>
        @endforeach
    </ul>
    @endif
</div>
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
        const tagOverflowAddBtn = document.getElementById('leadTagOverflowAddBtn');
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
            const receivedColumn = headers.findIndex(function (header) {
                return header.textContent.trim().toLowerCase() === 'received';
            });

            leadsDataTable = $(table).DataTable({
                autoWidth: false,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                order: receivedColumn >= 0 ? [[receivedColumn, 'desc']] : [],
                columnDefs: [{
                    orderable: false,
                    targets: [0],
                }],
                language: {
                    search: 'Quick search:',
                    searchPlaceholder: 'Search displayed leads',
                    lengthMenu: 'Show _MENU_ leads',
                    info: 'Showing _START_ to _END_ of _TOTAL_ leads',
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

        function renderListingTagChoices(masterTags, currentTags, selectedIds) {
            leadTagsChoices.innerHTML = '';
            const selected = (selectedIds || []).map(String);
            const selectedNames = new Set((currentTags || [])
                .filter(function (tag) { return selected.includes(String(tag.id)); })
                .map(function (tag) { return String(tag.name).trim().toLocaleLowerCase(); }));
            const tags = (masterTags || []).slice();
            const knownNames = new Set(tags.map(function (tag) { return String(tag.name).trim().toLocaleLowerCase(); }));

            (currentTags || []).forEach(function (tag) {
                const tagName = String(tag.name).trim().toLocaleLowerCase();
                if (!knownNames.has(tagName)) {
                    tags.push(tag);
                    knownNames.add(tagName);
                }
            });

            if (!tags.length) {
                leadTagsChoices.innerHTML = '<span class="text-muted small">No tags available yet. Add them under Master &gt; Tag.</span>';
                return;
            }

            tags.forEach(function (tag) {
                const label = document.createElement('label');
                label.className = 'portal-tag-chip-check';
                label.style.borderColor = tag.color || '#14b8a6';

                const input = document.createElement('input');
                input.type = 'checkbox';
                input.name = 'tags[]';
                input.value = tag.id;
                input.checked = selected.includes(String(tag.id)) || selectedNames.has(String(tag.name).trim().toLocaleLowerCase());

                label.appendChild(input);
                label.appendChild(document.createTextNode(' ' + tag.name));
                leadTagsChoices.appendChild(label);
            });
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
            leadFormSubmitLabel.textContent = 'Create Lead';
            setManagementFieldsVisible(false);
            renderStageOptions(defaultMasterData.defaultStageId);
            renderSourceOptions(null);
            renderTagCheckboxes([]);

            const ownerSelect = document.getElementById('leadFormOwner');
            if (ownerSelect) ownerSelect.value = defaultMasterData.currentOwnerId || '';
            document.getElementById('leadFormStatus').value = 'active';
            document.getElementById('leadFormPhoneCountryCode').value = defaultMasterData.defaultPhoneCountryCode;

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
                leadFormSubmitLabel.textContent = 'Save Changes';
                setManagementFieldsVisible(true);

                document.getElementById('leadFormName').value = data.name || '';
                document.getElementById('leadFormEmail').value = data.email || '';
                document.getElementById('leadFormPhone').value = data.phone || '';
                document.getElementById('leadFormPhoneCountryCode').value = data.phone_country_code || defaultMasterData.defaultPhoneCountryCode;
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
                renderListingTagChoices(data.tags_master, data.tags, data.tag_ids);
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

        tagOverflowAddBtn.addEventListener('click', function () {
            const leadId = currentOverflowLeadId;
            closeTagOverflowPopover();
            if (leadId) openTagsModal(leadId);
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
                body: new URLSearchParams(new FormData(leadTagsForm)),
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

            const body = new URLSearchParams();
            window.appendLeadSelection(body);

            fetch("{{ route('portal.crm.leads.bulk-delete') }}", {
                method: 'DELETE',
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
