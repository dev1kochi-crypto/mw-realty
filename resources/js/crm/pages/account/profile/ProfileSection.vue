<template>
    <div class="dash-card pf-card">
        <div class="pf-card-head">
            <div class="min-w-0">
                <h2 class="pf-card-title">{{ section.title }}</h2>
                <p v-if="section.hint" class="pf-card-hint">{{ section.hint }}</p>
            </div>
            <div class="pf-card-tools">
                <div v-if="section.total" class="pf-progress" :title="`${section.done} of ${section.total} filled in`">
                    <span>{{ section.done }}/{{ section.total }}</span>
                    <span class="pf-progress-bar"><i :style="{ width: `${Math.round(section.done / section.total * 100)}%` }"></i></span>
                </div>
                <button type="button" class="pf-edit-btn" @click="open()"><i class="fas fa-pen"></i> Edit</button>
            </div>
        </div>

        <div v-if="!editing" class="section-view">
            <dl class="pf-list">
                <div v-for="f in section.fields" :key="f.name" class="pf-item" :class="{ 'is-wide': f.col >= 12 || f.type === 'textarea' }">
                    <dt>{{ f.label }}</dt>
                    <dd :dir="f.rtl ? 'rtl' : null" :class="{ 'is-text': f.type === 'textarea' }">
                        <template v-if="f.display !== null && f.display !== '' && f.display !== undefined">
                            <template v-if="f.type === 'multiselect'"><span v-for="chip in f.value" :key="chip" class="pf-chip">{{ chip }}</span></template>
                            <a v-else-if="f.type === 'url'" :href="f.display" target="_blank" rel="noopener">{{ f.display.replace(/^https?:\/\/(www\.)?/, '') }}</a>
                            <template v-else>{{ f.display }}</template>
                            <span v-if="f.badge" class="pf-badge" :class="f.badge[0]">{{ f.badge[1] }}</span>
                        </template>
                        <span v-else-if="f.type === 'static'" class="pf-empty">—</span>
                        <button v-else type="button" class="pf-add" @click="open(f.name)">Not added <span><i class="fas fa-plus"></i> Add</span></button>
                        <div v-if="f.note_html" class="pf-note" v-html="f.note_html"></div>
                    </dd>
                </div>
            </dl>
        </div>

        <form v-else ref="formEl" class="section-edit" @submit.prevent="save">
            <div v-if="error" class="section-form-error text-danger small mb-2">{{ error }}</div>
            <div class="row g-3">
                <template v-for="f in section.fields" :key="f.name">
                    <!-- Shown in the form too (read-only), so every row of the view is accounted for. -->
                    <div v-if="f.type === 'static'" :class="`col-md-${f.col}`">
                        <label class="form-label">{{ f.label }} <i class="fas fa-lock text-muted small ms-1"></i></label>
                        <input type="text" class="form-control form-control-sm" :value="f.display" disabled aria-readonly="true">
                        <div class="form-text">
                            {{ f.locked_help ?? 'Set by MW Realty — can\'t be changed here.' }}
                            <button v-if="f.locked_action === 'email'" type="button" class="pf-link-btn ms-0" @click="$emit('change-email')">Change it</button>
                        </div>
                    </div>
                    <div v-else :class="`col-md-${f.col}`">
                        <label class="form-label" :for="`pf-${section.key}-${f.name}`">{{ f.label }}<template v-if="f.label_hint"> <span class="text-muted small">({{ f.label_hint }})</span></template></label>
                        <textarea v-if="f.type === 'textarea'" :id="`pf-${section.key}-${f.name}`" v-model="form[f.name]" :name="f.name" class="form-control form-control-sm" rows="4" maxlength="2000" :dir="f.rtl ? 'rtl' : null" :placeholder="f.placeholder ?? ''"></textarea>
                        <select v-else-if="f.type === 'year'" :id="`pf-${section.key}-${f.name}`" v-model="form[f.name]" :name="f.name" class="form-select form-select-sm">
                            <option value="">{{ f.placeholder ?? 'Select year' }}</option>
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                        <div v-else-if="f.type === 'multiselect'" class="profile-chip-picks">
                            <label v-for="opt in f.options" :key="opt" class="profile-chip-pick"><input v-model="form[f.name]" type="checkbox" :name="`${f.name}[]`" :value="opt"><span>{{ opt }}</span></label>
                        </div>
                        <PhoneInput v-else-if="f.type === 'phone'" :id="`pf-${section.key}-${f.name}`" v-model="form.phone" v-model:country-code="form.phone_country_code" :name="f.name" class="form-control-sm" />
                        <BrokerageCombo v-else-if="f.type === 'brokerage'" v-model="form[f.name]" />
                        <input v-else :id="`pf-${section.key}-${f.name}`" v-model="form[f.name]" :name="f.name" :type="f.type === 'text' ? 'text' : f.type" class="form-control form-control-sm" :placeholder="f.placeholder ?? ''">
                        <div v-if="f.help" class="form-text">{{ f.help }}</div>
                    </div>
                </template>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-sm btn-success" :class="{ 'is-saving': state === 'saving', 'is-saved': state === 'saved' }" :disabled="!!state">
                    <template v-if="state === 'saving'"><span class="pf-spinner" aria-hidden="true"></span>Saving…</template>
                    <template v-else-if="state === 'saved'"><i class="fas fa-check"></i>Saved</template>
                    <template v-else>Save</template>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="!!state" @click="editing = false">Cancel</button>
            </div>
        </form>
    </div>
