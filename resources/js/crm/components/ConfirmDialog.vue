<template>
    <Teleport to="body">
        <template v-if="state.open">
            <div class="modal d-block portal-dialog" tabindex="-1" role="alertdialog" aria-modal="true" @click.self="settle(false)" @keydown.esc="settle(false)">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <button type="button" class="btn-close portal-dialog__close" aria-label="Close" @click="settle(false)"></button>
                        <div class="modal-body">
                            <div class="portal-dialog__icon" :class="`portal-dialog__icon--${state.tone}`"><i class="fas" :class="icons[state.tone]"></i></div>
                            <h5 class="portal-dialog__title">{{ state.title }}</h5>
                            <p class="portal-dialog__text">{{ state.message }}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-portal-light" @click="settle(false)">{{ state.cancelText }}</button>
                            <button ref="okButton" type="button" class="btn portal-dialog__ok" :class="`portal-dialog__ok--${state.tone}`" @click="settle(true)">{{ state.confirmText }}</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-backdrop show"></div>
        </template>
    </Teleport>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue';
import { useConfirm } from '../composables/useConfirm';

const { state, settle } = useConfirm();
const okButton = ref(null);
const icons = { danger: 'fa-trash-alt', warning: 'fa-exclamation', primary: 'fa-question' };

watch(() => state.open, (open) => {
    if (open) nextTick(() => okButton.value?.focus());
});
</script>
