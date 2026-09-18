{{-- Read-only lead profile. Edit remains a deliberate handoff to the shared
     LeadFormModal, keeping this view focused on reviewing the enquiry. --}}
<div class="modal fade" id="leadViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content portal-lead-view-modal">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-3 min-w-0">
                    <span class="portal-lead-view-avatar" id="leadViewAvatar">?</span>
                    <div class="min-w-0">
                        <div class="portal-lead-view-eyebrow">Lead profile</div>
                        <h5 class="modal-title text-truncate" id="leadViewName">-</h5>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="portal-badge-status" id="leadViewStatus">-</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <div class="modal-body pt-4">
                <div class="portal-lead-view-contact-grid mb-3">
                    <div class="portal-lead-view-contact-item">
                        <span class="portal-lead-view-contact-icon"><i class="fas fa-envelope" aria-hidden="true"></i></span>
                        <div class="min-w-0">
                            <div class="portal-lead-view-label">Email</div>
                            <div class="portal-lead-view-value text-truncate" id="leadViewEmail">-</div>
                        </div>
                    </div>
                    <div class="portal-lead-view-contact-item">
                        <span class="portal-lead-view-contact-icon"><i class="fas fa-phone" aria-hidden="true"></i></span>
                        <div class="min-w-0">
                            <div class="portal-lead-view-label">Phone</div>
                            <div class="portal-lead-view-value" id="leadViewPhone">-</div>
                        </div>
                    </div>
                </div>

                <div class="portal-lead-view-info-grid mb-3">
                    <div class="portal-lead-view-info-item">
                        <div class="portal-lead-view-label">Property</div>
                        <div class="portal-lead-view-value" id="leadViewProperty">-</div>
                    </div>
                    <div class="portal-lead-view-info-item d-none" id="leadViewOwnerCard">
                        <div class="portal-lead-view-label">Owner</div>
                        <div class="portal-lead-view-value" id="leadViewOwner">-</div>
                    </div>
                    <div class="portal-lead-view-info-item">
                        <div class="portal-lead-view-label">Stage</div>
                        <span class="portal-stage-pill" id="leadViewStage">—</span>
                    </div>
                    <div class="portal-lead-view-info-item">
                        <div class="portal-lead-view-label">Source</div>
                        <div class="portal-lead-view-value" id="leadViewSource">—</div>
                    </div>
                    <div class="portal-lead-view-info-item">
                        <div class="portal-lead-view-label">Received</div>
                        <div class="portal-lead-view-value" id="leadViewReceived">-</div>
                    </div>
                </div>

                <div class="portal-lead-view-section mb-3">
                    <div class="portal-lead-view-label mb-2">Tags</div>
                    <div class="d-flex flex-wrap gap-2" id="leadViewTags">—</div>
                </div>

                <div class="portal-lead-view-message mb-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="portal-lead-view-message-icon"><i class="fas fa-message" aria-hidden="true"></i></span>
                        <div class="portal-lead-view-label">Enquiry message</div>
                    </div>
                    <div class="portal-lead-view-message-copy" id="leadViewMessage">-</div>
                </div>

                <div class="portal-lead-view-notes">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="portal-lead-view-label">Activity</div>
                            <div class="fw-bold">Notes</div>
                        </div>
                        <button type="button" class="btn btn-sm portal-btn-ghost" id="openAddNoteModal"><i class="fas fa-plus me-1" aria-hidden="true"></i>Add note</button>
                    </div>
                    <div id="leadViewNotesList" class="d-flex flex-column gap-2"></div>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-portal-primary" id="leadViewEditBtn"><i class="fas fa-pen me-1" aria-hidden="true"></i>Edit lead</button>
            </div>
        </div>
    </div>
</div>
