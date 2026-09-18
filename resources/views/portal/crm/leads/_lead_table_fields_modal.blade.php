<div class="modal fade" id="leadTableFieldsModal" tabindex="-1" aria-labelledby="leadTableFieldsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content portal-modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="leadTableFieldsModalTitle">Table Fields</h5>
                    <p class="text-muted small mb-0">Choose the information shown in the leads table.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="leadTableFieldsForm" data-no-spinner="true">
                <div class="modal-body">
                    <div id="leadTableFieldsError" class="alert alert-danger py-2 small d-none" role="alert"></div>
                    <div class="portal-table-fields-list">
                        @foreach($leadTableFields as $field => $meta)
                        <label class="portal-table-field-option">
                            <input
                                type="checkbox"
                                class="form-check-input"
                                name="columns[]"
                                value="{{ $field }}"
                                {{ in_array($field, $leadTableColumns, true) ? 'checked' : '' }}
                                {{ $meta['locked'] ? 'disabled' : '' }}
                            >
                            <span>{{ $meta['label'] }}</span>
                            @if($meta['locked'])
                            <small>Always shown</small>
                            @endif
                        </label>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
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