</template>

<script setup>
/**
 * One profile section (resources/views/portal/profile/_section): a read-only list plus its edit
 * form, saved with POST /profile (section = its key). Field types: text | email | url | date | year
 * | textarea | multiselect | phone | tel | brokerage | static ("static" is shown, never edited).
 */
import { nextTick, reactive, ref } from 'vue';
import http from '../../../api/http';
import PhoneInput from '../../../../components/PhoneInput.vue';
import BrokerageCombo from './BrokerageCombo.vue';

const props = defineProps({ section: { type: Object, required: true } });
const emit = defineEmits(['saved', 'change-email']);

const editing = ref(false);
const formEl = ref(null);
const form = reactive({});
const error = ref('');
const state = ref(null); // saving | saved
const years = Array.from({ length: new Date().getFullYear() - 1959 }, (_, i) => new Date().getFullYear() - i);

/** Opens the form (at `field` — "Not added · Add" / "Complete your profile"). */
function open(field = null) {
    Object.keys(form).forEach((key) => delete form[key]);
    props.section.fields.forEach((f) => {
        if (f.type === 'static') return;
        if (f.type === 'phone') {
            form.phone = f.phone_number ?? '';
            form.phone_country_code = f.phone_code ?? '+971';
        } else if (f.type === 'multiselect') {
            form[f.name] = [...(f.value ?? [])];
        } else {
            form[f.name] = f.value ?? '';
        }
    });
    error.value = '';
    editing.value = true;
    nextTick(() => {
        const target = field && formEl.value?.querySelector(`[name="${field}"], [name="${field}[]"]`);
        (target || formEl.value?.querySelector('input:not([type=hidden]):not([disabled]), select, textarea'))?.focus();
    });
}

/** Saving… (spinner) → Saved ✓ → the page reloads its data; back to Save on an error. */
function save() {
    error.value = '';
    state.value = 'saving';
    const body = { section: props.section.key, ...form };
    // Sent even when nothing is ticked, so clearing every choice saves.
    if ('spoken_languages' in form) body.spoken_languages_sent = 1;
    if ('phone' in form && !form.phone) delete body.phone_country_code;
    http.post('/profile', body)
        .then(() => {
            state.value = 'saved';
            setTimeout(() => {
                state.value = null;
                editing.value = false;
                emit('saved');
            }, 650);
        })
        .catch((e) => {
            state.value = null;
            const data = e.response?.data;
            error.value = data?.errors ? Object.values(data.errors).flat().join(' ') : (data?.message || 'Could not save changes.');
        });
}

defineExpose({ open });
</script>
