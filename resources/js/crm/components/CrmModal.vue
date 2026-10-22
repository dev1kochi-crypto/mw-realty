<template>
    <Teleport to="body">
        <template v-if="open">
            <div class="modal d-block" :class="modalClass" tabindex="-1" role="dialog" aria-modal="true" @click.self="$emit('close')">
                <div class="modal-dialog modal-dialog-centered" :class="[sizeClass, { 'modal-dialog-scrollable': scrollable }]">
                    <div class="modal-content" :class="contentClass">
                        <!-- slot "header" replaces the default title bar (close it with the slot's `close`). -->
                        <slot name="header" :close="() => $emit('close')">
                            <div class="modal-header">
                                <h5 class="modal-title fw-bold">{{ title }}</h5>
                                <button type="button" class="btn-close" aria-label="Close" @click="$emit('close')"></button>
                            </div>
                        </slot>
                        <div v-if="!bare" class="modal-body" :class="bodyClass">
                            <slot />
                        </div>
                        <slot v-else />
                        <div v-if="$slots.footer" class="modal-footer" :class="footerClass">
                            <slot name="footer" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-backdrop show"></div>
        </template>
    </Teleport>
</template>

<script setup>
/** Bootstrap-styled modal driven by Vue state (no Bootstrap JS) — `open` shows it, `close` asks to hide it. */
import { computed, nextTick, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    size: { type: String, default: '' }, // sm | lg | xl
    scrollable: { type: Boolean, default: false },
    modalClass: { type: [String, Array, Object], default: '' }, // on .modal itself, for screen CSS keyed on it
    contentClass: { type: [String, Array, Object], default: '' },
    bodyClass: { type: [String, Array, Object], default: '' },
    footerClass: { type: [String, Array, Object], default: '' },
    // true = the default slot is the whole content (no .modal-body wrapper).
    bare: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);

const sizeClass = computed(() => (props.size ? `modal-${props.size}` : ''));

function onKey(event) {
    if (event.key === 'Escape') emit('close');
}

// Body scroll lock while any CRM modal is showing (several can be mounted at once).
const syncScrollLock = () => nextTick(() => document.body.classList.toggle('modal-open', !!document.querySelector('.modal.d-block')));

watch(() => props.open, (open) => {
    syncScrollLock();
    if (open) document.addEventListener('keydown', onKey);
    else document.removeEventListener('keydown', onKey);
}, { immediate: true });

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    syncScrollLock();
});
</script>
