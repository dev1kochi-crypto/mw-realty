<template>
    <div class="portal-card p-4">
        <div class="portal-section-title">Get in touch</div>
        <ul class="list-unstyled mb-0 st-contact">
            <li v-for="item in contact" :key="item.label">
                <span class="portal-contact-icon"><i :class="item.icon"></i></span>
                <div>
                    <div class="fw-semibold">{{ item.value }}</div>
                    <div class="text-muted" style="font-size: 0.78rem;">{{ item.label }}</div>
                </div>
            </li>
        </ul>
        <p class="text-muted small mb-0 mt-3"><i class="fas fa-info-circle me-1"></i>For anything account-specific, a ticket is fastest — it keeps the whole conversation and its status in one place.</p>
    </div>
</template>

<script setup>
/** MW Realty's contact details (resources/views/portal/contact/_contact-card) — from GET /support/meta. */
defineProps({ contact: { type: Array, default: () => [] } });
</script>
