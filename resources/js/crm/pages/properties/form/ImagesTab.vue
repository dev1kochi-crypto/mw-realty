<template>
    <div class="property-tab-pane-head d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <div class="property-tab-pane-title">Images</div>
            <div class="property-tab-pane-hint">Recommended: 1200x900px. Max size: 4096 KB. The first photo is used as the featured image.</div>
        </div>
        <div class="d-flex gap-2">
            <button id="galleryReorderBtn" type="button" class="btn btn-sm portal-btn-ghost" :class="{ active: reordering }" @click="reordering = !reordering">
                <template v-if="reordering"><i class="fas fa-check me-1"></i>Done</template>
                <template v-else><i class="fas fa-arrows-alt me-1"></i>Reorder</template>
            </button>
            <button type="button" class="btn btn-sm portal-btn-ghost" @click="input.click()"><i class="fas fa-folder-open me-1"></i>Select Images</button>
            <button type="button" class="btn btn-sm btn-outline-danger" @click="removeAll"><i class="fas fa-trash me-1"></i>Remove All</button>
        </div>
    </div>

    <div class="property-dropzone dz-clickable" :class="{ 'dz-drag-hover': dropHover }" @dragover.prevent="onZoneOver" @dragleave="dropHover = false" @drop.prevent="onZoneDrop">
        <div v-show="gallery.length" class="gallery-thumb-grid" :class="{ 'gallery-reorder-active': reordering }" @dragover.stop.prevent="onThumbOver" @drop.stop.prevent="onThumbDrop">
            <div v-for="(img, i) in gallery" :key="img.key" class="gallery-thumb" :class="{ 'is-dragging': dragKey === img.key }" :draggable="reordering"
                 @dragstart.stop="dragKey = img.key" @dragend.stop="dragKey = null">
                <button type="button" class="gallery-thumb-remove" @click="remove(img)"><i class="fas fa-times"></i></button>
                <span class="gallery-thumb-index">{{ i + 1 }}</span>
                <img :src="img.url" draggable="false">
                <span v-if="i === 0" class="gallery-thumb-featured">FEATURED</span>
            </div>
        </div>
        <div class="dz-message" @click="input.click()"><i class="fas fa-cloud-upload-alt me-1"></i> Drop gallery photos here, or click to browse</div>
    </div>
    <input ref="input" type="file" class="d-none" multiple accept="image/*" @change="addFiles($event.target.files); $event.target.value = ''">
    <div v-if="imageError" class="text-danger small mt-1">{{ imageError }}</div>
</template>

<script setup>
/**
 * Property form › Images. Saved photos ({reference_no}-{n}.jpeg) and newly picked ones share one
 * grid; new ones are uploaded with the form. Reorder: drag the thumbnails — saved photos' order is
 * stored right away (PropertyGalleryController), new ones upload in the shown order. The first
 * photo is the featured one.
 */
import { computed, inject, ref } from 'vue';
import http from '../../../api/http';
import { useConfirm } from '../../../composables/useConfirm';
import { useToast } from '../../../composables/useToast';

const MAX_FILES = 30;

const f = inject('listingForm');
const { confirm } = useConfirm();
const { error: toastError } = useToast();
const gallery = f.state.gallery;
const input = ref(null);
const reordering = ref(false);
const dragKey = ref(null);
const dropHover = ref(false);
const propertyId = computed(() => f.state.property?.id ?? null);
const imageError = computed(() => Object.entries(f.state.errors).find(([key]) => key.startsWith('images'))?.[1]?.[0] ?? null);
let nextKey = 0;

function addFiles(fileList) {
    const pending = gallery.filter((img) => !img.saved).length;
    [...fileList].filter((file) => file.type.startsWith('image/')).slice(0, Math.max(0, MAX_FILES - pending)).forEach((file) => {
        gallery.push({ key: `p${nextKey++}`, saved: false, file, url: URL.createObjectURL(file) });
    });
}

function onZoneOver() {
    if (!dragKey.value) dropHover.value = true;
}
function onZoneDrop(event) {
    dropHover.value = false;
    if (!dragKey.value && event.dataTransfer?.files?.length) addFiles(event.dataTransfer.files);
}

// Reorder drag stays inside the grid (a saved thumb's <img> would otherwise be re-added as a file drop).
function onThumbOver(event) {
    if (!dragKey.value) return;
    const target = event.target.closest('.gallery-thumb');
    if (!target) return;
    const thumbs = [...event.currentTarget.querySelectorAll('.gallery-thumb')];
    const to = thumbs.indexOf(target);
    const from = gallery.findIndex((img) => img.key === dragKey.value);
    if (to < 0 || from === to) return;
    const rect = target.getBoundingClientRect();
    const after = event.clientX - rect.left > rect.width / 2;
    const [moved] = gallery.splice(from, 1);
    let index = to > from ? to - 1 : to;
    if (after) index += 1;
    gallery.splice(index, 0, moved);
}
function onThumbDrop(event) {
    if (!dragKey.value) {
        if (event.dataTransfer?.files?.length) addFiles(event.dataTransfer.files);
        return;
    }
    dragKey.value = null;
    const savedOrder = gallery.filter((img) => img.saved).map((img) => img.number);
    if (propertyId.value && savedOrder.length) {
        http.post(`/properties/${propertyId.value}/images/reorder`, { order: savedOrder }).catch(() => toastError('Could not save the new photo order.'));
    }
}

async function remove(img) {
    if (!img.saved) {
        gallery.splice(gallery.indexOf(img), 1);
        return;
    }
    if (!(await confirm({ title: 'Delete This Image?', message: 'This photo will be removed immediately — it can\'t be undone.', confirmText: 'Delete', tone: 'danger' }))) return;
    http.delete(`/properties/${propertyId.value}/images/${img.number}`)
        .then(() => gallery.splice(gallery.indexOf(img), 1))
        .catch(() => toastError('Could not delete the image. Please try again.'));
}

/** Clears the new photos, and for a saved listing deletes every saved photo in one request. */
async function removeAll() {
    if (!(await confirm({ title: 'Remove All Gallery Photos?', message: 'This deletes every photo in this gallery immediately — it can\'t be undone.', confirmText: 'Remove All', tone: 'danger' }))) return;
    const clear = () => gallery.splice(0, gallery.length);
    if (propertyId.value && gallery.some((img) => img.saved)) {
        http.delete(`/properties/${propertyId.value}/images`).then(clear).catch(() => toastError('Could not remove the photos. Please try again.'));
    } else {
        clear();
    }
}
</script>
