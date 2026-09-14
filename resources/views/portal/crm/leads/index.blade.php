@extends('portal.crm._layout')

@section('title', 'Leads')

@section('crm-content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
    <div>
        <div class="portal-section-title mb-0">{{ $isAdmin ? 'All Leads' : 'My Leads' }}</div>
        <p class="text-muted mb-0" style="font-size: 0.88rem;">Every enquiry a visitor sends about {{ $isAdmin ? 'any' : 'your' }} listing lands here.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('portal.crm.leads.trashed') }}" class="portal-btn-ghost btn btn-sm"><i class="fas fa-trash-can me-1"></i> Deleted Leads</a>
        <a href="{{ route('portal.crm.leads.import.template') }}" class="portal-btn-ghost btn btn-sm"><i class="fas fa-file-arrow-down me-1"></i> Download Import Template</a>
        <a href="{{ route('portal.crm.leads.import.form') }}" class="portal-btn-ghost btn btn-sm"><i class="fas fa-file-import me-1"></i> Import</a>
        <a href="{{ route('portal.crm.leads.export', request()->query()) }}" class="portal-btn-ghost btn btn-sm"><i class="fas fa-file-export me-1"></i> Export</a>
        <button type="button" id="openCreateLeadModal" class="btn btn-portal-primary btn-sm"><i class="fas fa-plus me-1"></i> Add Lead</button>
    </div>
</div>

<div id="crmFlashContainer">
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif
</div>

