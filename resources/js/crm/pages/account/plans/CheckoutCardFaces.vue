<template>
    <div class="co-card__inner">
        <div class="co-face co-face__front">
            <div class="co-front">
                <div class="co-front__top">
                    <div class="d-flex align-items-center"><span class="co-chip"></span><i class="fas fa-wifi co-contactless"></i></div>
                    <div class="co-issuer">MW REALTY<small>{{ plan.toUpperCase() }}</small></div>
                </div>
                <div class="co-number" :class="numberState"><span>••••</span><span>••••</span><span>••••</span><span>{{ last4 || '••••' }}</span></div>
                <div class="co-front__bottom">
                    <div class="d-flex gap-4 min-w-0">
                        <div class="min-w-0">
                            <div class="co-field-label">Cardholder</div>
                            <div class="co-field-value">{{ name.trim() || 'Your name' }}</div>
                        </div>
                        <div>
                            <div class="co-field-label">Expires</div>
                            <div class="co-field-value">{{ expiry }}</div>
                        </div>
                    </div>
                    <i v-if="brandIcon" :key="brandIcon" class="fab co-brand-logo is-pop" :class="brandIcon"></i>
                    <i v-else class="fas fa-credit-card co-brand-logo" style="opacity:.35"></i>
                </div>
            </div>
        </div>
        <div class="co-face co-face__back">
            <div class="co-back">
                <div class="co-stripe"></div>
                <div class="co-sig"><span class="co-sig__strip"></span><span class="co-sig__cvc" :class="cvcState">{{ cvc }}</span></div>
                <div class="co-sig__label"><span>Authorized signature</span><span>Security code</span></div>
                <div class="co-back__foot">
                    <span>MW REALTY · SECURED BY STRIPE</span>
                    <i class="co-back-logo" :class="brandIcon ? ['fab', brandIcon] : ['fas', 'fa-credit-card']"></i>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
/** The checkout's card preview, front and back (flips on the CVC) — plans/checkout's .co-card markup. */
defineProps({
    plan: { type: String, required: true },
    name: { type: String, default: '' },
    brandIcon: { type: String, default: null },
    numberState: { type: String, default: '' }, // '' | is-typing | is-done
    last4: { type: String, default: null },
    expiry: { type: String, default: 'MM/YY' },
    cvc: { type: String, default: 'CVC' },
    cvcState: { type: String, default: '' },
});
</script>
