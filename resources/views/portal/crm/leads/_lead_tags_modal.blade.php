<style>
    #leadTagsModal .modal-dialog { max-width: 420px; }
    .lead-tags-list { display: flex; flex-wrap: wrap; align-content: flex-start; gap: 8px; max-height: 240px; min-height: 60px; overflow-y: auto; padding: 8px; border: 1px solid var(--portal-border, #dee2e6); border-radius: 10px; }
    .lead-tags-selected { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
    .lead-tags-selected:empty { display: none; }
    .lead-tags-selected__chip { display: inline-flex; align-items: center; gap: 6px; padding: 2px 6px 2px 10px; border-radius: 999px; font-size: .75rem; font-weight: 600; color: #fff; }
    .lead-tags-selected__chip button { border: 0; background: rgba(255,255,255,.25); color: inherit; width: 18px; height: 18px; border-radius: 50%; line-height: 1; font-size: .7rem; padding: 0; }
</style>
<div class="modal fade" id="leadTagsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content portal-lead-tags-modal">
            <form id="leadTagsForm" novalidate>
                <div class="modal-header border-0 pb-0">
                    <div>
                        <div class="portal-lead-view-eyebrow">Lead tags</div>
                        <h5 class="modal-title" id="leadTagsModalTitle">Manage tags</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                {{-- Tags are searched + paged on the server (20 at a time, more on scroll); the ticked
                     ones are kept in a JS set, so a save keeps tags that aren't in the loaded list. --}}
                <div class="modal-body pt-3">
                    <p class="text-muted small mb-2">Choose the labels that help your team prioritise this lead.</p>
                    <div class="lead-tags-selected" id="leadTagsSelected"></div>
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text"><i class="fas fa-magnifying-glass"></i></span>
                        <input type="search" class="form-control" id="leadTagsSearch" placeholder="Search tags" autocomplete="off" aria-label="Search tags">
                    </div>
                    <div class="lead-tags-list" id="leadTagsChoices" role="group" aria-label="Tags"></div>
                    <div class="small text-muted mt-1" id="leadTagsStatus"></div>
                    <div class="alert alert-danger d-none mt-3 mb-0" id="leadTagsError" role="alert"></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <span class="small text-muted me-auto" id="leadTagsCount"></span>
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