@if(session('importResult'))
@php($result = session('importResult'))
<div class="portal-card p-3 mb-3">
    <div class="fw-semibold mb-1">Import finished — {{ $result['imported'] }} imported, {{ $result['skipped'] }} skipped.</div>
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
@include('portal.crm.leads._lead_view_modal')
@include('portal.crm.leads._lead_note_modal')
@include('portal.crm.leads._lead_delete_modal')
@include('portal.crm.leads._stage_quick_add_modal')

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const defaultMasterData = JSON.parse(document.getElementById('leadFormDefaults').textContent);
        let currentMasterData = {
            stages: defaultMasterData.stages.slice(),
            sources: defaultMasterData.sources.slice(),
            tags: defaultMasterData.tags.slice(),
        };
        let selectedLeadIds = new Set();
        let currentViewedLeadId = null;

        const leadFormModalEl = document.getElementById('leadFormModal');
        const leadFormModal = new bootstrap.Modal(leadFormModalEl);
        const leadForm = document.getElementById('leadForm');
        const leadFormTitle = document.getElementById('leadFormModalTitle');
        const leadFormSubmitLabel = document.getElementById('leadFormSubmitLabel');
        const leadFormSpinner = document.getElementById('leadFormSpinner');
        const leadFormSubmitBtn = document.getElementById('leadFormSubmitBtn');
        const leadFormGeneralError = document.getElementById('leadFormGeneralError');

        const viewModal = new bootstrap.Modal(document.getElementById('leadViewModal'));
        const deleteModal = new bootstrap.Modal(document.getElementById('leadDeleteModal'));
        const noteModal = new bootstrap.Modal(document.getElementById('leadNoteModal'));
        const leadNoteForm = document.getElementById('leadNoteForm');
        const leadNoteBody = document.getElementById('leadNoteBody');
        const leadNoteGeneralError = document.getElementById('leadNoteGeneralError');
        const leadNoteSubmitBtn = document.getElementById('leadNoteSubmitBtn');
        const leadNoteSpinner = document.getElementById('leadNoteSpinner');

        function buildNoteElement(note) {
            const item = document.createElement('div');
            item.className = 'portal-card p-2';
            const body = document.createElement('div');
            body.style.whiteSpace = 'pre-wrap';
            body.textContent = note.body;
            const meta = document.createElement('div');
            meta.className = 'text-muted small mt-1';
            meta.textContent = (note.author_name || 'Someone') + ' · ' + note.created_at;
            item.appendChild(body);
            item.appendChild(meta);
            return item;
        }

        // notes_history arrives newest-first from the server, so this preserves
        // that order; a freshly-added note is prepended separately (see the
        // note-form submit handler) since it wasn't part of that server list.
        function renderNotesList(notes) {
            const list = document.getElementById('leadViewNotesList');
            list.innerHTML = '';
            if (!notes || !notes.length) {
                list.innerHTML = '<span class="text-muted small">No notes yet.</span>';
                return;
            }
            notes.forEach(function (note) { list.appendChild(buildNoteElement(note)); });
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

        function openEditModal(id) {
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

        function openViewModal(id) {
            fetchLead(id).then(function (data) {
                currentViewedLeadId = id;
                document.getElementById('leadViewName').textContent = data.name || 'Unknown';
                document.getElementById('leadViewEmail').textContent = data.email || '-';
                document.getElementById('leadViewPhone').textContent = data.formatted_phone || '-';
                document.getElementById('leadViewProperty').textContent = data.property_title || '-';

                const ownerRow = document.getElementById('leadViewOwnerRow');
                const ownerRow2 = document.getElementById('leadViewOwnerRow2');
                if (data.is_admin) {
                    ownerRow.classList.remove('d-none');
                    ownerRow2.classList.remove('d-none');
                    document.getElementById('leadViewOwner').textContent = data.owner_name || '-';
                } else {
                    ownerRow.classList.add('d-none');
                    ownerRow2.classList.add('d-none');
                }

                document.getElementById('leadViewStage').textContent = data.stage_name || '—';
                document.getElementById('leadViewSource').textContent = data.source_name || '—';
                document.getElementById('leadViewStatus').textContent = data.status ? (data.status.charAt(0).toUpperCase() + data.status.slice(1)) : '-';
                document.getElementById('leadViewReceived').textContent = data.created_at || '-';
                document.getElementById('leadViewMessage').textContent = data.message || '-';
                renderNotesList(data.notes_history);

                const tagsContainer = document.getElementById('leadViewTags');
                tagsContainer.innerHTML = '';
                if (data.tags && data.tags.length) {
                    data.tags.forEach(function (tag) {
                        const span = document.createElement('span');
                        span.className = 'portal-tag-chip me-1';
                        span.style.background = tag.color + '22';
                        span.style.color = tag.color;
                        span.textContent = tag.name;
                        tagsContainer.appendChild(span);
                    });
                } else {
                    tagsContainer.textContent = '—';
                }

                document.getElementById('leadViewEditBtn').dataset.id = id;
                viewModal.show();
            }).catch(function () {
                alert('Could not load this lead. Please try again.');
            });
        }

        function showFlash(type, message) {
            const container = document.getElementById('crmFlashContainer');
            if (!container) return;
            const alertEl = document.createElement('div');
            alertEl.className = 'alert alert-' + type + ' alert-dismissible fade show';
            alertEl.setAttribute('role', 'alert');
            alertEl.textContent = message;

            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'btn-close';
            closeBtn.setAttribute('data-bs-dismiss', 'alert');
            alertEl.appendChild(closeBtn);
            container.appendChild(alertEl);

            setTimeout(function () {
                bootstrap.Alert.getOrCreateInstance(alertEl).close();
            }, 4000);
        }

        window.reloadLeadsListing = function () {
            fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    document.getElementById('leadsListingWrapper').innerHTML = html;
                    selectedLeadIds.clear();
                });
        };

        document.getElementById('openCreateLeadModal').addEventListener('click', openCreateModal);

        document.addEventListener('click', function (e) {
            const editBtn = e.target.closest('.edit-lead-btn');
            if (editBtn) { openEditModal(editBtn.dataset.id); return; }

            const viewBtn = e.target.closest('.view-lead-btn');
            if (viewBtn) { openViewModal(viewBtn.dataset.id); return; }

            const stagePicker = e.target.closest('.portal-stage-picker');
            if (stagePicker) {
                const container = stagePicker.closest('.portal-inline-stage');
                const select = container.querySelector('.portal-stage-select');
                stagePicker.classList.add('d-none');
                select.classList.remove('d-none');
                select.focus();
            }
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

        function updateBulkActionsBar() {
            const bar = document.getElementById('leadBulkActionsBar');
            const count = selectedLeadIds.size;
            document.getElementById('leadSelectedCount').textContent = count;
            bar.classList.toggle('d-none', count === 0);

            const selectAll = document.getElementById('selectAllLeads');
            if (selectAll) {
                const boxes = document.querySelectorAll('.lead-select-checkbox');
                selectAll.checked = boxes.length > 0 && count === boxes.length;
                selectAll.indeterminate = count > 0 && count < boxes.length;
            }
        }

        document.addEventListener('change', function (e) {
            if (e.target.matches('.lead-select-checkbox')) {
                if (e.target.checked) { selectedLeadIds.add(e.target.value); } else { selectedLeadIds.delete(e.target.value); }
                updateBulkActionsBar();
                return;
            }

            if (e.target.id === 'selectAllLeads') {
                document.querySelectorAll('.lead-select-checkbox').forEach(function (box) {
                    box.checked = e.target.checked;
                    if (e.target.checked) { selectedLeadIds.add(box.value); } else { selectedLeadIds.delete(box.value); }
                });
                updateBulkActionsBar();
            }
        });

        document.addEventListener('click', function (e) {
            if (e.target.closest('#bulkDeleteLeadsBtn')) {
                const count = selectedLeadIds.size;
                document.getElementById('leadDeleteCount').textContent = count === 1 ? '1 lead' : count + ' leads';
                document.getElementById('leadDeleteCountPlural').textContent = count === 1 ? 'it' : 'them';
                deleteModal.show();
            }
        });

        document.getElementById('leadViewEditBtn').addEventListener('click', function () {
            const id = this.dataset.id;
            viewModal.hide();
            openEditModal(id);
        });

        document.getElementById('openAddNoteModal').addEventListener('click', function () {
            leadNoteBody.value = '';
            leadNoteGeneralError.classList.add('d-none');
            leadNoteBody.classList.remove('is-invalid');
            noteModal.show();
            setTimeout(function () { leadNoteBody.focus(); }, 300);
        });

        leadNoteForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!currentViewedLeadId) return;

            leadNoteGeneralError.classList.add('d-none');
            leadNoteBody.classList.remove('is-invalid');
            leadNoteSubmitBtn.disabled = true;
            leadNoteSpinner.classList.remove('d-none');

            fetch("{{ url('portal/crm/leads') }}/" + currentViewedLeadId + "/notes", {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: new URLSearchParams({ body: leadNoteBody.value }),
            })
            .then(async function (response) {
                const data = await response.json().catch(function () { return {}; });

                if (response.status === 422) {
                    const message = (data.errors && data.errors.body && data.errors.body[0]) || 'Please enter a note.';
                    leadNoteBody.classList.add('is-invalid');
                    leadNoteGeneralError.textContent = message;
                    leadNoteGeneralError.classList.remove('d-none');
                    return;
                }
                if (!response.ok) {
                    leadNoteGeneralError.textContent = data.message || 'Could not save this note. Please try again.';
                    leadNoteGeneralError.classList.remove('d-none');
                    return;
                }

                const list = document.getElementById('leadViewNotesList');
                if (list.querySelector('.text-muted')) list.innerHTML = '';
                list.prepend(buildNoteElement(data.note));

                noteModal.hide();
                showFlash('success', data.message || 'Note added.');
            })
            .catch(function () {
                leadNoteGeneralError.textContent = 'Something went wrong. Please try again.';
                leadNoteGeneralError.classList.remove('d-none');
            })
            .finally(function () {
                leadNoteSubmitBtn.disabled = false;
                leadNoteSpinner.classList.add('d-none');
            });
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

                leadFormModal.hide();
                showFlash('success', data.message || 'Saved.');
                reloadLeadsListing();
            })
            .catch(function () {
                leadFormGeneralError.textContent = 'Something went wrong. Please try again.';
                leadFormGeneralError.classList.remove('d-none');
            })
            .finally(function () {
                setSubmitting(false);
            });
        });

        document.getElementById('leadDeleteConfirmBtn').addEventListener('click', function () {
            if (!selectedLeadIds.size) return;
            const btn = this;
            const spinner = document.getElementById('leadDeleteSpinner');
            btn.disabled = true;
            spinner.classList.remove('d-none');

            const body = new URLSearchParams();
            selectedLeadIds.forEach(function (id) { body.append('ids[]', id); });

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

        const params = new URLSearchParams(window.location.search);
        if (params.get('lead')) {
            openViewModal(params.get('lead'));
        }
    });
</script>
@endpush
@endsection
