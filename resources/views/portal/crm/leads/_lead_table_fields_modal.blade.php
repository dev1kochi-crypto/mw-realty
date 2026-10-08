{{-- Table Fields — two panes: "Shown in table" (drag the grip to order, × to hide) and "Add fields"
     (every field by group, searchable; click to show / hide). Both are rendered by the leads page
     script from $leadTableFields / $leadTableGroups. Saved per user in lead_table_preferences
     (LeadTablePreferenceService), so the table looks the same next time; columns can also be
     dragged on the table header itself. Lead always stays first. --}}
<div class="modal fade" id="leadTableFieldsModal" tabindex="-1" aria-labelledby="leadTableFieldsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content portal-modal-content portal-tf">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="portal-tf-icon" aria-hidden="true"><i class="fas fa-table-columns"></i></span>
                    <div>
                        <h5 class="modal-title" id="leadTableFieldsModalTitle">Table Fields</h5>
                        <p class="text-muted small mb-0">Pick the columns for your leads table and drag them into order.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="leadTableFieldsForm" data-no-spinner="true" class="d-flex flex-column" style="min-height: 0;">
                <div class="modal-body p-0">
                    <div id="leadTableFieldsError" class="alert alert-danger py-2 small d-none m-3 mb-0" role="alert"></div>
                    <div class="portal-tf-panes">
                        <section class="portal-tf-pane" aria-labelledby="leadTableFieldsShownTitle">
                            <div class="portal-tf-pane-head">
                                <h6 id="leadTableFieldsShownTitle">Shown in table</h6>
                                <span class="portal-tf-count" id="leadTableFieldsCount"></span>
                            </div>
                            <p class="portal-tf-hint"><i class="fas fa-grip-vertical" aria-hidden="true"></i> Drag to reorder — left to right in the table.</p>
                            <ol class="portal-tf-shown" id="leadTableFieldsShown"></ol>
                        </section>
                        <section class="portal-tf-pane portal-tf-pane-catalog" aria-labelledby="leadTableFieldsAddTitle">
                            <div class="portal-tf-pane-head">
                                <h6 id="leadTableFieldsAddTitle">Add fields</h6>
                            </div>
                            <div class="portal-tf-search">
                                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                                <input type="search" class="form-control form-control-sm" id="leadTableFieldsSearch" placeholder="Search fields…" aria-label="Search fields" autocomplete="off">
                            </div>
                            <div id="leadTableFieldsCatalog" class="portal-tf-catalog"></div>
                            <p class="portal-tf-empty d-none" id="leadTableFieldsNoMatch">No fields match your search.</p>
                        </section>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link btn-sm text-decoration-none me-auto px-0" id="leadTableFieldsReset"><i class="fas fa-rotate-left me-1" aria-hidden="true"></i>Reset to default</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-portal-primary" id="leadTableFieldsSaveBtn">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="leadTableFieldsSpinner" aria-hidden="true"></span>
                        Save Fields
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
<style>
    .portal-tf .modal-header { align-items: flex-start; }
    .portal-tf-icon { width: 40px; height: 40px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; background: rgba(79, 70, 229, .1); color: var(--portal-primary); flex-shrink: 0; }
    .portal-tf-panes { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr); min-height: 0; }
    .portal-tf-pane { padding: 1rem 1.25rem; min-width: 0; }
    .portal-tf-pane-catalog { border-left: 1px solid var(--portal-border); background: var(--portal-bg); }
    .portal-tf-pane-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-bottom: .35rem; }
    .portal-tf-pane-head h6 { margin: 0; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--portal-muted); }
    .portal-tf-count { font-size: .72rem; font-weight: 600; padding: .15rem .55rem; border-radius: 999px; background: rgba(79, 70, 229, .1); color: var(--portal-primary); }
    .portal-tf-hint { font-size: .74rem; color: var(--portal-muted); margin-bottom: .6rem; }

    /* Shown in table */
    .portal-tf-shown { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: .35rem; counter-reset: tf; }
    .portal-tf-item { display: flex; align-items: center; gap: .55rem; padding: .45rem .55rem; border: 1px solid var(--portal-border); border-radius: 10px; background: #fff; font-size: .875rem; counter-increment: tf; }
    .portal-tf-item::before { content: counter(tf); min-width: 1.2rem; text-align: center; font-size: .7rem; font-weight: 600; color: var(--portal-muted); }
    .portal-tf-item:hover { border-color: #c7d2fe; }
    .portal-tf-item.is-pinned { background: var(--portal-bg); }
    .portal-tf-item .portal-tf-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500; }
    .portal-tf-item .portal-tf-meta { font-size: .7rem; color: var(--portal-muted); white-space: nowrap; }
    .portal-tf-grip { color: #9ca3af; cursor: grab; padding: 0 2px; display: inline-flex; align-items: center; touch-action: none; }
    .portal-tf-grip.is-disabled { cursor: default; color: #c4c8d0; font-size: .75rem; }
    .portal-tf-remove { border: 0; background: transparent; color: #9ca3af; width: 26px; height: 26px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; }
    .portal-tf-remove:hover, .portal-tf-remove:focus-visible { background: #fee2e2; color: #dc2626; }
    .portal-tf-item.sortable-ghost { opacity: .4; border-style: dashed; }
    .portal-tf-item.sortable-chosen { box-shadow: 0 6px 18px rgba(15, 23, 42, .12); }
    .portal-tf-item.is-new { animation: portal-tf-flash 1s ease; }
    @keyframes portal-tf-flash { from { background: rgba(79, 70, 229, .12); } to { background: #fff; } }

    /* Add fields */
    .portal-tf-search { position: sticky; top: -1rem; z-index: 1; margin: 0 -1.25rem .75rem; padding: .5rem 1.25rem; background: var(--portal-bg); }
    .portal-tf-search i { position: absolute; left: calc(1.25rem + .7rem); top: 50%; transform: translateY(-50%); color: var(--portal-muted); font-size: .8rem; }
    .portal-tf-search input { padding-left: 2rem; border-radius: 10px; background: #fff; }
    .portal-tf-group + .portal-tf-group { margin-top: .9rem; }
    .portal-tf-group-title { display: flex; align-items: center; gap: .4rem; font-size: .76rem; font-weight: 600; color: #475569; margin-bottom: .4rem; }
    .portal-tf-group-title i { color: var(--portal-muted); width: 14px; text-align: center; }
    .portal-tf-chips { display: flex; flex-wrap: wrap; gap: .4rem; }
    .portal-tf-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .3rem .7rem; font-size: .8rem; border-radius: 999px; border: 1px solid var(--portal-border); background: #fff; color: #334155; transition: background-color .15s ease, border-color .15s ease, color .15s ease; }
    .portal-tf-chip i { font-size: .68rem; }
    .portal-tf-chip:hover { border-color: var(--portal-primary); color: var(--portal-primary); }
    .portal-tf-chip.is-on { background: var(--portal-primary); border-color: var(--portal-primary); color: #fff; }
    .portal-tf-chip.is-on:hover { opacity: .9; color: #fff; }
    .portal-tf-chip:disabled { opacity: .55; cursor: not-allowed; }
    .portal-tf-empty { font-size: .8rem; color: var(--portal-muted); text-align: center; padding: 1rem 0; margin: 0; }

    /* Desktop: each pane scrolls on its own (the cap is on the pane — a grid row would just grow to its content). */
    @media (min-width: 768px) {
        .portal-tf .modal-body { overflow: hidden; }
        .portal-tf-pane { max-height: min(62vh, 560px); overflow-y: auto; overscroll-behavior: contain; }
    }
    @media (max-width: 767.98px) {
        .portal-tf-panes { grid-template-columns: 1fr; }
        .portal-tf-pane-catalog { border-left: 0; border-top: 1px solid var(--portal-border); }
    }

    /* Table header drag */
    #leadsDataTable th.portal-th-draggable { white-space: nowrap; }
    #leadsDataTable .portal-th-grip { color: #b6bcc8; cursor: grab; margin-right: 6px; opacity: 0; transition: opacity .15s ease; touch-action: none; }
    #leadsDataTable th.portal-th-draggable:hover .portal-th-grip, #leadsDataTable th.portal-th-draggable:focus-within .portal-th-grip { opacity: 1; }
    #leadsDataTable th.sortable-ghost { background: rgba(79, 70, 229, .08); outline: 2px dashed var(--portal-primary); outline-offset: -2px; }
    @media (hover: none) { #leadsDataTable .portal-th-grip { opacity: 1; } }
    #leadsDataTable .portal-insight-link { font-weight: 600; text-decoration: none; }
    #leadsDataTable .portal-insight-link:hover { text-decoration: underline; }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
@endpush
