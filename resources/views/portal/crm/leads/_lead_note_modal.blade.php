{{-- Small popup for adding one activity-history entry to the currently-viewed
     lead (see the "Notes" section of _lead_view_modal.blade.php). Posts to
     LeadNoteController@store — entries are appended, never overwritten. --}}
<div class="modal fade" id="leadNoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="leadNoteForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title">Add Note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="leadNoteGeneralError" role="alert"></div>
                    <label class="form-label fw-semibold">Note</label>
                    <textarea id="leadNoteBody" class="form-control" rows="4" maxlength="5000" placeholder="e.g. Called and discussed pricing, following up next week." required></textarea>
                    <div class="invalid-feedback" data-error-for="body"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-portal-primary" id="leadNoteSubmitBtn">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="leadNoteSpinner" role="status" aria-hidden="true"></span>
                        Save Note
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
