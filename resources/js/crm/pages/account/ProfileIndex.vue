<template>
    <div v-if="!p" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>
    <div v-else>
        <!-- Cover: banner, avatar / logo, name, login email (+ change), completeness ring, key numbers -->
        <div class="pf-cover">
            <div class="pf-cover-banner">
                <button v-if="p.kyc?.can_submit" type="button" class="pf-cover-cta" @click="openSubmit"><i class="fas fa-paper-plane me-1"></i> {{ p.kyc.is_resubmission ? 'Resubmit for Approval' : 'Submit for Approval' }}</button>
            </div>
            <div class="pf-cover-body">
                <!-- Profile photo / agency logo — shown on the public agent / agency pages. Click to change. -->
                <div class="profile-avatar-wrap pf-cover-avatar">
                    <label class="profile-avatar profile-avatar--upload" :class="avatarFill" for="avatarInput" :title="`Change ${avatarLabel}`">
                        <img v-if="a.avatar_url" :src="a.avatar_url" alt="" :class="{ 'is-logo': !isAgent }">
                        <span v-else>{{ a.initials }}</span>
                        <span class="profile-avatar__cam" aria-hidden="true"><i class="fas fa-camera"></i></span>
                        <span v-if="avatarBusy" class="profile-avatar__spin"><span class="spinner-border spinner-border-sm"></span></span>
                    </label>
                    <input id="avatarInput" ref="avatarInput" type="file" accept="image/png,image/jpeg,image/webp" class="d-none" :aria-label="`Upload ${avatarLabel}`" @change="uploadAvatar">
                    <button v-if="a.avatar_url" type="button" class="profile-avatar__remove" :title="`Remove ${avatarLabel}`" :aria-label="`Remove ${avatarLabel}`" @click="removeAvatar"><i class="fas fa-xmark"></i></button>
                </div>

                <div class="pf-cover-main">
                    <div class="pf-cover-name">
                        <h1>{{ a.display_name }}</h1>
                        <span class="pf-tag">{{ isAgent ? 'Agent' : 'Agency' }}</span>
                        <span class="pf-tag pf-status" :class="`is-${a.status}`"><i class="fas fa-circle"></i>{{ ucfirst(a.status) }}</span>
                    </div>
                    <div class="pf-cover-meta">
                        <span class="pf-login-email"><i class="fas fa-envelope"></i>{{ a.email }}
                            <button type="button" class="pf-link-btn" @click="openEmail">Change</button></span>
                        <span v-if="a.phone"><i class="fas fa-phone"></i>{{ a.phone }}</span>
                        <span><i class="fas fa-calendar"></i>Joined {{ formatDate(a.joined_at) }}</span>
                    </div>
                </div>

                <div class="pf-ring" role="img" :aria-label="`Profile ${p.completeness}% complete`">
                    <svg viewBox="0 0 36 36" aria-hidden="true"><circle class="pf-ring-bg" cx="18" cy="18" r="15.9" /><circle class="pf-ring-val" cx="18" cy="18" r="15.9" pathLength="100" :style="{ strokeDasharray: `${p.completeness} 100` }" /></svg>
                    <div class="pf-ring-text"><strong>{{ p.completeness }}%</strong><span>complete</span></div>
                </div>
            </div>
            <div class="pf-cover-stats">
                <div><strong>{{ p.stats.properties }}</strong><span>Properties</span></div>
                <div><strong>{{ p.stats.leads }}</strong><span>Leads</span></div>
                <div><strong>{{ p.stats.plan ?? 'No plan' }}</strong><span>{{ p.stats.plan_via_agency ? 'Plan · via agency' : 'Plan' }}</span></div>
                <div><strong :class="docsDone < p.documents.length ? 'is-todo' : 'is-done'">{{ docsDone }}/{{ p.documents.length }}</strong><span>Documents</span></div>
            </div>
        </div>

        <div v-if="p.kyc" class="kyc-progress">
            <div class="kyc-progress__head">
                <span class="kyc-progress__icon" :class="p.kyc.tone"><i class="fas" :class="p.kyc.icon"></i></span>
                <div class="min-w-0 flex-grow-1">
                    <div class="kyc-progress__title">{{ p.kyc.title }}</div>
                    <div class="kyc-progress__text">{{ p.kyc.text }}</div>
                </div>
            </div>
            <div class="kyc-steps">
                <div class="kyc-step" :class="p.kyc.steps[0]">
                    <div class="kyc-step__label"><i class="fas" :class="p.kyc.steps[0] === 'is-done' ? 'fa-check' : 'fa-id-card'"></i>Details &amp; documents</div>
                    <div class="kyc-step__sub">{{ p.kyc.docs_done }}/{{ p.kyc.docs_total }} documents uploaded</div>
                </div>
                <div class="kyc-step" :class="p.kyc.steps[1]">
                    <div class="kyc-step__label"><i class="fas" :class="p.kyc.steps[1] === 'is-done' ? 'fa-check' : 'fa-paper-plane'"></i>Submit for approval</div>
                    <div class="kyc-step__sub">{{ p.kyc.submitted_at ? `Sent ${formatDate(p.kyc.submitted_at)}` : 'Use the button above' }}</div>
                </div>
                <div class="kyc-step" :class="p.kyc.steps[2]">
                    <div class="kyc-step__label"><i class="fas fa-user-shield"></i>Admin review</div>
                    <div class="kyc-step__sub">{{ p.kyc.in_review ? 'In progress' : 'Usually within 1–2 working days' }}</div>
                </div>
                <div class="kyc-step">
                    <div class="kyc-step__label"><i class="fas fa-circle-check"></i>Approved</div>
                    <div class="kyc-step__sub">Leads, Reports &amp; listings unlock</div>
                </div>
            </div>
            <div v-if="p.kyc.rejection_reason" class="kyc-note tone-red"><strong><i class="fas fa-exclamation-circle me-1"></i>Reason:</strong> {{ p.kyc.rejection_reason }}</div>
            <div v-else-if="p.kyc.review_note" class="kyc-note tone-info"><strong><i class="fas fa-comment-medical me-1"></i>Requested by our team:</strong>
{{ p.kyc.review_note }}</div>
        </div>

        <div class="profile-layout">
            <nav class="profile-nav" role="tablist" aria-label="Profile sections">
                <button v-for="s in p.sections" :key="s.key" type="button" class="profile-nav-link" :class="{ active: tab === s.key }" role="tab" @click="show(s.key)">
                    <i class="fas" :class="s.icon"></i><span>{{ s.title }}</span>
                    <span v-if="s.total - s.done" class="pf-nav-state is-todo" :title="`${s.total - s.done} not filled in`">{{ s.total - s.done }} missing</span>
                    <span v-else class="pf-nav-state is-done" title="Complete"><i class="fas fa-check"></i></span>
                </button>
                <button type="button" class="profile-nav-link" :class="{ active: tab === 'documents' }" role="tab" @click="show('documents')">
                    <i class="fas fa-folder-open"></i><span>Documents</span>
                    <span v-if="docsDone < p.documents.length" class="pf-nav-state is-todo">{{ docsDone }}/{{ p.documents.length }}</span>
                    <span v-else class="pf-nav-state is-done"><i class="fas fa-check"></i></span>
                </button>
                <button type="button" class="profile-nav-link" :class="{ active: tab === 'seo' }" role="tab" @click="show('seo')">
                    <i class="fas fa-search"></i><span>SEO</span><span class="pf-nav-state">Optional</span>
                </button>
            </nav>
            <div ref="panes" class="profile-panes">
                <template v-for="s in p.sections" :key="s.key">
                    <ProfileSection v-show="tab === s.key" :ref="(el) => { sectionRefs[s.key] = el; }" :section="s" @saved="load" @change-email="openEmail" />
                </template>

                <div v-show="tab === 'seo'" class="dash-card mb-3">
                    <h6><i class="fas fa-search"></i> SEO Metadata
                        <button type="button" class="section-edit-btn" title="Edit" @click="openSeo"><i class="fas fa-pen"></i></button>
                    </h6>
                    <p class="text-muted mb-3" style="font-size: 0.8rem;">
                        Used on your public profile page. If left blank, the page falls back to your name and bio.
                    </p>
                    <div v-if="!seo.editing">
                        <div class="row">
                            <div class="col-md-6 info-row"><div class="info-label">Meta Title</div><div class="info-value">{{ p.seo.meta_title || '-' }}</div></div>
                            <div class="col-md-6 info-row"><div class="info-label">Canonical URL</div><div class="info-value">{{ p.seo.canonical_url || '-' }}</div></div>
                            <div class="col-md-12 info-row"><div class="info-label">Meta Description</div><div class="info-value">{{ p.seo.meta_description || '-' }}</div></div>
                        </div>
                    </div>
                    <form v-else class="section-edit" @submit.prevent="saveSeo">
                        <div v-if="seo.error" class="section-form-error text-danger small mb-2">{{ seo.error }}</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Meta Title</label>
                                <input v-model="seo.form.meta_title" type="text" class="form-control form-control-sm" maxlength="255" placeholder="Page title for search engines">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Canonical URL</label>
                                <input v-model="seo.form.canonical_url" type="url" class="form-control form-control-sm" maxlength="2048" :placeholder="p.seo.canonical_placeholder">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Meta Description</label>
                                <textarea v-model="seo.form.meta_description" class="form-control form-control-sm" rows="2" maxlength="500" placeholder="Page description"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Meta Keywords</label>
                                <input v-model="seo.form.meta_keywords" type="text" class="form-control form-control-sm" maxlength="500" placeholder="keyword1, keyword2">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">OG Image</label>
                                <input ref="ogInput" type="file" class="form-control form-control-sm" accept="image/*">
                                <template v-if="p.seo.og_image_url">
                                    <img :src="p.seo.og_image_url" class="mt-2 rounded" style="height:50px;" alt="">
                                    <div class="form-check mt-1">
                                        <input id="removeMetaOgImage" v-model="seo.removeImage" type="checkbox" class="form-check-input">
                                        <label class="form-check-label small" for="removeMetaOgImage">Remove image</label>
                                    </div>
                                </template>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">OG Title</label>
                                <input v-model="seo.form.og_title" type="text" class="form-control form-control-sm" maxlength="255">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">OG Description</label>
                                <textarea v-model="seo.form.og_description" class="form-control form-control-sm" rows="2" maxlength="500"></textarea>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-sm btn-success" :disabled="seo.saving">Save</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" @click="seo.editing = false">Cancel</button>
                        </div>
                    </form>
                </div>

                <div v-show="tab === 'documents'" class="dash-card">
                    <h6><i class="fas fa-folder-open"></i> My Documents</h6>
                    <div class="row g-2">
                        <div v-for="doc in p.documents" :key="doc.field" class="col-md-6">
                            <div class="doc-card" :class="{ 'is-submitted': doc.submitted }">
                                <span class="doc-icon" :class="doc.submitted ? 'fill-teal' : 'fill-slate'"><i class="fas" :class="doc.icon"></i></span>
                                <div class="flex-grow-1">
                                    <div class="doc-name">{{ doc.label }}</div>
                                    <template v-if="doc.submitted">
                                        <span class="doc-verify-badge" :class="{ verified: 'tone-verified', rejected: 'tone-rejected' }[doc.verification.status] ?? 'tone-pending'">
                                            <template v-if="doc.verification.status === 'verified'"><i class="fas fa-check-circle"></i> Verified</template>
                                            <template v-else-if="doc.verification.status === 'rejected'"><i class="fas fa-times-circle"></i> Needs Re-upload</template>
                                            <template v-else><i class="fas fa-hourglass-half"></i> Awaiting Review</template>
                                        </span>
                                        <div v-if="doc.verification.status === 'rejected' && doc.verification.note" class="doc-note">{{ doc.verification.note }}</div>
                                    </template>
                                    <span v-else class="doc-status">Not submitted</span>
                                </div>
                                <span class="doc-actions d-flex align-items-center gap-1">
                                    <template v-if="doc.submitted">
                                        <button type="button" class="btn btn-sm btn-outline-primary" @click="viewDocument(doc)">View</button>
                                        <label class="btn btn-sm btn-outline-secondary mb-0 doc-upload-label" title="Replace">
                                            <i class="fas fa-sync-alt"></i><input type="file" class="d-none" accept=".jpg,.jpeg,.png,.pdf" @change="uploadDocument(doc, $event)">
                                        </label>
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Remove" @click="removeDocument(doc)"><i class="fas fa-trash"></i></button>
                                    </template>
                                    <label v-else class="btn btn-sm btn-outline-primary mb-0 doc-upload-label">
                                        Add <input type="file" class="d-none" accept=".jpg,.jpeg,.png,.pdf" @change="uploadDocument(doc, $event)">
                                    </label>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Below every section: what's still missing (one click to fill it in) + how the public page looks. -->
                <div class="pf-extras">
                    <div class="dash-card pf-card pf-todo">
                        <div class="pf-card-head">
                            <div>
                                <h2 class="pf-card-title">{{ todoCount ? 'Complete your profile' : 'Profile complete' }}</h2>
                                <p class="pf-card-hint">{{ todoCount ? `${todoCount} thing${todoCount === 1 ? '' : 's'} left — complete profiles get more enquiries.` : 'Everything is filled in. Nice work!' }}</p>
                            </div>
                            <span class="pf-todo-pct">{{ p.completeness }}%</span>
                        </div>
                        <template v-if="todoCount">
                            <ul class="pf-todo-list">
                                <li v-for="m in p.todo.fields.slice(0, 6)" :key="`${m.section}-${m.name}`">
                                    <span class="pf-todo-icon"><i class="fas" :class="m.icon"></i></span>
                                    <span class="pf-todo-text"><strong>{{ m.label }}</strong><small>{{ m.section_title }}</small></span>
                                    <button type="button" class="pf-todo-go" @click="goTo(m.section, m.name)">Add</button>
                                </li>
                                <li v-for="d in p.todo.documents.slice(0, Math.max(0, 6 - p.todo.fields.length))" :key="d.field">
                                    <span class="pf-todo-icon"><i class="fas fa-folder-open"></i></span>
                                    <span class="pf-todo-text"><strong>{{ d.label }}</strong><small>Documents</small></span>
                                    <button type="button" class="pf-todo-go" @click="goTo('documents')">Upload</button>
                                </li>
                            </ul>
                            <div v-if="todoCount > 6" class="pf-todo-more">+ {{ todoCount - 6 }} more — see the sections marked “missing” on the left.</div>
                        </template>
                        <div v-else class="pf-todo-done"><i class="fas fa-circle-check"></i> All sections and documents are complete.</div>
                    </div>

                    <div class="dash-card pf-card pf-public">
                        <div class="pf-card-head">
                            <div>
                                <h2 class="pf-card-title">Your public page</h2>
                                <p class="pf-card-hint">{{ p.public.live ? 'How buyers see you on the website.' : 'Goes live on the website once your account is approved.' }}</p>
                            </div>
                        </div>
                        <div class="pf-public-card">
                            <div class="pf-public-top">
                                <span class="pf-public-avatar" :class="avatarFill">
                                    <img v-if="a.avatar_url" :src="a.avatar_url" alt=""><template v-else>{{ a.initials }}</template>
                                </span>
                                <div class="min-w-0">
                                    <div class="pf-public-name">{{ a.display_name }}</div>
                                    <div class="pf-public-sub">{{ p.public.subtitle }}</div>
                                </div>
                            </div>
                            <p class="pf-public-bio">{{ p.public.bio ?? 'Add a description in About to introduce yourself to buyers.' }}</p>
                            <div v-if="p.public.chips.length" class="pf-public-chips"><span v-for="chip in p.public.chips" :key="chip" class="pf-chip">{{ chip }}</span></div>
                        </div>
                        <a v-if="p.public.url && p.public.live" :href="p.public.url" target="_blank" rel="noopener" class="pf-public-link">View public page <i class="fas fa-arrow-up-right-from-square"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Change login email: a code goes to the new address first. -->
        <CrmModal :open="email.open" @close="email.open = false">
            <template #header="{ close }">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-envelope me-2"></i>Change login email</h5>
                    <button type="button" class="btn-close" aria-label="Close" @click="close"></button>
                </div>
            </template>
            <p class="small text-muted">Current: <strong>{{ a.email }}</strong></p>
            <div v-if="email.step === 1" class="email-change-step">
                <div v-if="email.error" class="alert-error-box text-danger small mb-2">{{ email.error }}</div>
                <label class="form-label">New Email Address</label>
                <div class="d-flex gap-2 flex-wrap">
                    <input ref="emailInput" v-model="email.value" type="email" class="form-control form-control-sm" placeholder="new.email@example.com" style="max-width:280px;" @keydown.enter.prevent="sendEmailCode">
                    <button type="button" class="btn btn-sm btn-primary" :disabled="email.busy" @click="sendEmailCode"><span v-if="email.busy" class="spinner-border spinner-border-sm"></span><template v-else>Send Code</template></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="email.open = false">Cancel</button>
                </div>
            </div>
            <div v-else class="email-change-step">
                <div v-if="email.error" class="alert-error-box text-danger small mb-2">{{ email.error }}</div>
                <p class="small text-muted mb-2">Enter the 4-digit code sent to <strong>{{ email.value }}</strong>. You'll be logged out and need to sign back in with your new email once verified.</p>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <input ref="codeInput" v-model="email.code" type="text" class="form-control form-control-sm" maxlength="4" inputmode="numeric" pattern="[0-9]*" placeholder="1234" style="max-width:110px; letter-spacing:0.3em; text-align:center;" @keydown.enter.prevent="verifyEmailCode">
                    <button type="button" class="btn btn-sm btn-success" :disabled="email.busy" @click="verifyEmailCode"><span v-if="email.busy" class="spinner-border spinner-border-sm"></span><template v-else>Verify &amp; Update</template></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="email.open = false">Cancel</button>
                </div>
            </div>
        </CrmModal>

        <!-- Submit / resubmit KYC for approval. -->
        <CrmModal :open="kyc.open" modal-class="kyc-submit-modal" bare @close="closeSubmit">
            <template #header><span></span></template>
            <button type="button" class="btn-close position-absolute top-0 end-0 m-3" aria-label="Close" @click="closeSubmit"></button>
            <div class="modal-body">
                <div class="kyc-submit-icon" :class="{ 'kyc-submit-icon-success': kyc.done }"><i class="fas" :class="kyc.done ? 'fa-check' : 'fa-paper-plane'"></i></div>
                <h5 class="kyc-submit-title">{{ kyc.done ? 'KYC submitted successfully' : (p.kyc?.is_resubmission ? 'Resubmit your KYC for review?' : 'Submit your KYC for approval?') }}</h5>
                <p class="kyc-submit-text" aria-live="polite">{{ kyc.done
                    ? 'Your KYC documents have been submitted. Our team will review them and get back to you soon. We’ll notify you once the review is complete.'
                    : (p.kyc?.is_resubmission
                        ? 'Your updated profile and documents will be sent to the Super Admin for another review.'
                        : 'Your profile and documents will be sent to the Super Admin for review. You can continue updating your profile after submission.') }}</p>
                <div v-if="kyc.error" class="alert alert-danger mt-3 mb-0 kyc-submit-error" role="alert">{{ kyc.error }}</div>
            </div>
            <div v-if="!kyc.done" class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" @click="closeSubmit">Cancel</button>
                <button type="button" class="btn btn-primary" :disabled="kyc.busy" @click="submitKyc">
                    <template v-if="kyc.busy"><span class="spinner-border spinner-border-sm me-1"></span> Submitting...</template>
                    <template v-else><i class="fas fa-paper-plane me-1"></i>{{ p.kyc?.is_resubmission ? 'Resubmit for Approval' : 'Submit for Approval' }}</template>
                </button>
            </div>
            <div v-else class="modal-footer">
                <button type="button" class="btn btn-primary" @click="closeSubmit">Done</button>
            </div>
        </CrmModal>
    </div>
