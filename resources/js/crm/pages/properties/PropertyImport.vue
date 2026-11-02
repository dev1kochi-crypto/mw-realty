<template>
    <div>
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <RouterLink :to="{ name: section.routes.index }" class="small text-decoration-none"><i class="fas fa-arrow-left me-1"></i>Back to {{ section.title }}</RouterLink>
                <div class="portal-section-title mb-0 mt-1">Import listings</div>
                <p class="text-muted mb-0" style="font-size: .85rem;">Bring your whole inventory in at once — from a spreadsheet, or straight from Property Finder.</p>
            </div>
            <a :href="templateUrl" class="btn portal-btn-ghost btn-sm"><i class="fas fa-file-arrow-down me-1"></i> Download template</a>
        </div>

        <!-- The ways to add listings -->
        <div class="limp-ways mb-3">
            <div class="intg-tile limp-way is-current" style="--tile-color: #16a34a;">
                <div class="intg-tile__top">
                    <span class="intg-tile__logo" aria-hidden="true"><i class="fas fa-file-excel"></i></span>
                    <span class="intg-status intg-status--ok"><i class="fas fa-circle-check"></i>You're here</span>
                </div>
                <div><div class="intg-tile__cat">Bulk</div><h6 class="intg-tile__name">Excel / CSV import</h6></div>
                <p class="intg-tile__desc">Your own inventory sheet or another CRM's export — up to 2,000 listings a file.</p>
            </div>
            <RouterLink :to="{ name: 'integrations.property-finder' }" class="intg-tile limp-way" style="--tile-color: #ef5e4e;">
                <div class="intg-tile__top"><span class="intg-tile__logo" aria-hidden="true"><i class="fas fa-house-chimney"></i></span></div>
                <div><div class="intg-tile__cat">Automatic</div><h6 class="intg-tile__name">Property Finder sync</h6></div>
                <p class="intg-tile__desc">Connect your account once — live listings come in, then just the new ones.</p>
                <div class="intg-tile__foot"><span>Open</span><i class="fas fa-arrow-right"></i></div>
            </RouterLink>
            <RouterLink :to="{ name: section.routes.create }" class="intg-tile limp-way" style="--tile-color: var(--portal-primary);">
                <div class="intg-tile__top"><span class="intg-tile__logo" aria-hidden="true"><i class="fas fa-plus"></i></span></div>
                <div><div class="intg-tile__cat">One at a time</div><h6 class="intg-tile__name">Add {{ section.itemLabel }}</h6></div>
                <p class="intg-tile__desc">The full listing form, with Write with AI for the description.</p>
                <div class="intg-tile__foot"><span>Open</span><i class="fas fa-arrow-right"></i></div>
            </RouterLink>
        </div>

        <div class="row g-3">
            <!-- Upload → match columns → import -->
            <div class="col-lg-8">
                <div class="portal-card p-4 h-100">
                    <ol class="limp-steps" aria-label="Import steps">
                        <li v-for="(label, i) in ['Upload file', 'Match columns', 'Import']" :key="label" :class="{ 'is-active': stepIndex === i, 'is-done': stepIndex > i }">
                            <span class="limp-steps__num"><i v-if="stepIndex > i" class="fas fa-check"></i><template v-else>{{ i + 1 }}</template></span>{{ label }}
                        </li>
                    </ol>

                    <div v-if="error" class="alert alert-danger d-flex gap-2 align-items-start mb-3" role="alert" style="font-size: 0.85rem; border-radius: 10px;">
                        <i class="fas fa-triangle-exclamation mt-1"></i><div>{{ error }}</div>
                    </div>

                    <!-- 3: progress / result -->
                    <template v-if="showProgress">
                        <PropertyImportProgress :progress="tracker.current.value" @refresh="router.push({ name: section.routes.index })" />
                        <div v-if="!working" class="mt-3"><button type="button" class="btn btn-sm portal-btn-ghost" @click="reset"><i class="fas fa-file-import me-1"></i> Import another file</button></div>
                    </template>

                    <!-- 2: column mapping -->
                    <form v-else-if="upload" @submit.prevent="start">
                        <p class="text-muted mb-3" style="font-size: 0.82rem;">
                            <strong>{{ upload.import.file }}</strong> — {{ number(upload.import.total) }} listings, {{ upload.columns.length }} columns.
                            We matched the columns we recognised; check them and pick the rest. <span class="text-danger">*</span> = needed for every listing.
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" style="font-size: 0.84rem;">
                                <thead><tr><th style="width: 34%;">MW Realty field</th><th style="width: 33%;">Your column</th><th>Sample values</th></tr></thead>
                                <tbody>
                                    <tr v-for="field in upload.fields" :key="field.key">
                                        <td>
                                            <span class="fw-semibold">{{ field.label }}</span><span v-if="field.required" class="text-danger"> *</span>
                                            <div v-if="field.hint" class="text-muted" style="font-size: 0.74rem;">{{ field.hint }}</div>
                                        </td>
                                        <td>
                                            <select v-model="mapping[field.key]" class="form-select form-select-sm" :class="{ 'is-invalid': field.required && mapping[field.key] === null && tried }" :aria-label="`Column for ${field.label}`">
                                                <option :value="null">— Not in my file —</option>
                                                <option v-for="col in upload.columns" :key="col.index" :value="col.index">{{ col.heading }}{{ usedBy(col.index, field.key) ? ` (also ${usedBy(col.index, field.key)})` : '' }}</option>
                                            </select>
                                        </td>
                                        <td class="text-muted text-truncate" style="max-width: 220px;" :title="samples(field.key)">{{ samples(field.key) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3 d-grid gap-2">
                            <div class="form-check">
                                <input id="importAi" v-model="aiDescriptions" class="form-check-input" type="checkbox" :disabled="!upload.ai_available">
                                <label class="form-check-label" for="importAi">
                                    <i class="fas fa-wand-magic-sparkles me-1" style="color: var(--portal-primary);"></i> Write a description with AI for listings that have none
                                    <span v-if="!upload.ai_available" class="text-muted small d-block">AI writing isn't set up on this site yet.</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input id="importTranslate" v-model="translate" class="form-check-input" type="checkbox" :disabled="!upload.translate_available">
                                <label class="form-check-label" for="importTranslate">
                                    <i class="fas fa-language me-1" style="color: var(--portal-primary);"></i> Translate titles, descriptions and locations into the other site languages
                                    <span v-if="!upload.translate_available" class="text-muted small d-block">Auto-translate isn't set up on this site yet — add the other languages on each listing.</span>
                                </label>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <button type="button" class="btn btn-outline-secondary px-4" @click="reset">Back</button>
                            <button type="submit" class="btn btn-portal-primary px-4" :disabled="busy">
                                <span v-if="busy" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                <i v-else class="fas fa-play me-1"></i> {{ busy ? 'Starting…' : `Import ${number(upload.import.total)} listings` }}
                            </button>
                        </div>
                    </form>

                    <!-- 1: upload -->
                    <form v-else @submit.prevent="submit">
                        <label for="crmListingImportFile" class="d-flex flex-column align-items-center justify-content-center text-center gap-2 p-4 mb-0"
                               :style="{ border: `1.5px dashed ${dragging ? 'var(--portal-primary)' : 'var(--portal-border)'}`, borderRadius: '14px', cursor: 'pointer', background: 'var(--portal-bg)', minHeight: '190px' }"
                               @dragover.prevent="dragging = true" @dragleave="dragging = false" @drop.prevent="onDrop">
                            <span class="d-flex align-items-center justify-content-center rounded-circle" style="width: 52px; height: 52px; background: var(--portal-surface); border: 1px solid var(--portal-border);">
                                <i class="fas fa-cloud-arrow-up" style="color: var(--portal-primary); font-size: 1.2rem;"></i>
                            </span>
                            <span class="fw-semibold">{{ file?.name ?? 'Drop your spreadsheet here, or click to browse' }}</span>
                            <span class="text-muted" style="font-size: 0.78rem;">.xlsx, .xls or .csv — up to 10 MB, 2,000 listings. Upload it as it is: you'll match its columns next.</span>
                        </label>
                        <input id="crmListingImportFile" ref="fileInput" type="file" class="d-none" accept=".xlsx,.xls,.csv,.tsv,.txt" @change="file = $event.target.files[0] ?? null">
                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-portal-primary px-4" :disabled="!file || busy">
                                <span v-if="busy" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                <i v-else class="fas fa-arrow-right me-1"></i> {{ busy ? 'Reading file…' : 'Next: match columns' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- What the import does with each row -->
            <div class="col-lg-4">
                <div class="portal-card p-4 h-100">
                    <div class="fw-semibold mb-3">What happens to each listing</div>
                    <ul class="limp-checks">
                        <li><i class="fas fa-table-columns"></i><span><strong>Your columns, our fields</strong> — common headings (Ref, BUA, Beds, Trakheesi…) are matched for you.</span></li>
                        <li><i class="fas fa-broom"></i><span><strong>Tidied up</strong> — SHOUTY titles, prices like 1.25M or 95k, sq.m → sq.ft, bullet lists.</span></li>
                        <li><i class="fas fa-clone"></i><span><strong>No duplicates</strong> — same permit, same reference, or same unit and price as one you already have.</span></li>
                        <li><i class="fas fa-wand-magic-sparkles"></i><span><strong>AI descriptions</strong> for rows without one, and translation into the other languages.</span></li>
                        <li><i class="fas fa-list-check"></i><span><strong>What's missing</strong> — photos, map pin, permit… listed per row in the status file.</span></li>
                        <li><i class="fas fa-shield-halved"></i><span><strong>Safe to publish</strong> — listings stay off the website until their permit is validated.</span></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- History -->
        <div class="portal-card p-0 mt-3">
            <div class="px-4 pt-3 pb-2 fw-semibold">Previous imports</div>
            <div v-if="history.length" class="table-responsive">
                <table class="table mb-0 align-middle" style="font-size: 0.84rem;">
                    <thead>
                        <tr><th class="ps-4">File</th><th>Date</th><th class="text-end">Rows</th><th class="text-end">Ready</th><th class="text-end">Need details</th><th class="text-end">Duplicates</th><th class="text-end">Skipped</th><th class="text-end">Automated</th><th class="pe-4"></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in history" :key="row.id">
                            <td class="ps-4">
                                <div class="fw-semibold text-truncate" style="max-width: 240px;" :title="row.file">{{ row.file }}</div>
                                <div class="text-muted small">{{ row.uploaded_by }}<template v-if="row.status === 'failed'"> · <span class="text-danger">Failed</span></template><template v-else-if="row.status !== 'done'"> · Importing {{ row.percent }}%</template></div>
                            </td>
                            <td class="text-nowrap">{{ date(row.created_at) }}</td>
                            <td class="text-end">{{ number(row.total) }}</td>
                            <td class="text-end"><span class="limp-stat added">{{ number(row.complete) }}</span></td>
                            <td class="text-end"><span class="limp-stat needs-info">{{ number(row.needs_info) }}</span></td>
                            <td class="text-end"><span class="limp-stat updated">{{ number(row.duplicates) }}</span></td>
                            <td class="text-end"><span class="limp-stat skipped">{{ number(row.skipped) }}</span></td>
                            <td class="text-end">{{ row.status === 'done' ? `${row.automated_percent}%` : '—' }}</td>
                            <td class="pe-4 text-end">
                                <a v-if="row.has_result" :href="`${apiBase}/properties/imports/${row.id}/result`" class="btn btn-sm portal-btn-ghost text-nowrap" title="The file with an Import Status column"><i class="fas fa-file-arrow-down me-1"></i> Status file</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else-if="historyLoaded" class="px-4 pb-4 text-muted small">No imports yet — your first one will show here, with its status file.</div>
            <div v-if="nextPage" class="text-center pb-3"><button type="button" class="btn btn-sm btn-link" @click="loadHistory(nextPage)">Show older imports</button></div>
        </div>
    </div>
</template>

<script setup>
/**
 * Properties / Commercial › Import listings — the ways to add listings in bulk (Excel / CSV here,
 * Property Finder sync, the form), the spreadsheet import itself (POST /properties/imports →
 * POST /properties/imports/{id}/start → live progress) and the account's import history.
 * Reached from Add ▾ › Import on the listing page and from the Integrations hub.
 */
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PropertyImportProgress from './components/PropertyImportProgress.vue';
import http, { errorMessage } from '../../api/http';
import { isWorking } from '../../composables/useLeadImport';
import { usePropertyImport } from '../../composables/usePropertyImport';
import { number } from '../../utils/format';
import { sectionFor } from './sections';

const route = useRoute();
const router = useRouter();
const section = computed(() => sectionFor(route.meta.segment));
const apiBase = window.CrmConfig.apiBase;
const templateUrl = `${apiBase}/properties/imports/template`;

const tracker = usePropertyImport();
const file = ref(null);
const fileInput = ref(null);
const upload = ref(null);
const mapping = reactive({});
const aiDescriptions = ref(false);
const translate = ref(false);
const busy = ref(false);
const tried = ref(false);
const error = ref('');
const dragging = ref(false);
const started = ref(false);

const working = computed(() => isWorking(tracker.current.value));
// A running import (started here or elsewhere) shows its progress instead of the upload step.
const showProgress = computed(() => tracker.current.value && (working.value || started.value));
const stepIndex = computed(() => (showProgress.value ? 2 : (upload.value ? 1 : 0)));

function reset() {
    file.value = null;
    upload.value = null;
    error.value = '';
    tried.value = false;
    started.value = false;
    if (!working.value) tracker.dismiss();
    if (fileInput.value) fileInput.value.value = '';
}

function onDrop(event) {
    dragging.value = false;
    file.value = event.dataTransfer.files[0] ?? null;
}

const columnByIndex = (index) => upload.value?.columns.find((c) => c.index === index);
const samples = (field) => (mapping[field] === null || mapping[field] === undefined ? '' : (columnByIndex(mapping[field])?.samples ?? []).join(' · '));
/** Another field already using this column (a column may feed two fields, e.g. Area → community and city). */
const usedBy = (index, self) => upload.value?.fields.find((f) => f.key !== self && mapping[f.key] === index)?.label ?? '';

function submit() {
    const body = new FormData();
    body.append('file', file.value);
    body.append('segment', section.value.segment);
    busy.value = true;
    error.value = '';
    http.post('/properties/imports', body)
        .then((res) => {
            upload.value = res.data;
            Object.keys(mapping).forEach((k) => delete mapping[k]);
            res.data.fields.forEach((f) => { mapping[f.key] = res.data.mapping[f.key] ?? null; });
            aiDescriptions.value = res.data.ai_available;
            translate.value = res.data.translate_available;
        })
        .catch((e) => {
            error.value = errorMessage(e, 'The file could not be uploaded.');
        })
        .finally(() => {
            busy.value = false;
        });
}

function start() {
    tried.value = true;
    const missing = upload.value.fields.filter((f) => f.required && mapping[f.key] === null).map((f) => f.label);
    if (missing.length) {
        error.value = `Choose the column for: ${missing.join(', ')}.`;
        return;
    }
    busy.value = true;
    error.value = '';
    http.post(`/properties/imports/${upload.value.import.id}/start`, { mapping: { ...mapping }, translate: translate.value, ai_descriptions: aiDescriptions.value })
        .then((res) => {
            started.value = true;
            tracker.track(res.data.import);
            loadHistory();
        })
        .catch((e) => {
            error.value = errorMessage(e, 'The import could not be started.');
        })
        .finally(() => {
            busy.value = false;
        });
}

/* ------------------------------------------------------------ history */

const history = ref([]);
const historyLoaded = ref(false);
const nextPage = ref(null);

function loadHistory(page = 1) {
    return http.get('/properties/imports', { params: { page } })
        .then((res) => {
            history.value = page === 1 ? res.data.data : [...history.value, ...res.data.data];
            nextPage.value = res.data.next_page;
        })
        .catch(() => {})
        .finally(() => {
            historyLoaded.value = true;
        });
}

const date = (iso) => (iso ? new Date(iso).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '');

// The finished import's numbers appear in the history as soon as it's done.
watch(() => tracker.current.value?.status, (status, before) => {
    if (before && status !== before && !working.value) loadHistory();
});

tracker.restore();
loadHistory();
onBeforeUnmount(() => tracker.stop());
</script>
