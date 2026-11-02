<template>
    <Transition name="mw-compare-tray">
        <aside v-if="visible" class="mw-compare-tray" :aria-label="t('compare.tray_aria', 'Properties to compare')">
            <div class="mw-compare-tray__inner">
                <ul class="mw-compare-tray__list">
                    <li v-for="item in items" :key="item.slug" class="mw-compare-tray__item">
                        <img v-if="item.image" :src="item.image" alt="" class="mw-compare-tray__thumb">
                        <span class="mw-compare-tray__name">{{ item.name }}</span>
                        <button type="button" class="mw-compare-tray__remove" :aria-label="`${t('compare.remove', 'Remove')} ${item.name}`" @click="remove(item.slug)">&times;</button>
                    </li>
                    <li v-for="n in COMPARE_MAX - items.length" :key="`empty-${n}`" class="mw-compare-tray__item mw-compare-tray__item--empty" aria-hidden="true"></li>
                </ul>
                <div class="mw-compare-tray__actions">
                    <p class="mw-compare-tray__hint" :class="{ 'is-warn': full }" aria-live="polite">
                        <template v-if="full">{{ t('compare.full', 'You can compare up to 5 properties — remove one to add another.') }}</template>
                        <template v-else-if="items.length < COMPARE_MIN">{{ t('compare.pick_more', 'Pick one more property to compare.') }}</template>
                        <template v-else>{{ items.length }} / {{ COMPARE_MAX }} {{ t('compare.selected', 'selected') }}</template>
                    </p>
                    <button type="button" class="mw-compare-tray__clear" @click="clear">{{ t('compare.clear', 'Clear') }}</button>
                    <router-link v-if="items.length >= COMPARE_MIN" :to="compareUrl" class="mw-compare-tray__go">{{ t('compare.compare_now', 'Compare now') }}</router-link>
                    <span v-else class="mw-compare-tray__go is-disabled" aria-disabled="true">{{ t('compare.compare_now', 'Compare now') }}</span>
                </div>
            </div>
        </aside>
    </Transition>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { COMPARE_MAX, COMPARE_MIN, useCompare } from '../../composables/useCompare';
import { useStaticText } from '../../composables/useStaticText';
import './compare.css';

/** Floating bar listing the picked properties, on every page except /compare itself. */
const route = useRoute();
const { t } = useStaticText();
const { items, full, remove, clear, compareUrl } = useCompare();
const visible = computed(() => items.value.length > 0 && route.name !== 'compare');
</script>
