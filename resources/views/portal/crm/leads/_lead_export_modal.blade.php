{{-- Export Leads popup — shows how many leads (matching the current filters)
     are about to be downloaded, then hands off to the existing export route. --}}
<div class="modal fade" id="leadExportModal" tabindex="-1" aria-labelledby="leadExportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 46px; height: 46px; background: rgba(20, 184, 166, 0.12);">
                        <i class="fas fa-file-export" style="color: var(--portal-accent); font-size: 1.1rem;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="leadExportModalLabel">Export Leads</h5>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Download the current list as an Excel file</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="d-flex align-items-center gap-3 p-3" style="border: 1px solid var(--portal-border); border-radius: 12px; background: var(--portal-bg);">
                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 40px; height: 40px; background: var(--portal-surface); border: 1px solid var(--portal-border);">
                        <i class="fas fa-users text-muted"></i>
                    </div>
                    <div>
                        <span class="d-block fw-semibold" style="font-size: 1.05rem;">{{ $leads->count() }} {{ Str::plural('lead', $leads->count()) }}</span>
                        <span class="d-block text-muted" style="font-size: 0.8rem;">Matching your current search and filters</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <a href="{{ route('portal.crm.leads.export', request()->query()) }}" id="leadExportDownloadBtn" class="btn btn-portal-primary px-4">
                    <i class="fas fa-download me-1"></i> Download
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const exportModalEl = document.getElementById('leadExportModal');
        const exportModal = new bootstrap.Modal(exportModalEl);
        const openExportButton = document.getElementById('openLeadExportModal');

        openExportButton?.addEventListener('click', function () {
            exportModal.show();
        });

        document.getElementById('leadExportDownloadBtn')?.addEventListener('click', function () {
            setTimeout(function () { exportModal.hide(); }, 300);
        });
    });
</script>
@endpush
