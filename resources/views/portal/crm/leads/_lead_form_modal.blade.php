{{-- Single reusable Create/Edit Lead modal — LeadFormModal in the PR's terms.
     JS toggles it between "create" and "edit" mode (title, submit label, form
     action/method, and which values are pre-filled) instead of two templates. --}}
<div class="modal fade" id="leadFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="leadForm" data-mode="create" data-lead-id="" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="leadFormModalTitle">Add Lead</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="leadFormGeneralError" role="alert"></div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name</label>
                        <input type="text" name="name" id="leadFormName" class="form-control" required maxlength="255">
                        <div class="invalid-feedback" data-error-for="name"></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" id="leadFormEmail" class="form-control" maxlength="255">
                            <div class="invalid-feedback" data-error-for="email"></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <div class="input-group">
                                <select name="phone_country_code" id="leadFormPhoneCountryCode" class="form-select flex-grow-0" style="max-width: 125px;" aria-label="Phone country code">
                                    <option value="+91">+91 (India)</option>
                                    <option value="+971">+971 (UAE)</option>
                                    <option value="+1">+1 (US/Canada)</option>
                                    <option value="+44">+44 (UK)</option>
                                    <option value="+61">+61 (Australia)</option>
                                    <option value="+65">+65 (Singapore)</option>
                                    <option value="+966">+966 (Saudi Arabia)</option>
                                    <option value="+974">+974 (Qatar)</option>
                                </select>
                                <input type="text" name="phone" id="leadFormPhone" class="form-control" maxlength="50" inputmode="tel" placeholder="Phone number">
                            </div>
                            <div class="invalid-feedback" data-error-for="phone"></div>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold">Message</label>
                        <textarea name="message" id="leadFormMessage" class="form-control" rows="3"></textarea>
                    </div>
                    {{-- Owner/Status only matter once a lead exists to manage — Create silently
                         defaults them (owner = whoever's adding it, status = New) and only
                         Edit exposes them for changing later. --}}
                    <div class="row g-3" id="leadFormOwnerSourceRow">
                        @if($isAdmin)
                        <div class="col-sm-6" id="leadFormOwnerCol">
                            <label class="form-label fw-semibold">Owner</label>
                            <select name="owner_id" id="leadFormOwner" class="form-select">
                                @foreach($owners as $owner)
                                <option value="{{ $owner->id }}" {{ (string) $owner->id === (string) $currentOwnerId ? 'selected' : '' }}>{{ $owner->displayName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="col-sm-6" id="leadFormSourceCol">
                            <label class="form-label fw-semibold">Source</label>
                            <select name="source_id" id="leadFormSource" class="form-select">
                                <option value="">No source</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mt-0">
                        <div class="col-sm-6" id="leadFormStageCol">
                            <label class="form-label fw-semibold">Stage</label>
                            <div class="d-flex gap-2">
                                <select name="stage_id" id="leadFormStage" class="form-select">
                                    <option value="">No stage</option>
                                </select>
                                <button type="button" id="openAddStageModal" class="btn btn-sm portal-btn-ghost text-nowrap">+ Add Stage</button>
                            </div>
                        </div>
                        <div class="col-sm-6" id="leadFormStatusCol">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" id="leadFormStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-0" id="leadFormTagsGroup">
                        <label class="form-label fw-semibold">Tags</label>
                        <div class="d-flex flex-wrap gap-2" id="leadFormTags"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-portal-primary" id="leadFormSubmitBtn">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="leadFormSpinner" role="status" aria-hidden="true"></span>
                        <span id="leadFormSubmitLabel">Create Lead</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- The defaults the Create modal starts from — same master data already
     loaded for the page's filter dropdowns, just handed to JS as data instead
     of being re-queried. Editing a lead swaps these for that lead's own
     Stage/Source/Tag options (fetched from LeadController::show). --}}
<script id="leadFormDefaults" type="application/json">
{
    "stages": @json($stages->map(fn ($stage) => ['id' => $stage->id, 'name' => $stage->name])),
    "sources": @json($sources->map(fn ($source) => ['id' => $source->id, 'name' => $source->name])),
    "tags": @json($tags->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color])),
    "currentOwnerId": {{ $currentOwnerId ?? 'null' }},
    "defaultPhoneCountryCode": "+91",
    "defaultStageId": {{ $stages->firstWhere('is_default', true)?->id ?? 'null' }}
}
</script>
