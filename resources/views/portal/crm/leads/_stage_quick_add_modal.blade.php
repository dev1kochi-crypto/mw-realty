{{-- Reusable "+ Add Stage" quick-add modal for the Lead create/edit form.
     Expects a <select id="leadFormStage"> and a button id="openAddStageModal"
     elsewhere on the page. Posts to the same Stage master-data endpoint used
     by Master > Stages, just with an Accept: application/json header so it
     gets the new stage back as JSON instead of a redirect. Dispatches a
     "stage:created" event on document so any other listener (e.g. the Lead
     form's own master-data cache) can react without this modal knowing about it. --}}
<div class="modal fade" id="addStageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addStageForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add Stage</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Stage Name</label>
                        <input type="text" id="addStageName" class="form-control" required maxlength="100" placeholder="e.g. Site Visit Scheduled">
                        <div class="text-danger small mt-1" id="addStageError" style="display:none;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-portal-primary">Save Stage</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const modalEl = document.getElementById('addStageModal');
        const openBtn = document.getElementById('openAddStageModal');
        if (!modalEl || !openBtn) return;

        const modal = new bootstrap.Modal(modalEl);
        const form = document.getElementById('addStageForm');
        const nameInput = document.getElementById('addStageName');
        const errorEl = document.getElementById('addStageError');

        function resetForm() {
            nameInput.value = '';
            errorEl.style.display = 'none';
            errorEl.textContent = '';
        }

        openBtn.addEventListener('click', function () {
            resetForm();
            modal.show();
            setTimeout(function () { nameInput.focus(); }, 300);
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            errorEl.style.display = 'none';
            errorEl.textContent = '';

            // Created for whichever owner the Lead form is currently editing
            // (set on #leadForm's dataset) — so it lands in *that* owner's own
            // Master > Stage list, not necessarily the logged-in admin's.
            const ownerId = document.getElementById('leadForm')?.dataset.ownerId || '';

            fetch("{{ route('portal.crm.master.stages.store') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({ name: nameInput.value, owner_id: ownerId || undefined }),
            })
            .then(async function (response) {
                const data = await response.json().catch(function () { return {}; });

                if (!response.ok) {
                    const message = (data.errors && data.errors.name && data.errors.name[0]) || data.message || 'Could not add this stage.';
                    errorEl.textContent = message;
                    errorEl.style.display = 'block';
                    return;
                }

                const select = document.getElementById('leadFormStage');
                if (select && data.stage) {
                    const option = document.createElement('option');
                    option.value = data.stage.id;
                    option.textContent = data.stage.name;
                    option.selected = true;
                    select.appendChild(option);
                }

                if (data.stage) {
                    document.dispatchEvent(new CustomEvent('stage:created', { detail: data.stage }));
                }

                modal.hide();
            })
            .catch(function () {
                errorEl.textContent = 'Something went wrong. Please try again.';
                errorEl.style.display = 'block';
            });
        });
    })();
</script>
@endpush
