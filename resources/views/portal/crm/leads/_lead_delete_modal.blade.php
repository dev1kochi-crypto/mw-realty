{{-- Delete confirmation popup for the table's checkbox-selected leads —
     soft-deletes only (LeadService::deleteMany), same as the "Deleted Leads" /
     restore flow already in place. --}}
<div class="modal fade" id="leadDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-body text-center pt-4 px-4 pb-2">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-danger bg-opacity-10" style="width: 64px; height: 64px;">
                    <i class="fas fa-triangle-exclamation text-danger" style="font-size: 1.5rem;"></i>
                </div>
                <h5 class="modal-title fw-semibold mb-2">Delete Leads</h5>
                <p class="text-muted mb-0">Delete <strong id="leadDeleteCount" class="text-body">this lead</strong>? You can restore <span id="leadDeleteCountPlural">it</span> later from Deleted Leads.</p>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger px-4" id="leadDeleteConfirmBtn">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="leadDeleteSpinner" role="status" aria-hidden="true"></span>
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>
