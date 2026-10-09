<template>
    <img v-if="url" :src="url" v-bind="$attrs">
    <slot v-else-if="failed" name="fallback" />
</template>

<script setup>
/**
 * An image from the CRM API that needs this session / token (private files, e.g. watermark logos) —
 * fetched as a blob and shown from an object URL. `src` is an API path (/listing-settings/…).
 */
import { onBeforeUnmount, ref, watch } from 'vue';
import http from '../api/http';

defineOptions({ inheritAttrs: false });
const props = defineProps({ src: { type: String, default: null } });

const url = ref(null);
const failed = ref(false);

function release() {
    if (url.value) URL.revokeObjectURL(url.value);
    url.value = null;
}

watch(() => props.src, (src) => {
    release();
    failed.value = false;
    if (!src) return;
    http.get(src, { responseType: 'blob' })
        .then((res) => {
            if (props.src === src) url.value = URL.createObjectURL(res.data);
        })
        .catch(() => { failed.value = true; });
}, { immediate: true });

onBeforeUnmount(release);
</script>
