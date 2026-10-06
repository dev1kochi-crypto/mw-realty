{{-- Single reusable Create/Edit Lead modal — LeadFormModal in the PR's terms.
     JS toggles it between "create" and "edit" mode (title, subtitle, submit label, form
     action/method, and which values are pre-filled) instead of two templates.
     Styles: .lead-form-modal in public/portal/css/portal.css. --}}
<div class="modal fade" id="leadFormModal" tabindex="-1" aria-hidden="true" aria-labelledby="leadFormModalTitle">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content lead-form-modal">
            <form id="leadForm" data-mode="create" data-lead-id="" novalidate class="lead-form-modal__form">
                <div class="modal-header lead-form-modal__header">
                    <span class="lead-form-modal__icon" aria-hidden="true"><i class="fas fa-user-plus" id="leadFormModalIcon"></i></span>
                    <div class="min-w-0">
                        <h5 class="modal-title" id="leadFormModalTitle">Add Lead</h5>
                        <p class="lead-form-modal__subtitle mb-0" id="leadFormModalSubtitle">Add an enquiry you received by phone, email or in person.</p>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body lead-form-modal__body">
                    <div class="alert alert-danger d-none" id="leadFormGeneralError" role="alert"></div>

                    {{-- Contact details --}}
                    <section class="lead-form-modal__section">
                        <div class="lead-form-modal__section-title"><i class="fas fa-address-card"></i>Contact details</div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="leadFormName">Name <span class="text-danger">*</span></label>
                                <div class="lead-form-modal__field">
                                    <i class="fas fa-user" aria-hidden="true"></i>
                                    <input type="text" name="name" id="leadFormName" class="form-control" required maxlength="255" placeholder="Full name">
                                    <div class="invalid-feedback" data-error-for="name"></div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="leadFormEmail">Email</label>
                                <div class="lead-form-modal__field">
                                    <i class="fas fa-envelope" aria-hidden="true"></i>
                                    <input type="email" name="email" id="leadFormEmail" class="form-control" maxlength="255" placeholder="name@example.com">
                                    <div class="invalid-feedback" data-error-for="email"></div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="leadFormPhone">Phone</label>
                                {{-- Country-code picker + number only (7–13 digits) — portal/js/phone-input.js. --}}
                                <input type="hidden" name="phone_country_code" id="leadFormPhoneCountryCode" value="+971">
                                <input type="tel" name="phone" id="leadFormPhone" class="form-control" maxlength="20" placeholder="50 123 4567" data-phone-input data-code-input="#leadFormPhoneCountryCode">
                                <div class="invalid-feedback" data-error-for="phone"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="leadFormMessage">Message</label>
                                <textarea name="message" id="leadFormMessage" class="form-control" rows="3" placeholder="What is the lead looking for? Budget, area, timeline…"></textarea>
                            </div>
                        </div>
                    </section>

                    {{-- Lead details. Owner/Status only matter once a lead exists to manage — Create
                         silently defaults them (owner = whoever's adding it, status = New) and only
                         Edit exposes them for changing later. --}}
                    <section class="lead-form-modal__section">
                        <div class="lead-form-modal__section-title"><i class="fas fa-sliders"></i>Lead details</div>
                        <div class="row g-3" id="leadFormOwnerSourceRow">
                            @if($isAdmin)
                            <div class="col-sm-6" id="leadFormOwnerCol">
                                <label class="form-label" for="leadFormOwner">Owner</label>
                                <div class="lead-form-modal__field">
                                    <i class="fas fa-building" aria-hidden="true"></i>
                                    <select name="owner_id" id="leadFormOwner" class="form-select">
                                        @foreach($owners as $owner)
                                        <option value="{{ $owner->id }}" {{ (string) $owner->id === (string) $currentOwnerId ? 'selected' : '' }}>{{ $owner->displayName() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @endif
                            <div class="col-sm-6" id="leadFormSourceCol">
                                <label class="form-label" for="leadFormSource">Source</label>
                                <div class="lead-form-modal__field">
                                    <i class="fas fa-bullseye" aria-hidden="true"></i>
                                    <select name="source_id" id="leadFormSource" class="form-select">
                                        <option value="">Auto — set from where the lead came in</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mt-0">
                            <div class="col-sm-6" id="leadFormStageCol">
                                <label class="form-label" for="leadFormStage">Stage</label>
                                <div class="d-flex gap-2">
                                    <div class="lead-form-modal__field flex-grow-1">
                                        <i class="fas fa-layer-group" aria-hidden="true"></i>
                                        <select name="stage_id" id="leadFormStage" class="form-select">
                                            <option value="">Default stage (e.g. New)</option>
                                        </select>
                                    </div>
                                    <button type="button" id="openAddStageModal" class="btn portal-btn-ghost lead-form-modal__add-stage" title="Add a stage"><i class="fas fa-plus"></i><span class="d-none d-md-inline ms-1">Stage</span></button>
                                </div>
                            </div>
                            <div class="col-sm-6" id="leadFormStatusCol">
                                <label class="form-label" for="leadFormStatus">Status</label>
                                <div class="lead-form-modal__field">
                                    <i class="fas fa-toggle-on" aria-hidden="true"></i>
                                    <select name="status" id="leadFormStatus" class="form-select">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3" id="leadFormTagsGroup">
                            <label class="form-label">Tags</label>
                            <div class="d-flex flex-wrap gap-2" id="leadFormTags"></div>
                        </div>
                    </section>
                </div>
                <div class="modal-footer lead-form-modal__footer">
                    <span class="lead-form-modal__hint me-auto"><span class="text-danger">*</span> Required</span>
                    <button type="button" class="btn portal-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-portal-primary px-4" id="leadFormSubmitBtn">
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
    "defaultStageId": {{ $stages->firstWhere('is_default', true)?->id ?? 'null' }},
    "defaultSourceId": {{ $sources->first(fn ($source) => mb_strtolower($source->name) === mb_strtolower(\App\Models\Lead::DIRECT_SOURCE))?->id ?? 'null' }}
}
</script>
