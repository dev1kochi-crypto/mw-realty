<div class="modal fade" id="leadTagsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-scrollable">
        <div class="modal-content portal-lead-tags-modal">
            <form id="leadTagsForm" novalidate>
                <div class="modal-header border-0 pb-0">
                    <div>
                        <div class="portal-lead-view-eyebrow">Lead tags</div>
                        <h5 class="modal-title" id="leadTagsModalTitle">Manage tags</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <p class="text-muted small mb-3">Choose the labels that help your team prioritise this lead.</p>
                    <div class="d-flex flex-wrap gap-2" id="leadTagsChoices"></div>
                    <div class="alert alert-danger d-none mt-3 mb-0" id="leadTagsError" role="alert"></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-portal-primary" id="leadTagsSaveBtn">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="leadTagsSpinner" role="status" aria-hidden="true"></span>
                        Save tags
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