</template>

<script setup>
/**
 * My Profile (resources/views/portal/profile + profile/_section) — sections, documents, SEO,
 * photo / logo, login email change and KYC submission. GET /api/crm/profile sends the sections
 * as data (ProfileController::sections()), so this page only renders them.
 */
import { computed, nextTick, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmModal from '../../components/CrmModal.vue';
import { useConfirm } from '../../composables/useConfirm';
import { useSession } from '../../composables/useSession';
import { useToast } from '../../composables/useToast';
import { download } from '../../utils/download';
import { formatDate, ucfirst } from '../../utils/format';
import ProfileSection from './profile/ProfileSection.vue';

const TAB_KEY = 'portal-profile-tab';

const route = useRoute();
const { confirm } = useConfirm();
const { success, error: toastError } = useToast();
const { load: reloadSession } = useSession();

const p = ref(null);
const loadError = ref('');
const tab = ref(null);
const panes = ref(null);
const sectionRefs = {};
const avatarInput = ref(null);
const avatarBusy = ref(false);
const ogInput = ref(null);
const emailInput = ref(null);
const codeInput = ref(null);
const seo = reactive({ editing: false, form: {}, removeImage: false, saving: false, error: '' });
const email = reactive({ open: false, step: 1, value: '', code: '', error: '', busy: false });
const kyc = reactive({ open: false, done: false, busy: false, error: '' });

const a = computed(() => p.value.account);
const isAgent = computed(() => a.value.type === 'agent');
const avatarFill = computed(() => (isAgent.value ? 'fill-teal' : 'fill-navy'));
const avatarLabel = computed(() => (isAgent.value ? 'profile photo' : 'logo'));
const docsDone = computed(() => p.value.documents.filter((d) => d.submitted).length);
const todoCount = computed(() => p.value.todo.fields.length + p.value.todo.documents.length);

function load() {
    loadError.value = '';
    return http.get('/profile')
        .then((res) => {
            p.value = res.data;
            if (!tab.value) {
                // The section from the URL (#documents), else the one open last time, else the first.
                let stored = null;
                try { stored = sessionStorage.getItem(TAB_KEY); } catch (e) { /* storage unavailable */ }
                const keys = [...res.data.sections.map((s) => s.key), 'documents', 'seo'];
                tab.value = [route.hash.replace('#', ''), stored].find((k) => keys.includes(k)) ?? keys[0];
            }
        })
        .catch((e) => { loadError.value = errorMessage(e); });
}

function show(key) {
    tab.value = key;
    try { sessionStorage.setItem(TAB_KEY, key); } catch (e) { /* storage unavailable */ }
}

/** "Complete your profile" → open that section and its edit form at the missing field. */
function goTo(section, field = null) {
    show(section);
    nextTick(() => {
        if (field) sectionRefs[section]?.open(field);
        panes.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}

/* ---------- Photo / logo ---------- */
function uploadAvatar() {
    const file = avatarInput.value.files[0];
    if (!file) return;
    if (file.size > 4 * 1024 * 1024) {
        toastError('Please choose an image under 4 MB.');
        avatarInput.value.value = '';
        return;
    }
    const body = new FormData();
    body.append('avatar', file);
    avatarBusy.value = true;
    http.post('/profile/avatar', body)
        .then((res) => {
            p.value.account.avatar_url = res.data.avatar_url;
            success(res.data.message);
        })
        .catch((e) => toastError(e.response?.data?.errors?.avatar?.[0] || errorMessage(e, 'Could not upload the image.')))
        .finally(() => {
            avatarBusy.value = false;
            avatarInput.value.value = '';
        });
}

async function removeAvatar() {
    if (!(await confirm({ title: 'Remove this image?', message: 'Your public profile will show a placeholder instead.', confirmText: 'Remove', tone: 'danger' }))) return;
    http.delete('/profile/avatar')
        .then(() => {
            p.value.account.avatar_url = null;
            success('Image removed.');
        })
        .catch(() => toastError('Could not remove the image.'));
}

/* ---------- SEO ---------- */
function openSeo() {
    const { meta_title, meta_description, meta_keywords, canonical_url, og_title, og_description } = p.value.seo;
    Object.assign(seo, { editing: true, removeImage: false, error: '', form: { meta_title, meta_description, meta_keywords, canonical_url, og_title, og_description } });
}

function saveSeo() {
    seo.saving = true;
    seo.error = '';
    const body = new FormData();
    body.append('section', 'seo');
    Object.entries(seo.form).forEach(([key, value]) => body.append(`metadata[${key}]`, value ?? ''));
    if (ogInput.value?.files[0]) body.append('metadata_og_image', ogInput.value.files[0]);
    if (seo.removeImage) body.append('remove_metadata_og_image', '1');
    http.post('/profile', body)
        .then(() => {
            seo.editing = false;
            return load();
        })
        .catch((e) => {
            const data = e.response?.data;
            seo.error = data?.errors ? Object.values(data.errors).flat().join(' ') : (data?.message || 'Could not save changes.');
        })
        .finally(() => { seo.saving = false; });
}

/* ---------- Documents ---------- */
function viewDocument(doc) {
    download('get', `/profile/documents/${doc.field}`).catch(() => toastError('Could not open the document.'));
}

function uploadDocument(doc, event) {
    const file = event.target.files[0];
    if (!file) return;
    const body = new FormData();
    body.append('document', file);
    http.post(`/profile/documents/${doc.field}`, body)
        .then(() => load())
        .catch((e) => {
            const data = e.response?.data;
            toastError(data?.errors ? Object.values(data.errors).flat().join(' ') : 'Could not upload document.');
        })
        .finally(() => { event.target.value = ''; });
}

async function removeDocument(doc) {
    if (!(await confirm({ title: 'Remove this document?', message: 'You can upload it again later.', confirmText: 'Remove', tone: 'danger' }))) return;
    http.delete(`/profile/documents/${doc.field}`)
        .then(() => load())
        .catch((e) => toastError(errorMessage(e)));
}

/* ---------- Login email (2 steps: a code to the new address, then verify it) ---------- */
function openEmail() {
    Object.assign(email, { open: true, step: 1, value: '', code: '', error: '', busy: false });
    nextTick(() => emailInput.value?.focus());
}

function sendEmailCode() {
    email.error = '';
    const value = email.value.trim();
    if (!value) { email.error = 'Please enter an email address.'; return; }
    email.busy = true;
    http.post('/profile/email/request', { new_email: value })
        .then(() => {
            email.value = value;
            email.step = 2;
            nextTick(() => codeInput.value?.focus());
        })
        .catch((e) => {
            const data = e.response?.data;
            email.error = data?.errors ? Object.values(data.errors).flat().join(' ') : (data?.message || 'Could not send the code.');
        })
        .finally(() => { email.busy = false; });
}

function verifyEmailCode() {
    email.error = '';
    const code = email.code.trim();
    if (!code) { email.error = 'Please enter the code.'; return; }
    email.busy = true;
    http.post('/profile/email/verify', { code })
        .then((res) => { window.location.href = res.data.redirect; })
        .catch((e) => {
            email.error = e.response?.data?.message || 'Invalid or expired code.';
            email.busy = false;
        });
}

/* ---------- KYC submission ---------- */
function openSubmit() {
    Object.assign(kyc, { open: true, done: false, busy: false, error: '' });
}

function submitKyc() {
    kyc.busy = true;
    kyc.error = '';
    http.post('/profile/submit')
        .then(() => { kyc.done = true; })
        .catch((e) => { kyc.error = e.response?.data?.message || 'Could not submit your KYC for approval.'; })
        .finally(() => { kyc.busy = false; });
}

function closeSubmit() {
    kyc.open = false;
    if (kyc.done) {
        load();
        reloadSession();
    }
}

load();
</script>
