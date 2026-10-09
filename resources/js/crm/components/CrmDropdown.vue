<template>
    <div ref="root" class="dropdown">
        <button ref="toggle" type="button" :class="buttonClass" :title="title" :aria-label="ariaLabel" :aria-expanded="open" :disabled="disabled" @click="flip">
            <slot name="toggle" />
        </button>
        <Teleport to="body">
            <ul v-if="open" ref="menu" class="dropdown-menu show" :style="menuStyle" @click="onMenuClick">
                <slot :close="close" />
            </ul>
        </Teleport>
    </div>
</template>

<script setup>
/**
 * A Bootstrap dropdown driven by Vue (the CRM loads no Bootstrap JS). The menu is placed on <body>
 * with fixed positioning under the toggle (right-aligned, like .dropdown-menu-end), so a card or a
 * sideways-scrolling table never clips it. Clicking an item closes it; so do Escape, scrolling and
 * a click outside.
 */
import { nextTick, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    buttonClass: { type: [String, Array, Object], default: '' },
    title: { type: String, default: null },
    ariaLabel: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    // Clicking an item closes the menu unless this is false (e.g. a menu holding inputs).
    closeOnClick: { type: Boolean, default: true },
});

const root = ref(null);
const toggle = ref(null);
const menu = ref(null);
const open = ref(false);
const menuStyle = ref({});

function place() {
    const rect = toggle.value.getBoundingClientRect();
    const width = menu.value?.offsetWidth || 160;
    const height = menu.value?.offsetHeight || 0;
    const below = rect.bottom + 2 + height <= window.innerHeight;
    menuStyle.value = {
        position: 'fixed',
        top: `${below ? rect.bottom + 2 : Math.max(8, rect.top - height - 2)}px`,
        left: `${Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8))}px`,
        zIndex: 1080,
    };
}

function onOutside(event) {
    if (root.value?.contains(event.target) || menu.value?.contains(event.target)) return;
    close();
}
function onKey(event) {
    if (event.key === 'Escape') close();
}

function listen(on) {
    const method = on ? 'addEventListener' : 'removeEventListener';
    document[method]('pointerdown', onOutside);
    document[method]('keydown', onKey);
    window[method]('scroll', close, true);
    window[method]('resize', close);
}

function flip() {
    if (open.value) {
        close();
        return;
    }
    open.value = true;
    place();
    nextTick(place); // again, now that the menu's real size is known
    listen(true);
}

function close() {
    if (!open.value) return;
    open.value = false;
    listen(false);
}

function onMenuClick(event) {
    if (props.closeOnClick && event.target.closest('.dropdown-item:not(:disabled)')) close();
}

onBeforeUnmount(() => listen(false));

defineExpose({ close });
</script>
