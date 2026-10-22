<template>
    <!-- Content language switcher (drives the Description + Location language tabs and the dropdowns) and
         auto-fill: text typed in the first language is translated into the others. -->
    <div ref="bar" id="propertyLangBar" class="property-lang-bar">
        <div class="property-lang-bar__label"><i class="fas fa-language"></i> Content language</div>
        <div class="property-lang-bar__switch" role="tablist" aria-label="Content language">
            <button v-for="lang in f.state.languages" :key="lang.code" type="button" role="tab" class="property-lang-pill" :class="{ active: f.state.lang === lang.code }"
                    :aria-selected="f.state.lang === lang.code ? 'true' : 'false'" @click="f.state.lang = lang.code">
                <span class="property-lang-pill__dot" :class="dotClass(lang.code)" :title="dotTitle(lang.code)"></span>{{ lang.name }}
            </button>
        </div>
        <div class="property-lang-bar__tools">
            <div class="form-check form-switch m-0" :title="`Translate ${sourceName} text into the other languages as you type`">
                <input id="autoTranslateToggle" v-model="f.state.autoTranslate" class="form-check-input" type="checkbox" :disabled="!f.state.options.auto_translate" @change="f.state.autoTranslate && f.translate(false)">
                <label class="form-check-label small fw-semibold" for="autoTranslateToggle">Auto-fill from {{ sourceName }}</label>
            </div>
            <button type="button" class="btn btn-sm btn-portal-light" @click="translateNow"><i class="fas fa-wand-magic-sparkles me-1"></i>Translate now</button>
        </div>
        <div class="property-lang-bar__status" :class="{ 'is-error': f.state.translateStatus?.error }" aria-live="polite" v-html="f.state.translateStatus?.html || ''"></div>
    </div>
</template>

<script setup>
/** Property form › content language bar (see useListingForm: translate / langState). */
import { inject, onBeforeUnmount, onMounted, ref } from 'vue';
import { useConfirm } from '../../../composables/useConfirm';

const f = inject('listingForm');
const { confirm } = useConfirm();
const bar = ref(null);
const sourceName = f.state.languages[0]?.name ?? '';

const dotClass = (code) => ({ done: 'is-done', partial: 'is-partial' }[f.langState(code)] ?? '');
const dotTitle = (code) => ({ done: 'Complete', partial: 'Partly filled' }[f.langState(code)] ?? 'Empty');

async function translateNow() {
    const force = f.anyManualTargets() && await confirm({
        title: 'Overwrite hand-edited translations?',
        message: 'Some translated fields were edited by hand. Overwrite them with a fresh translation, or only fill the untouched fields?',
        confirmText: 'Overwrite everything',
        cancelText: 'Only fill untouched',
        tone: 'warning',
    });
    f.translate(force);
}

// Publish the bar's height (+ its gap) so the sticky section menu sits below it, not under it.
function publishHeight() {
    if (!bar.value) return;
    const sticky = getComputedStyle(bar.value).position === 'sticky';
    document.documentElement.style.setProperty('--property-lang-bar-h', sticky ? `${bar.value.offsetHeight + 8}px` : '0px');
}
let observer = null;
onMounted(() => {
    publishHeight();
    if (window.ResizeObserver) {
        observer = new ResizeObserver(publishHeight);
        observer.observe(bar.value);
    }
    window.addEventListener('resize', publishHeight);
});
onBeforeUnmount(() => {
    observer?.disconnect();
    window.removeEventListener('resize', publishHeight);
    document.documentElement.style.removeProperty('--property-lang-bar-h');
});
</script>
