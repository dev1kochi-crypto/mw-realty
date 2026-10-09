<template>
    <div class="property-tab-pane-head">
        <div class="property-tab-pane-title">Core details</div>
        <div class="property-tab-pane-hint">Emirate, offering and property type, location, reference and availability</div>
    </div>

    <div v-if="f.state.serverLocked.size" class="alert alert-info d-flex gap-2 align-items-start py-2 small">
        <i class="fas fa-lock mt-1"></i>
        <div>This listing's permit is {{ f.state.property?.compliance_status === 'approved' ? 'approved' : 'verified' }}, so the fields marked <i class="fas fa-lock"></i> must stay as they are on the permit.
            Need one changed? <a :href="links.contact" target="_blank">Raise a ticket</a>.</div>
    </div>

    <!--
        Advertising permit, by emirate (App\Support\PermitRules):
          Dubai              → Permit type: RERA (license + permit + Validate) · DTCM (permit, rent only) · None (DIFC/JAFZA)
          Abu Dhabi          → Broker license + ADREC permit + Validate
          Northern Emirates  → City: Al Ain → as Abu Dhabi · any other city → no permit
    -->
    <div id="permitBlock" class="permit-block">
        <div class="row g-3">
            <div class="col-12">
                <OptionSelectField ref="emirateField" field="emirate" label="Emirate" required @change="f.onPermitInputsChanged()" />
            </div>

            <!-- Northern Emirates: the city decides whether a permit is needed. -->
            <div v-if="show.northern" class="col-12">
                <label class="form-label fw-semibold" for="permitCity">City <span class="text-danger">*</span>
                    <i v-if="f.isLocked('permit_city')" class="fas fa-lock text-muted ms-1 small" title="Matches the verified permit"></i></label>
                <select id="permitCity" v-model="form.permit_city" class="form-select" :class="{ 'is-invalid': f.fieldError('permit_city') }" :disabled="f.isLocked('permit_city')" @change="f.onPermitInputsChanged()">
                    <option value="">Select an option</option>
                    <option v-for="(label, value) in permit.northern_cities" :key="value" :value="value">{{ label }}</option>
                </select>
                <div v-if="f.fieldError('permit_city')" class="invalid-feedback">{{ f.fieldError('permit_city') }}</div>
            </div>

            <!-- Dubai: which authority issued the permit. -->
            <div v-if="show.dubai" class="col-12">
                <label class="form-label fw-semibold d-block">Permit type <span class="text-danger">*</span>
                    <i class="fas fa-circle-info text-muted ms-1" tabindex="0" title="RERA: DLD (Trakheesi) advertising permit. DTCM: holiday-home permit — rent only. None: DIFC / JAFZA free zones, where no permit is issued."></i>
                    <i v-if="f.isLocked('permit_type')" class="fas fa-lock text-muted ms-1 small" title="Matches the verified permit"></i></label>
                <div class="property-segmented property-segmented--wide" role="radiogroup" aria-label="Permit type">
                    <template v-for="type in permit.dubai_types" :key="type">
                        <input :id="`permitType-${type}`" v-model="form.permit_type" type="radio" class="btn-check" :value="type" :disabled="f.isLocked('permit_type')" @change="f.onPermitInputsChanged()">
                        <label :for="`permitType-${type}`">{{ permit.types[type].label }}</label>
                    </template>
                </div>
            </div>

            <!-- License the permit is issued under (RERA: company ORN · ADREC: brokerage registration no.). -->
            <div v-if="show.licensed" class="col-12">
                <label class="form-label fw-semibold" for="permitLicense"><span>{{ f.permitInfo.value.license_label || 'License' }}</span> <span class="text-danger">*</span></label>
                <select id="permitLicense" class="form-select" disabled aria-describedby="permitLicenseHelp">
                    <option v-if="f.license.value">{{ f.license.value.name }} — {{ f.permitType.value === 'adrec' ? 'Brokerage Registration Number' : 'ORN' }}: {{ f.license.value.number }}</option>
                    <option v-else>No license on file</option>
                </select>
                <div id="permitLicenseHelp" class="form-text">{{ f.permitType.value === 'adrec' ? 'ADREC checks the broker license together with the permit number.' : 'DLD checks the permit against this RERA office registration number (ORN).' }}</div>
                <div v-if="!f.license.value" class="alert alert-warning small py-2 mt-2 mb-0">
                    <i class="fas fa-triangle-exclamation me-1"></i><span>{{ f.licenseMissing.value.text || '' }}</span>
                    <a v-if="f.licenseMissing.value.url" :href="f.licenseMissing.value.url" target="_blank" class="ms-1 fw-semibold">{{ f.licenseMissing.value.link }}</a>
                </div>
            </div>

            <!-- Permit number (+ Validate for RERA / ADREC). -->
            <div v-if="show.numbered" class="col-12">
                <label class="form-label fw-semibold" for="permitNumber"><span>{{ f.permitInfo.value.number_label || 'RERA permit number' }}</span> <span class="text-danger">*</span>
                    <i class="fas fa-circle-info text-muted ms-1" tabindex="0" title="The advertising permit number issued for this listing. One permit covers one listing only."></i>
                    <i v-if="f.isLocked('permit_number')" class="fas fa-lock text-muted ms-1 small" title="Matches the verified permit"></i></label>
                <div class="d-flex gap-2 align-items-start">
                    <input id="permitNumber" v-model="form.permit_number" type="text" class="form-control" :class="{ 'is-invalid': f.fieldError('permit_number') }" maxlength="64" placeholder="e.g. 7112345678" autocomplete="off"
                           :readonly="f.isLocked('permit_number') || f.state.permitNumberReadonly" @input="onNumberInput">
                    <button v-if="show.validates" id="permitValidate" type="button" class="btn portal-btn-ghost permit-validate-btn" :disabled="f.state.permitChecking || !f.license.value" @click="f.validatePermit()">
                        {{ f.state.permitVerified ? 'Refresh' : 'Validate' }}
                    </button>
                </div>
                <div v-if="f.fieldError('permit_number')" class="invalid-feedback d-block">{{ f.fieldError('permit_number') }}</div>

                <div v-if="show.validates" id="permitStatus" class="permit-status" :class="`is-${f.state.permitStatus.state}`" role="status" aria-live="polite">
                    <span class="permit-status-icon"><i class="fas"></i></span>
                    <div><strong>{{ f.state.permitStatus.title }}</strong><span>{{ f.state.permitStatus.text }}</span></div>
                </div>
            </div>

            <div v-if="show.numbered" class="col-md-6">
                <label class="form-label fw-semibold" for="permitExpires">Permit expiry date <span class="text-danger">*</span></label>
                <input id="permitExpires" v-model="form.permit_expires_at" type="date" class="form-control" :class="{ 'is-invalid': f.fieldError('permit_expires_at') }">
                <div v-if="f.fieldError('permit_expires_at')" class="invalid-feedback">{{ f.fieldError('permit_expires_at') }}</div>
                <div class="form-text">The listing is taken off the website automatically when the permit expires.</div>
            </div>
            <div v-if="show.qr" class="col-md-6">
                <label class="form-label fw-semibold">Permit QR code <span class="text-danger">*</span></label>
                <input type="file" class="form-control" :class="{ 'is-invalid': f.fieldError('permit_qr') }" accept="image/*" @change="f.state.files.permit_qr = $event.target.files[0] || null">
                <div v-if="f.fieldError('permit_qr')" class="invalid-feedback">{{ f.fieldError('permit_qr') }}</div>
                <div class="form-text">The QR image issued with the permit — shown on the listing page so buyers can verify the ad.</div>
                <img v-if="f.state.property?.files.permit_qr" :src="f.state.property.files.permit_qr" alt="Permit QR" class="mt-2 border rounded" style="height: 72px;">
            </div>
            <div v-if="show.numbered" class="col-12">
                <label class="form-label fw-semibold">Permit verification link</label>
                <input v-model="form.permit_verification_url" type="url" class="form-control" :class="{ 'is-invalid': f.fieldError('permit_verification_url') }" placeholder="The link the QR code opens (optional)">
                <div v-if="f.fieldError('permit_verification_url')" class="invalid-feedback">{{ f.fieldError('permit_verification_url') }}</div>
            </div>

            <div v-if="show['no-permit']" class="col-12">
                <div class="alert alert-light border small mb-0"><i class="fas fa-circle-check text-success me-1"></i>No advertising permit is needed for this listing.</div>
            </div>

            <!-- PermitRules::needsApproval: LISTING_SUPERADMIN_APPROVAL on, or a DTCM / None permit. -->
            <div v-if="show.approval" class="col-12">
                <div class="alert alert-info small mb-0"><i class="fas fa-user-shield me-1"></i>After you save, MW Realty checks and approves this listing before it shows on the website.</div>
            </div>
        </div>
    </div>
    <hr class="my-4">

    <!-- One field per row, in the order the listing portals use. -->
    <div class="row g-3 core-details-grid">
        <div class="col-12"><OptionButtonsField field="category" label="Category" required :icons="{ residential: 'fa-house', commercial: 'fa-building' }" /></div>
        <div class="col-12"><OptionButtonsField field="listing_type" label="Offering type" required :icons="{ rent: 'fa-key', sale: 'fa-tag' }" :disabled-values="f.rentOnly.value ? ['sale'] : null" /></div>
        <div v-if="f.isRent.value" class="col-12 rent-only"><OptionSelectField field="rental_period" label="Rental period" required /></div>
        <div class="col-12"><OptionSelectField ref="typeField" field="property_type" label="Property type" required /></div>
        <div class="col-12"><OptionSelectField ref="locationField" field="location" label="Property location" required placeholder="Search community / area" /></div>
        <div class="col-12"><OptionSelectField field="completion_status" label="Completion status" /></div>
        <div class="col-12">
            <label class="form-label fw-semibold">Reference</label>
            <input v-model="form.reference_no" type="text" class="form-control" placeholder="Auto-generated" readonly>
        </div>
        <div class="col-12">
            <label class="form-label fw-semibold">RERA ID</label>
            <input v-model="form.rera_id" type="text" class="form-control" placeholder="e.g. RERA70613">
        </div>
        <div class="col-12">
            <label class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
            <input id="slugInput" v-model="form.slug" name="slug" type="text" class="form-control" :class="{ 'is-invalid': f.fieldError('slug') || f.state.invalid === 'slug' }" placeholder="Auto-generated from title" @input="f.state.slugTouched = true; f.state.invalid = null">
            <div v-if="f.fieldError('slug')" class="invalid-feedback">{{ f.fieldError('slug') }}</div>
        </div>

        <!-- Available: immediately, or on one or more dates (open house / viewing days). -->
        <div class="col-12">
            <label class="form-label fw-semibold d-block">Available</label>
            <div class="property-segmented" role="radiogroup" aria-label="Available">
                <input id="availImmediately" v-model="form.availability" type="radio" class="btn-check" value="immediately">
                <label for="availImmediately">Immediately</label>
                <input id="availFromDate" v-model="form.availability" type="radio" class="btn-check" value="from_date" @change="$nextTick(() => !form.available_dates.length && dateInput?.focus())">
                <label for="availFromDate">From date</label>
            </div>
            <div v-if="form.availability === 'from_date'" class="property-dates-box">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <input ref="dateInput" type="date" class="form-control" style="max-width: 220px;" :min="todayIso" aria-label="Add an available date" @change="addDate">
                    <span class="form-text m-0">Pick a date to add it — add as many open house / viewing days as you need.</span>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <span v-for="date in form.available_dates" :key="date" class="nearby-chip">
                        {{ dateLabel(date) }}
                        <button type="button" class="nearby-chip-remove" aria-label="Remove date" @click="form.available_dates = form.available_dates.filter((d) => d !== date)"><i class="fas fa-times"></i></button>
                    </span>
                </div>
                <div v-if="f.fieldError('available_dates')" class="text-danger small mt-1">{{ f.fieldError('available_dates') }}</div>
            </div>
        </div>
    </div>

    <hr class="my-4">
    <div class="small text-muted d-flex align-items-center gap-2 mb-3">
        <i class="fas fa-circle-info"></i>
        <template v-if="f.state.options.is_admin">
            Emirate, offering type, rental period, property type, completion status, furnishing, amenities, easy access and attributes are managed in
            <RouterLink :to="{ name: 'master.property-options' }">Master › Property Options</RouterLink>.
        </template>
        <template v-else>
            Need an option that isn't listed?
            <a :href="links.contact" target="_blank">Raise a ticket</a> and our team will add it.
        </template>
    </div>

    <div class="form-check form-switch">
        <input id="propertyStatus" v-model="form.status" class="form-check-input" type="checkbox">
        <label class="form-check-label fw-semibold" for="propertyStatus">Active (visible on site)</label>
    </div>
    <div v-if="!f.state.isEdit || !f.state.property?.can_go_live" class="form-text">Goes live only after MW Realty approves the listing's permit.</div>
</template>

<script setup>
/** Property form › Core details: the advertising permit (_permit), category, offering, type, location, reference, slug, availability. */
import { computed, inject, ref } from 'vue';
import OptionButtonsField from './OptionButtonsField.vue';
import OptionSelectField from './OptionSelectField.vue';

const f = inject('listingForm');
const form = f.form;
const permit = f.state.options.permit;
const links = f.state.options.links;
const show = computed(() => f.permitShow.value);
const dateInput = ref(null);
const emirateField = ref(null);
const typeField = ref(null);
const locationField = ref(null);
const todayIso = new Date().toLocaleDateString('en-CA');

function onNumberInput() {
    f.state.errors = { ...f.state.errors, permit_number: undefined };
    f.resetVerification();
}

const dateLabel = (value) => new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });

/** Each picked date becomes a chip, kept in date order. */
function addDate(event) {
    const value = event.target.value;
    event.target.value = '';
    if (!value || form.available_dates.includes(value)) return;
    form.available_dates = [...form.available_dates, value].sort();
}

/** The required-field check opens the picker that's still empty. */
defineExpose({
    openSelect(name) {
        ({ emirate: emirateField, property_type: typeField, location: locationField })[name]?.value?.open();
    },
});
</script>
