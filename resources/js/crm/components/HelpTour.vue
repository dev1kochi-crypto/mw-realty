<template>
    <Teleport to="body">
        <div class="modal d-block portal-tour" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="crmHelpTitle"
             @click.self="close" @keydown.right.prevent="slidesRef?.next()" @keydown.left.prevent="slidesRef?.back()" @keydown.esc="close">
            <div class="modal-dialog modal-dialog-centered">
                <div ref="dialog" class="modal-content" tabindex="-1">
                    <button type="button" class="btn-close portal-tour__close" aria-label="Close" @click="close"></button>
                    <HelpSlides ref="slidesRef" :guide="guide" @close="close" @finish="close" />
                </div>
            </div>
        </div>
        <div class="modal-backdrop show"></div>
    </Teleport>
</template>

<script setup>
/**
 * The top bar's "!" popup — a help guide's slides (HelpSlides) in a modal. Mount it while open;
 * it emits `close`.
 */
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import HelpSlides from './HelpSlides.vue';

defineProps({ guide: { type: Object, required: true } });
const emit = defineEmits(['close']);

const dialog = ref(null);
const slidesRef = ref(null);

function close() {
    emit('close');
}

onMounted(() => {
    document.body.classList.add('modal-open');
    nextTick(() => dialog.value?.focus());
});

onBeforeUnmount(() => {
    document.body.classList.remove('modal-open');
});
</script>
