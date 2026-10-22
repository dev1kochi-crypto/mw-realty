<template>
    <div>
        <div class="lead-tags-selected">
            <span v-for="tag in modelValue" :key="tag.id" class="lead-tags-selected__chip" :style="{ background: tag.color || '#6c757d' }">
                {{ tag.name }}
                <button type="button" :aria-label="`Remove ${tag.name}`" @click="toggle(tag)">&times;</button>
            </span>
        </div>
        <div class="input-group input-group-sm mb-2">
            <span class="input-group-text"><i class="fas fa-magnifying-glass"></i></span>
            <input v-model="search" type="search" class="form-control" placeholder="Search tags" autocomplete="off" aria-label="Search tags">
        </div>
        <div class="lead-tags-list" role="group" aria-label="Tags" @scroll="onScroll">
            <label v-for="tag in options" :key="tag.id" class="portal-lead-page-tag-choice">
                <input type="checkbox" class="form-check-input me-1" :checked="isChosen(tag)" @change="toggle(tag)">
                <span :style="{ color: tag.color }">{{ tag.name }}</span>
            </label>
            <span v-if="loading" class="spinner-border spinner-border-sm text-muted m-1"></span>
            <span v-else-if="!options.length" class="text-muted small">{{ search ? 'No tags match.' : 'No tags yet — add them under Master › Tag.' }}</span>
        </div>
    </div>
</template>

<script setup>
/**
 * Pick any number of tags — searched and paged on the server (/leads/options/tags, 20 at a time,
 * more on scroll). v-model = the chosen [{ id, name, color }], kept even when not in the loaded list.
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import http from '../api/http';

const props = defineProps({ modelValue: { type: Array, default: () => [] } });
const emit = defineEmits(['update:modelValue']);

const options = ref([]);
const nextPage = ref(1);
const loading = ref(false);
const search = ref('');
let timer = null;
let requestId = 0;

const isChosen = (tag) => props.modelValue.some((chosen) => chosen.id === tag.id);

function toggle(tag) {
    emit('update:modelValue', isChosen(tag)
        ? props.modelValue.filter((chosen) => chosen.id !== tag.id)
        : [...props.modelValue, { id: tag.id, name: tag.name, color: tag.color }]);
}

function fetchPage(reset) {
    if (reset) {
        options.value = [];
        nextPage.value = 1;
    }
    if (!nextPage.value) return;
    const id = ++requestId;
    loading.value = true;
    http.get('/leads/options/tags', { params: { search: search.value || undefined, page: nextPage.value } })
        .then((res) => {
            if (id !== requestId) return;
            options.value.push(...res.data.data);
            nextPage.value = res.data.next_page;
        })
        .finally(() => {
            if (id === requestId) loading.value = false;
        });
}

function onScroll(event) {
    const el = event.target;
    if (!loading.value && nextPage.value && el.scrollTop + el.clientHeight >= el.scrollHeight - 24) fetchPage(false);
}

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => fetchPage(true), 250);
});

onMounted(() => fetchPage(true));
onBeforeUnmount(() => clearTimeout(timer));
</script>
