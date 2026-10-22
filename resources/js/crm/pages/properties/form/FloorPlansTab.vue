<template>
    <div class="property-tab-pane-head d-flex justify-content-between align-items-start">
        <div>
            <div class="property-tab-pane-title">Floor Plans</div>
            <div class="property-tab-pane-hint">Unit-type breakdown — title, size and an image per row.</div>
        </div>
        <button type="button" class="btn btn-sm portal-btn-ghost" @click="addRow"><i class="fas fa-plus me-1"></i>Add Row</button>
    </div>
    <div>
        <!-- A brand-new property starts with a few blank rows; rows without a title are skipped on save. -->
        <div v-for="(row, i) in rows" :key="row.key" class="floor-plan-card floor-plan-row">
            <label class="floor-plan-thumb" :class="{ 'has-image': row.preview }" title="Choose floor plan image">
                <input type="file" class="floor-plan-image-input" accept="image/*" @change="pickImage(row, $event)">
                <img v-show="row.preview" :src="row.preview || ''" alt="">
                <span class="floor-plan-thumb-empty"><i class="fas fa-image"></i><span>Add image</span></span>
                <span class="floor-plan-thumb-edit" aria-hidden="true"><i class="fas fa-camera"></i></span>
            </label>

            <div class="floor-plan-fields">
                <div class="floor-plan-field floor-plan-field--title">
                    <label class="form-label small mb-1">Title</label>
                    <input :ref="(el) => { if (el) titleInputs[row.key] = el; }" v-model="row.label" type="text" class="form-control form-control-sm" placeholder="1 Bedroom Apartments">
                </div>
                <div class="floor-plan-field">
                    <label class="form-label small mb-1">Sqft From</label>
                    <input v-model="row.size_from" type="number" min="0" class="form-control form-control-sm" placeholder="0">
                </div>
                <div class="floor-plan-field">
                    <label class="form-label small mb-1">Sqft To</label>
                    <input v-model="row.size_to" type="number" min="0" class="form-control form-control-sm" placeholder="0">
                </div>
            </div>

            <button type="button" class="floor-plan-remove floor-plan-remove-btn" title="Remove row" aria-label="Remove row" @click="rows.splice(i, 1)"><i class="fas fa-trash-can"></i></button>
        </div>
    </div>
    <div v-if="!rows.length" class="floor-plan-empty text-muted small">
        <i class="fas fa-border-all me-1"></i>No floor plans yet — use <strong>Add Row</strong> to add a unit type.
    </div>

    <!-- Given to website visitors only after they fill the lead form (property page › Download Floor Plan). -->
    <div class="floor-plan-file mt-4">
        <div class="floor-plan-file-icon"><i class="fas fa-file-arrow-down"></i></div>
        <div class="floor-plan-file-body">
            <div class="fw-semibold">Downloadable Floor Plan File</div>
            <div class="small text-muted">PDF or image. Visitors download it from the property page after sharing their contact details.</div>
            <div class="floor-plan-file-meta">
                <a v-if="current" :href="current" target="_blank" rel="noopener" class="floor-plan-file-chip"><i class="fas fa-paperclip"></i>Current file ({{ f.state.property.files.floor_plan_file_ext }})</a>
                <span v-if="f.state.files.floor_plan_file" class="floor-plan-file-chip is-new"><i class="fas fa-circle-check"></i><span>{{ f.state.files.floor_plan_file.name }}</span></span>
            </div>
        </div>
        <label class="btn btn-sm portal-btn-ghost mb-0 text-nowrap">
            <i class="fas fa-upload me-1"></i>{{ current ? 'Replace' : 'Upload' }}
            <input type="file" hidden @change="f.state.files.floor_plan_file = $event.target.files[0] || null">
        </label>
    </div>
</template>

<script setup>
/** Property form › Floor Plans: rows (image + title + size range) and the downloadable floor plan file. */
import { computed, inject, nextTick } from 'vue';

const f = inject('listingForm');
const rows = f.state.floorPlans;
const titleInputs = {};
const current = computed(() => f.state.property?.files.floor_plan_file ?? null);
let next = rows.length;

function addRow() {
    const row = { key: `fp-new-${next++}`, label: '', size_from: '', size_to: '', existing_image: '', preview: null, file: null };
    rows.push(row);
    nextTick(() => titleInputs[row.key]?.focus());
}

// Show the picked image in the tile straight away.
function pickImage(row, event) {
    const file = event.target.files[0];
    if (!file) return;
    row.file = file;
    row.preview = URL.createObjectURL(file);
}
</script>
