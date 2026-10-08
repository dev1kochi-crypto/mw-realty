{{-- Import Leads popup. Two formats: our template / a Leads export ("Direct import"), or the CSV
     Meta's Leads Center downloads ("Facebook leads"). Submitted over AJAX: a wrong file is reported
     here (once — not also as the page alert); a good one is imported in the background
     (ProcessLeadImport) and this popup switches to its live progress. Without JS the form posts
     normally and errors come back as the "importError" flash. --}}
@php($importType = old('import_type', \App\Imports\LeadsImport::FORMAT_TEMPLATE))
<div class="modal fade" id="leadImportModal" tabindex="-1" aria-labelledby="leadImportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 46px; height: 46px; background: rgba(79, 70, 229, 0.1);">
                        <i class="fas fa-cloud-arrow-up" style="color: var(--portal-primary); font-size: 1.1rem;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="leadImportModalLabel">Import Leads</h5>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Bulk-add leads from a spreadsheet</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('portal.crm.leads.import') }}" method="POST" enctype="multipart/form-data" id="leadImportForm">
                @csrf
                <input type="hidden" name="import_form" value="1">
                <div class="modal-body pt-3">
                    <div class="alert alert-danger gap-2 align-items-start mb-3 {{ session('importError') ? 'd-flex' : 'd-none' }}" id="leadImportError" role="alert" style="font-size: 0.85rem; border-radius: 10px;">
                        <i class="fas fa-triangle-exclamation mt-1"></i>
                        <div id="leadImportErrorText">{{ session('importError') }}</div>
                    </div>

                    <label class="form-label fw-semibold">What are you importing?</label>
                    <div class="row g-2 mb-3" role="radiogroup">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="import_type" id="leadImportTypeTemplate" value="{{ \App\Imports\LeadsImport::FORMAT_TEMPLATE }}" @checked($importType !== \App\Imports\LeadsImport::FORMAT_FACEBOOK)>
                            <label for="leadImportTypeTemplate" class="lead-import-type d-flex gap-2 align-items-start p-3 h-100">
                                <i class="fas fa-table-list mt-1" style="color: var(--portal-primary);"></i>
                                <span>
                                    <span class="d-block fw-semibold">Direct import</span>
                                    <span class="d-block text-muted" style="font-size: 0.76rem;">Our Excel template, or a file exported from Leads</span>
                                </span>
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="import_type" id="leadImportTypeFacebook" value="{{ \App\Imports\LeadsImport::FORMAT_FACEBOOK }}" @checked($importType === \App\Imports\LeadsImport::FORMAT_FACEBOOK)>
                            <label for="leadImportTypeFacebook" class="lead-import-type d-flex gap-2 align-items-start p-3 h-100">
                                <i class="fab fa-facebook mt-1" style="color: #1877f2;"></i>
                                <span>
                                    <span class="d-block fw-semibold">Facebook leads</span>
                                    <span class="d-block text-muted" style="font-size: 0.76rem;">CSV downloaded from Meta Leads Center / Ads Manager</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="alert alert-info d-flex gap-2 align-items-start mb-3" style="font-size: 0.85rem; border-radius: 10px;" data-import-help="{{ \App\Imports\LeadsImport::FORMAT_TEMPLATE }}">
                        <i class="fas fa-circle-info mt-1"></i>
                        <div>
                            Use the provided Excel import template — or a file exported from Leads — without renaming or removing columns.
                            <a href="{{ route('portal.crm.leads.import.template') }}" class="fw-semibold">Download the template</a>, fill it in, then upload it below.
                        </div>
                    </div>
                    <div class="alert alert-info d-flex gap-2 align-items-start mb-3" style="font-size: 0.85rem; border-radius: 10px;" data-import-help="{{ \App\Imports\LeadsImport::FORMAT_FACEBOOK }}">
                        <i class="fas fa-circle-info mt-1"></i>
                        <div>
                            Upload the leads CSV exactly as downloaded from Meta (Leads Center or Ads Manager → Download leads). Each lead's
                            source is its ad set name; campaign, ad and form are saved on the lead.
                            <a href="{{ route('portal.crm.leads.import.facebook-sample') }}" class="fw-semibold">Download a sample file</a> to see the format.
                        </div>
                    </div>

                    <label class="form-label fw-semibold">File (.xlsx, .xls, .csv)</label>
                    <label for="leadImportFile" id="leadImportDropzone" class="d-flex align-items-center gap-3 p-3 mb-0" style="border: 1.5px dashed var(--portal-border); border-radius: 12px; cursor: pointer; background: var(--portal-bg); transition: border-color .15s ease, background .15s ease;">
                        <span class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 40px; height: 40px; background: var(--portal-surface); border: 1px solid var(--portal-border);">
                            <i class="fas fa-cloud-arrow-up text-muted"></i>
                        </span>
                        <span class="flex-grow-1 overflow-hidden">
                            <span class="d-block fw-semibold text-truncate" id="leadImportFileName">Choose a file or drag it here</span>
                            <span class="d-block text-muted" style="font-size: 0.78rem;">.xlsx, .xls or .csv — up to 10 MB</span>
                        </span>
                        <span class="btn btn-sm portal-btn-ghost flex-shrink-0">Browse</span>
                    </label>
                    <input type="file" name="file" id="leadImportFile" class="d-none @error('file') is-invalid @enderror" accept=".xlsx,.xls,.csv,.tsv,.txt" required>
                    @error('file')
                    <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
                    @enderror
                    @error('import_type')
                    <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
                    @enderror

                    <p class="text-muted mb-0 mt-3" style="font-size: 0.78rem;">
                        Every row is checked (name, email, phone, empty and duplicate rows). Large files import in the background — you'll get a
                        notification and a summary email with the file attached and an <strong>Import Status</strong> column (added, updated or skipped with the reason).
                    </p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-portal-primary px-4" id="leadImportSubmit">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="leadImportSpinner" role="status" aria-hidden="true"></span>
                        <i class="fas fa-upload me-1" id="leadImportSubmitIcon"></i> <span id="leadImportSubmitLabel">Upload &amp; Import</span>
                    </button>
                </div>
            </form>

            {{-- After a successful upload: the background import's live progress (LeadImportTracker below). --}}
            <div class="d-none" id="leadImportProgressPane">
                <div class="modal-body pt-3" data-lead-import-view="modal"></div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" id="leadImportCloseBtn">Close — keep importing</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .lead-import-type { border: 1.5px solid var(--portal-border); border-radius: 12px; cursor: pointer; background: var(--portal-surface); transition: border-color .15s ease, background .15s ease; }
    .lead-import-type:hover { border-color: var(--portal-primary); }
    .btn-check:checked + .lead-import-type { border-color: var(--portal-primary); background: rgba(79, 70, 229, 0.06); box-shadow: 0 0 0 1px var(--portal-primary) inset; }
    .btn-check:focus-visible + .lead-import-type { outline: 2px solid var(--portal-primary); outline-offset: 2px; }

    /* Import progress (popup + Leads page banner) */
    .limp { display: flex; gap: 14px; align-items: flex-start; }
    .limp-icon { position: relative; width: 48px; height: 48px; flex-shrink: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; background: rgba(79, 70, 229, 0.1); color: var(--portal-primary); }
    .limp-icon.is-working::before { content: ""; position: absolute; inset: -4px; border-radius: 50%; border: 2px solid transparent; border-top-color: var(--portal-primary); border-right-color: var(--portal-primary); animation: limp-spin 1s linear infinite; }
    .limp-icon.is-working i { animation: limp-float 1.4s ease-in-out infinite; }
    .limp-icon.is-done { background: #dcfce7; color: #15803d; animation: limp-pop .35s ease-out; }
    .limp-icon.is-failed { background: #fee2e2; color: #b91c1c; }
    .limp-body { flex: 1; min-width: 0; }
    .limp-title { font-weight: 600; }
    .limp-file { font-size: .78rem; color: var(--portal-muted, #6b7280); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .limp .progress { height: 8px; border-radius: 99px; margin: 10px 0 6px; background: var(--portal-bg); }
    .limp .progress-bar { background-color: var(--portal-primary); border-radius: 99px; transition: width .6s ease; }
    .limp-progress-queued .progress-bar { width: 30% !important; animation: limp-slide 1.3s ease-in-out infinite, progress-bar-stripes 1s linear infinite; }
    .limp-stats { display: flex; flex-wrap: wrap; gap: 6px; font-size: .78rem; margin-top: 8px; }
    .limp-stat { padding: 2px 9px; border-radius: 99px; font-weight: 600; }
    .limp-stat.added { background: #dcfce7; color: #15803d; }
    .limp-stat.updated { background: #dbeafe; color: #1d4ed8; }
    .limp-stat.skipped { background: #fee2e2; color: #b91c1c; }
    .limp-meta { font-size: .78rem; color: var(--portal-muted, #6b7280); }
    @keyframes limp-spin { to { transform: rotate(360deg); } }
    @keyframes limp-float { 0%, 100% { transform: translateY(2px); } 50% { transform: translateY(-3px); } }
    @keyframes limp-pop { 0% { transform: scale(.6); } 80% { transform: scale(1.08); } 100% { transform: scale(1); } }
    @keyframes limp-slide { 0% { margin-left: -30%; } 100% { margin-left: 100%; } }
    @media (prefers-reduced-motion: reduce) { .limp-icon.is-working::before, .limp-icon.is-working i, .limp-progress-queued .progress-bar, .limp-icon.is-done { animation: none; } }
</style>
@endpush

@push('scripts')
<script>
    /**
     * Shows a background lead import's progress in every [data-lead-import-view] (this popup and the
     * Leads page banner) and polls its status until it's done or failed.
     */
    window.LeadImportTracker = (function () {
        let current = null;
        let timer = null;
        const fmt = (n) => Number(n || 0).toLocaleString();
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        function html(p) {
            const working = p.status === 'queued' || p.status === 'running';
            const stats = '<div class="limp-stats">'
                + '<span class="limp-stat added">' + fmt(p.added) + ' added</span>'
                + '<span class="limp-stat updated">' + fmt(p.updated) + ' updated</span>'
                + '<span class="limp-stat skipped">' + fmt(p.skipped) + ' skipped</span></div>';

            if (working) {
                const queued = p.status === 'queued';
                return '<div class="limp">'
                    + '<div class="limp-icon is-working"><i class="fas fa-cloud-arrow-up"></i></div>'
                    + '<div class="limp-body">'
                    + '<div class="limp-title">' + (queued ? 'Starting import…' : 'Importing leads… ' + p.percent + '%') + '</div>'
                    + '<div class="limp-file">' + esc(p.file) + '</div>'
                    + '<div class="progress ' + (queued ? 'limp-progress-queued' : '') + '" role="progressbar" aria-valuenow="' + p.percent + '" aria-valuemin="0" aria-valuemax="100">'
                    + '<div class="progress-bar progress-bar-striped progress-bar-animated" style="width:' + Math.max(p.percent, 2) + '%"></div></div>'
                    + '<div class="limp-meta">' + (queued ? 'Waiting for the import worker — ' + fmt(p.total) + ' rows' : fmt(p.processed) + ' of ' + fmt(p.total) + ' rows checked')
                    + ' · You can close this and keep working; you\'ll be notified when it\'s done.</div>'
                    + (queued ? '' : stats)
                    + '</div></div>';
            }

            if (p.status === 'failed') {
                return '<div class="limp">'
                    + '<div class="limp-icon is-failed"><i class="fas fa-triangle-exclamation"></i></div>'
                    + '<div class="limp-body"><div class="limp-title">Import failed</div>'
                    + '<div class="limp-file">' + esc(p.file) + '</div>'
                    + '<div class="limp-meta mt-1">' + esc(p.error) + '</div></div></div>';
            }

            return '<div class="limp">'
                + '<div class="limp-icon is-done"><i class="fas fa-check"></i></div>'
                + '<div class="limp-body"><div class="limp-title">Import finished — ' + fmt(p.total) + ' rows</div>'
                + '<div class="limp-file">' + esc(p.file) + '</div>' + stats
                + '<div class="limp-meta mt-2">A summary email with this file and its Import Status column has been sent.</div>'
                + '<div class="d-flex flex-wrap gap-2 mt-2">'
                + (p.result_url ? '<a href="' + esc(p.result_url) + '" class="btn btn-sm portal-btn-ghost"><i class="fas fa-file-arrow-down me-1"></i> Download status file</a>' : '')
                + '<button type="button" class="btn btn-sm btn-portal-primary" data-lead-import-reload><i class="fas fa-rotate me-1"></i> Refresh leads</button>'
                + '</div></div></div>';
        }

        function render() {
            document.querySelectorAll('[data-lead-import-view]').forEach(function (el) {
                el.innerHTML = html(current);
            });
            const banner = document.getElementById('leadImportBanner');
            banner?.classList.remove('d-none');
            const closeBtn = document.getElementById('leadImportCloseBtn');
            if (closeBtn) {
                closeBtn.textContent = (current.status === 'queued' || current.status === 'running') ? 'Close — keep importing' : 'Close';
            }
        }

        function poll() {
            clearTimeout(timer);
            if (!current || !(current.status === 'queued' || current.status === 'running')) {
                return;
            }
            timer = setTimeout(function () {
                fetch(current.status_url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then((r) => r.ok ? r.json() : Promise.reject(r))
                    .then(function (data) { current = data.import; render(); poll(); })
                    .catch(function () { poll(); });
            }, current.status === 'queued' ? 3000 : 1500);
        }

        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-lead-import-reload]')) {
                const url = new URL(window.location.href);
                url.searchParams.delete('import');
                window.location.href = url.toString();
            }
        });

        return {
            track: function (progress) { current = progress; render(); poll(); },
            current: function () { return current; },
        };
    })();

    document.addEventListener('DOMContentLoaded', function () {
        const importModalEl = document.getElementById('leadImportModal');
        const importModal = new bootstrap.Modal(importModalEl);
        const openImportButton = document.getElementById('openLeadImportModal');
        const importForm = document.getElementById('leadImportForm');
        const progressPane = document.getElementById('leadImportProgressPane');
        const fileInput = document.getElementById('leadImportFile');
        const fileNameLabel = document.getElementById('leadImportFileName');
        const dropzone = document.getElementById('leadImportDropzone');
        const errorBox = document.getElementById('leadImportError');
        const submitBtn = document.getElementById('leadImportSubmit');
        const typeInputs = importForm.querySelectorAll('input[name="import_type"]');

        function showForm() {
            importForm.classList.remove('d-none');
            progressPane.classList.add('d-none');
        }
        function showProgress() {
            importForm.classList.add('d-none');
            progressPane.classList.remove('d-none');
        }
        function showError(message) {
            document.getElementById('leadImportErrorText').textContent = message;
            errorBox.classList.toggle('d-none', !message);
            errorBox.classList.toggle('d-flex', !!message);
        }
        function setBusy(busy) {
            submitBtn.disabled = busy;
            document.getElementById('leadImportSpinner').classList.toggle('d-none', !busy);
            document.getElementById('leadImportSubmitIcon').classList.toggle('d-none', busy);
            document.getElementById('leadImportSubmitLabel').textContent = busy ? 'Checking file…' : 'Upload & Import';
        }

        // The Import button: while an import runs, it opens its progress instead of a new upload.
        openImportButton?.addEventListener('click', function () {
            const running = window.LeadImportTracker.current();
            (running && (running.status === 'queued' || running.status === 'running')) ? showProgress() : showForm();
            importModal.show();
        });
        importModalEl.addEventListener('hidden.bs.modal', function () {
            const running = window.LeadImportTracker.current();
            if (!running || !(running.status === 'queued' || running.status === 'running')) {
                showForm();
                showError('');
            }
        });

        // Show the instructions + sample file of the chosen format only.
        function syncImportType() {
            const type = importForm.querySelector('input[name="import_type"]:checked')?.value;
            importForm.querySelectorAll('[data-import-help]').forEach(function (el) {
                el.classList.toggle('d-none', el.dataset.importHelp !== type);
                el.classList.toggle('d-flex', el.dataset.importHelp === type);
            });
            showError('');
        }
        typeInputs.forEach(function (input) { input.addEventListener('change', syncImportType); });
        syncImportType();
        @if(session('importError'))
        showError(@json(session('importError')));
        @endif

        function updateFileName() {
            fileNameLabel.textContent = fileInput.files.length ? fileInput.files[0].name : 'Choose a file or drag it here';
            showError('');
        }

        fileInput.addEventListener('change', updateFileName);

        ['dragover', 'dragenter'].forEach(function (evt) {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                dropzone.style.borderColor = 'var(--portal-primary)';
            });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                dropzone.style.borderColor = 'var(--portal-border)';
            });
        });
        dropzone.addEventListener('drop', function (e) {
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                updateFileName();
            }
        });

        importForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!fileInput.files.length) {
                showError('Choose a file to import.');
                return;
            }
            setBusy(true);
            showError('');

            fetch(importForm.action, {
                method: 'POST',
                body: new FormData(importForm),
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (response) {
                    return response.json().catch(() => ({})).then((data) => ({ ok: response.ok, data: data }));
                })
                .then(function (result) {
                    if (!result.ok) {
                        const errors = result.data.errors ? Object.values(result.data.errors).flat() : [];
                        showError(errors.length ? errors.join(' ') : (result.data.message || 'The file could not be imported. Please try again.'));
                        return;
                    }
                    importForm.reset();
                    updateFileName();
                    syncImportType();
                    showProgress();
                    window.LeadImportTracker.track(result.data.import);
                })
                .catch(function () {
                    showError('Upload failed — check your connection and try again.');
                })
                .finally(function () { setBusy(false); });
        });

        @if(old('import_form') && ($errors->any() || session('importError')))
        importModal.show();
        @endif
    });
</script>
@endpush
