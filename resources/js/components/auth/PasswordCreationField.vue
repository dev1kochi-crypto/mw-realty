<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePasswordValidation } from '../../composables/usePasswordValidation';
import { animatePasswordValidation, animatePasswordStrength } from '../../composables/usePasswordAnimations';
import { useStaticText } from '../../composables/useStaticText';
import PasswordField from './PasswordField.vue';

// Shared by all three Signup.vue account-type tabs (user/agent/agency) — each tab passes its
// own idPrefix so element ids stay unique, but the validation logic/markup itself is identical,
// so it lives here once instead of being copy-pasted three times.
const props = defineProps({
    idPrefix: { type: String, required: true },
    password: { type: String, default: '' },
    confirmPassword: { type: String, default: '' },
});
const emit = defineEmits(['update:password', 'update:confirmPassword']);

const { t } = useStaticText();

const passwordModel = computed({
    get: () => props.password,
    set: (v) => emit('update:password', v),
});
const confirmModel = computed({
    get: () => props.confirmPassword,
    set: (v) => emit('update:confirmPassword', v),
});

const passwordRef = computed(() => props.password);
const confirmRef = computed(() => props.confirmPassword);
const { requirements, strength, strengthPercent, passwordsMatch } = usePasswordValidation(passwordRef, confirmRef);

const PASSWORD_RULES = computed(() => [
    ['length', t('signup.rule_length')],
    ['lowercase', t('signup.rule_lowercase')],
    ['uppercase', t('signup.rule_uppercase')],
    ['number', t('signup.rule_number')],
    ['special', t('signup.rule_special')],
]);

const showPasswordHelp = ref(false);
function closeOnOutsideClick(e) {
    if (showPasswordHelp.value && !e.target.closest('.mw-help-wrap')) showPasswordHelp.value = false;
}

const STRENGTH_COLORS = { weak: '#c92844', medium: '#e08a1e', strong: '#1f9d55' };
const strengthColor = computed(() => STRENGTH_COLORS[strength.value]);
const strengthLabel = computed(() => ({ weak: t('signup.strength_weak'), medium: t('signup.strength_medium'), strong: t('signup.strength_strong') }[strength.value]));

const requirementRefs = ref({});
function setRequirementRef(el, key) {
    if (el) requirementRefs.value[key] = el;
}

watch(requirements, (current, previous) => {
    if (!previous) return;
    for (const key of Object.keys(current)) {
        if (current[key] !== previous[key]) animatePasswordValidation(requirementRefs.value[key]);
    }
});

const strengthFillEl = ref(null);
watch(strengthPercent, (percent) => animatePasswordStrength(strengthFillEl.value, percent, strengthColor.value));

onMounted(() => document.addEventListener('click', closeOnOutsideClick));
onBeforeUnmount(() => document.removeEventListener('click', closeOnOutsideClick));
</script>

<template>
    <div class="mw-login-form__field">
        <label :for="`${idPrefix}-password`">
            {{ t('signup.password_label') }}
            <span class="mw-help-wrap">
                <button
                    type="button"
                    class="mw-help-icon"
                    :aria-label="t('signup.password_requirements_aria')"
                    @click="showPasswordHelp = !showPasswordHelp"
                >?</button>
                <div v-if="showPasswordHelp" class="mw-help-popover">
                    <p class="mw-password-rules__title">{{ t('signup.password_rules_title') }}</p>
                    <p
                        v-for="rule in PASSWORD_RULES"
                        :key="rule[0]"
                        :ref="(el) => setRequirementRef(el, rule[0])"
                        class="mw-password-rules__item"
                        :class="requirements[rule[0]] ? 'is-valid' : 'is-invalid'"
                    >
                        <svg v-if="requirements[rule[0]]" width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                        {{ rule[1] }}
                    </p>
                </div>
            </span>
        </label>
        <PasswordField :id="`${idPrefix}-password`" name="password" v-model="passwordModel" :placeholder="t('signup.password_create_placeholder')" />
    </div>

    <div class="mw-login-form__field">
        <label :for="`${idPrefix}-confirm`">{{ t('signup.confirm_password_label') }}</label>
        <PasswordField :id="`${idPrefix}-confirm`" name="password_confirmation" v-model="confirmModel" :placeholder="t('signup.confirm_password_placeholder')" />
    </div>

    <div class="mw-login-form__field mw-login-form__field--full">
        <div class="mw-password-rules">
            <div class="mw-password-strength" v-if="password">
                <div class="mw-password-strength__head">
                    <span>{{ t('signup.password_strength_label') }}</span>
                    <span :style="{ color: strengthColor }">{{ strengthLabel }}</span>
                </div>
                <div class="mw-password-strength__track">
                    <div class="mw-password-strength__fill" ref="strengthFillEl"></div>
                </div>
            </div>

            <p v-if="passwordsMatch !== null" class="mw-password-rules__item" :class="passwordsMatch ? 'is-valid' : 'is-invalid'">
                <svg v-if="passwordsMatch" width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                {{ passwordsMatch ? t('signup.passwords_match') : t('signup.passwords_no_match') }}
            </p>
        </div>
    </div>
</template>
