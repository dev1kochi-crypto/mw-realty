<template>
    <div class="portal-toast-stack" aria-live="polite" aria-atomic="false">
        <div v-for="t in toasts" :key="t.id" class="portal-toast" :class="[`portal-toast-${t.type}`, { 'is-shown': t.shown, 'is-leaving': t.leaving }]"
             :role="t.type === 'danger' ? 'alert' : 'status'" :style="{ '--toast-duration': `${t.duration}ms` }">
            <i class="fas portal-toast-icon" :class="icons[t.type]" aria-hidden="true"></i>
            <div class="portal-toast-text">{{ t.message }}</div>
            <button type="button" class="portal-toast-close" aria-label="Dismiss" @click="dismiss(t.id)"><i class="fas fa-xmark"></i></button>
            <span class="portal-toast-progress"></span>
        </div>
    </div>
</template>

<script setup>
import { useToast } from '../composables/useToast';

const { toasts, dismiss } = useToast();
const icons = { success: 'fa-circle-check', danger: 'fa-circle-exclamation', info: 'fa-circle-info' };
</script>
