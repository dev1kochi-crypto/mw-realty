{{-- Read-only Lead detail popup. Its only action is "Edit", which hands off
     to the same LeadFormModal in edit mode — this modal never posts anything. --}}
<div class="modal fade" id="leadViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Lead from <span id="leadViewName">-</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Email</dt>
                    <dd class="col-sm-8" id="leadViewEmail">-</dd>

                    <dt class="col-sm-4 text-muted">Phone</dt>
                    <dd class="col-sm-8" id="leadViewPhone">-</dd>

                    <dt class="col-sm-4 text-muted">Property</dt>
                    <dd class="col-sm-8" id="leadViewProperty">-</dd>

                    <dt class="col-sm-4 text-muted d-none" id="leadViewOwnerRow">Owner</dt>
                    <dd class="col-sm-8 d-none" id="leadViewOwnerRow2"><span id="leadViewOwner">-</span></dd>

                    <dt class="col-sm-4 text-muted">Stage</dt>
                    <dd class="col-sm-8" id="leadViewStage">—</dd>

                    <dt class="col-sm-4 text-muted">Source</dt>
                    <dd class="col-sm-8" id="leadViewSource">—</dd>

                    <dt class="col-sm-4 text-muted">Tags</dt>
                    <dd class="col-sm-8"><span id="leadViewTags">—</span></dd>

                    <dt class="col-sm-4 text-muted">Status</dt>
                    <dd class="col-sm-8" id="leadViewStatus">-</dd>

                    <dt class="col-sm-4 text-muted">Received</dt>
                    <dd class="col-sm-8" id="leadViewReceived">-</dd>

                    <dt class="col-sm-4 text-muted">Message</dt>
                    <dd class="col-sm-8" id="leadViewMessage" style="white-space: pre-wrap;">-</dd>
                </dl>

                {{-- Activity history — notes added after the lead exists, one entry per
                     interaction (call, follow-up, meeting, ...), never overwritten. --}}
                <hr>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="portal-section-title mb-0" style="font-size: 1rem;">Notes</div>
                    <button type="button" class="btn btn-sm portal-btn-ghost" id="openAddNoteModal">+ Add Note</button>
                </div>
                <div id="leadViewNotesList" class="d-flex flex-column gap-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-portal-primary" id="leadViewEditBtn">Edit</button>
            </div>
        </div>
    </div>
</div>
