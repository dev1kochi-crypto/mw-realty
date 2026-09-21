{{-- Import Leads popup — same Excel upload flow as the standalone import page,
     posted from wherever the "Import" button lives so validation errors reopen this modal. --}}
<div class="modal fade" id="leadImportModal" tabindex="-1" aria-labelledby="leadImportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <form action="{{ route('portal.crm.leads.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="import_form" value="1">
                <div class="modal-header border-0 pb-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 46px; height: 46px; background: rgba(79, 70, 229, 0.1);">
                            <i class="fas fa-file-import" style="color: var(--portal-primary); font-size: 1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0" id="leadImportModalLabel">Import Leads</h5>
                            <p class="text-muted mb-0" style="font-size: 0.8rem;">Bulk-add leads from a spreadsheet</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="alert alert-info d-flex gap-2 align-items-start mb-4" style="font-size: 0.85rem; border-radius: 10px;">
                        <i class="fas fa-circle-info mt-1"></i>
                        <div>
                            Please use the provided Excel import template. Leads can only be imported using the supported template format.
                            <a href="{{ route('portal.crm.leads.import.template') }}" class="fw-semibold">Download the template</a> first, fill it in, then upload it below.
                        </div>
                    </div>

                    <label class="form-label fw-semibold">Excel file (.xlsx, .xls, .csv)</label>
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
                    <input type="file" name="file" id="leadImportFile" class="d-none @error('file') is-invalid @enderror" accept=".xlsx,.xls,.csv" required>
                    @error('file')
                    <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-portal-primary px-4"><i class="fas fa-upload me-1"></i> Upload &amp; Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const importModalEl = document.getElementById('leadImportModal');
        const importModal = new bootstrap.Modal(importModalEl);
        const openImportButton = document.getElementById('openLeadImportModal');
        const fileInput = document.getElementById('leadImportFile');
        const fileNameLabel = document.getElementById('leadImportFileName');
        const dropzone = document.getElementById('leadImportDropzone');

        openImportButton?.addEventListener('click', function () {
            importModal.show();
        });

        function updateFileName() {
            fileNameLabel.textContent = fileInput.files.length ? fileInput.files[0].name : 'Choose a file or drag it here';
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

        @if(old('import_form') && ($errors->any() || session('error')))
        importModal.show();
        @endif
    });
</script>
@endpush
