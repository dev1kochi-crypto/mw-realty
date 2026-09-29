{{-- Save / Cancel row + error slot shared by every inline-edit card on the lead page. --}}
<div class="alert alert-danger py-2 px-3 small mt-2 mb-0 d-none" data-form-error role="alert"></div>
<div class="d-flex justify-content-end gap-2 mt-2">
    <button type="button" class="btn btn-sm btn-outline-secondary" data-card-cancel>Cancel</button>
    <button type="submit" class="btn btn-sm btn-portal-primary">{{ $saveLabel ?? 'Save' }}</button>
</div>
