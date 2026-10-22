<template>
    <div>
        <div class="portal-section-title mb-3">{{ title }}</div>
        <div class="portal-card p-5 text-center" style="max-width: 640px; margin: 2rem auto;">
            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center"
                 style="width: 72px; height: 72px; border-radius: 20px; background: linear-gradient(135deg, var(--portal-primary), var(--portal-accent)); color: #fff; font-size: 1.8rem;">
                <i :class="icon"></i>
            </div>
            <h4 class="fw-bold mb-2">{{ heading }}</h4>
            <p class="portal-muted mb-4">
                {{ description }}
                <template v-if="locked.agency">{{ feature }} {{ plural ? 'aren\'t' : 'isn\'t' }} included in {{ locked.agency }}'s {{ locked.plan ?? 'current' }} plan. Ask your agency to upgrade.</template>
                <template v-else>{{ feature }} {{ plural ? 'aren\'t' : 'isn\'t' }} included in your {{ locked.plan ?? 'current' }} plan.</template>
            </p>
            <a v-if="!locked.agency" href="/portal/plans" class="btn btn-portal-primary"><i class="fas fa-rocket me-2"></i>View Plans</a>
        </div>
    </div>
</template>

<script setup>
/**
 * The "Unlock …" card (same as Reports' locked state) for a section the plan doesn't include —
 * shown when the API answers 403 with `plan_locked` (EnsurePlanIncludesFeature middleware).
 * utils/planLocked.js reads it off a failed request.
 */
defineProps({
    title: { type: String, required: true }, // page heading
    heading: { type: String, required: true }, // "Unlock …"
    icon: { type: String, required: true },
    description: { type: String, required: true },
    feature: { type: String, required: true }, // "Lead insights" → "… isn't included in your Free plan."
    plural: { type: Boolean, default: false },
    locked: { type: Object, required: true }, // { plan, agency }
});
</script>
