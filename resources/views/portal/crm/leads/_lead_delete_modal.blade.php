{{-- Delete confirmation popup for the table's checkbox-selected leads —
     soft-deletes only (LeadService::deleteMany), same as the "Deleted Leads" /
     restore flow already in place. --}}
<div class="modal fade" id="leadDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Leads</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete <strong id="leadDeleteCount">this lead</strong>? You can restore <span id="leadDeleteCountPlural">it</span> later from Deleted Leads.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="leadDeleteConfirmBtn">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="leadDeleteSpinner" role="status" aria-hidden="true"></span>
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>
