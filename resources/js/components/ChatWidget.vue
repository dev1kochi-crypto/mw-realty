<script setup>
import { nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useChatbot } from '../composables/useChatbot';
import { useRecaptcha } from '../composables/useRecaptcha';
import { useSpeech } from '../composables/useSpeech';
import { useStaticText } from '../composables/useStaticText';
import { useCurrency } from '../composables/useCurrency';
import PhoneInput from './PhoneInput.vue';
import { contactError, responseError } from '../composables/useContactValidation';

const { messages, loading, isOpen, identified, detailsRequired, open, startChat, sendMessage, clearMessages } = useChatbot();
const { getRecaptchaToken } = useRecaptcha();
const { recognitionSupported, synthesisSupported, listening, startListening, stopListening, speak, cancelSpeech } = useSpeech();
const { t } = useStaticText();
// Prices arrive in AED (price_value) and are shown in the visitor's chosen currency.
const { selectedCurrency, toAed } = useCurrency();

const botName = window.MW_CHATBOT_NAME || 'Remi';
const draft = ref('');
const messageList = ref(null);
const enquiryFor = ref(null);
const enquiryForm = reactive({ name: '', email: '', phone: '', phone_country_code: '+971' });
const enquirySubmitting = ref(false);
const enquiryFeedback = ref(null);

function toggle() {
    isOpen.value ? (isOpen.value = false) : open();
}

// --- Details form — shown once the visitor's free first question has been answered: name, email and
// phone to keep chatting, so the conversation is saved against a real lead (Api\ChatbotController::start).
// Kept in memory to prefill property enquiries.
const detailsForm = reactive({ name: '', email: '', phone: '', phone_country_code: '+971' });
const detailsSubmitting = ref(false);
const detailsError = ref(null);

async function submitDetails() {
    if (detailsSubmitting.value) return;
    const problem = (!detailsForm.name.trim() && t('chat_widget.details_form.name_required'))
        || contactError({ ...detailsForm, emailRequired: true, phoneRequired: true });
    if (problem) {
        detailsError.value = problem;
        return;
    }
    detailsSubmitting.value = true;
    detailsError.value = null;

    try {
        const recaptcha_token = await getRecaptchaToken('ai_chatbot_start');
        await startChat({ ...detailsForm, recaptcha_token });
        scrollToBottom();
    } catch (error) {
        detailsError.value = responseError(error, t('chat_widget.enquiry_form.generic_error'));
    } finally {
        detailsSubmitting.value = false;
    }
}

// --- Dark / light mode — a plain per-visitor preference, remembered across visits, no server
// involvement at all. Applied as a modifier class on the panel itself (not <body>/<html>) so it
// only ever affects this widget, never the rest of the page.
const THEME_KEY = 'mw-chatbot-theme';
const theme = ref(localStorage.getItem(THEME_KEY) || 'light');

function toggleTheme() {
    theme.value = theme.value === 'dark' ? 'light' : 'dark';
    localStorage.setItem(THEME_KEY, theme.value);
}

// --- Voice output ("read replies aloud") — off by default; only speaks a NEW reply that arrives
// while enabled, never the whole history back-to-back (see the `messages` watcher below).
const voiceEnabled = ref(false);

function toggleVoice() {
    voiceEnabled.value = !voiceEnabled.value;
    if (!voiceEnabled.value) cancelSpeech();
}

watch(
    () => messages.value.length,
    (length, previousLength) => {
        if (!voiceEnabled.value || length <= previousLength) return;
        const last = messages.value[messages.value.length - 1];
        if (last?.role === 'model') speak(last.text);
    },
);

// --- Voice input (mic) — fills the draft box from speech instead of sending immediately, so the
// visitor can still glance at/edit the transcript before it goes out, same as typing it.
function toggleMic() {
    if (listening.value) {
        stopListening();
        return;
    }
    startListening((transcript) => { draft.value = transcript; });
}

// --- Delete/clear conversation
function clearConversation() {
    cancelSpeech();
    clearMessages();
}

// --- Filters — a quick-fill panel over the raw text box; applying one just asks Remi the
// equivalent natural-language question, so no backend/API contract changes are needed.
const showFilters = ref(false);
const filters = reactive({ purpose: '', type: '', bedrooms: '', budget: '' });

function toggleFilters() {
    showFilters.value = !showFilters.value;
}

function applyFilters() {
    const parts = ['Show me'];
    if (filters.bedrooms) parts.push(`${filters.bedrooms}-bedroom`);
    parts.push(filters.type || 'properties');
    if (filters.purpose) parts.push(`for ${filters.purpose}`);
    // The budget is typed in the visitor's currency; listings are searched in AED.
    if (filters.budget) {
        const amount = Number(String(filters.budget).replace(/[^\d.]/g, ''));
        parts.push(amount ? `under AED ${Math.round(toAed(amount))}` : `under AED ${filters.budget}`);
    }

    showFilters.value = false;
    const text = parts.join(' ') + '.';
    sendMessage(text).then(scrollToBottom);
}

onBeforeUnmount(() => {
    stopListening();
    cancelSpeech();
});

function scrollToBottom() {
    nextTick(() => {
        if (messageList.value) messageList.value.scrollTop = messageList.value.scrollHeight;
    });
}

watch([messages, isOpen, detailsRequired], scrollToBottom, { deep: true });

function submit() {
    const text = draft.value;
    draft.value = '';
    sendMessage(text).then(scrollToBottom);
}

function startEnquiry(property) {
    enquiryFor.value = property;
    enquiryForm.name = detailsForm.name;
    enquiryForm.email = detailsForm.email;
    enquiryForm.phone = detailsForm.phone;
    enquiryForm.phone_country_code = detailsForm.phone_country_code;
    enquiryFeedback.value = null;
}

async function submitEnquiry() {
    if (enquirySubmitting.value || !enquiryFor.value) return;
    const problem = (!enquiryForm.name.trim() && 'Please enter your name.')
        || (!enquiryForm.email.trim() && !enquiryForm.phone.trim() && 'Please enter your email or phone number.')
        || contactError({ ...enquiryForm, emailRequired: false });
    if (problem) {
        enquiryFeedback.value = { type: 'error', text: problem };
        return;
    }
    enquirySubmitting.value = true;
    enquiryFeedback.value = null;

    try {
        const recaptcha_token = await getRecaptchaToken('ai_chatbot_enquiry');
        const { data } = await window.axios.post('/leads/capture', {
            property_id: enquiryFor.value.id,
            name: enquiryForm.name,
            email: enquiryForm.email,
            phone: enquiryForm.phone,
            phone_country_code: enquiryForm.phone_country_code,
            message: `I'm interested in "${enquiryFor.value.name}" — please share more details.`,
            page_source: 'ai-chatbot',
            recaptcha_token,
        });
        enquiryFeedback.value = { type: 'success', text: data.message };
    } catch (error) {
        enquiryFeedback.value = { type: 'error', text: responseError(error, t('chat_widget.enquiry_form.generic_error')) };
    } finally {
        enquirySubmitting.value = false;
    }
}
</script>

<template>
    <div class="mw-chatbot">
        <button type="button" class="mw-chatbot__bubble" :class="{ 'is-idle': !isOpen }" :aria-label="isOpen ? t('chat_widget.close_aria') : t('chat_widget.open_aria')" @click="toggle">
            <!-- Animated assistant: floats, blinks, glances around, antenna pulses (all off for reduced motion). -->
            <svg v-if="!isOpen" class="mw-chatbot__bot" width="36" height="36" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <line x1="24" y1="6.5" x2="24" y2="12" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>
                <circle class="mw-chatbot__bot-antenna" cx="24" cy="5" r="3.2" fill="#fff"/>
                <rect x="4.5" y="21" width="4" height="9" rx="2" fill="#fff" opacity=".85"/>
                <rect x="39.5" y="21" width="4" height="9" rx="2" fill="#fff" opacity=".85"/>
                <rect x="8.5" y="12" width="31" height="26" rx="10" fill="#fff"/>
                <rect x="12.5" y="16.5" width="23" height="15" rx="7.5" fill="#244373"/>
                <g class="mw-chatbot__bot-eyes">
                    <ellipse class="mw-chatbot__bot-eye" cx="19" cy="23" rx="2.6" ry="3" fill="#7fe3ff"/>
                    <ellipse class="mw-chatbot__bot-eye" cx="29" cy="23" rx="2.6" ry="3" fill="#7fe3ff"/>
                </g>
                <path d="M20.5 27.6c1 .9 2.2 1.3 3.5 1.3s2.5-.4 3.5-1.3" stroke="#7fe3ff" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M17 38v4.5l5-4.5" fill="#fff"/>
            </svg>
            <span v-if="!isOpen" class="mw-chatbot__online" aria-hidden="true"></span>
            <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 6l12 12M18 6 6 18" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </button>

        <div v-if="isOpen" class="mw-chatbot__panel" :class="{ 'mw-chatbot__panel--dark': theme === 'dark' }" role="dialog" :aria-label="t('chat_widget.dialog_aria')">
            <div class="mw-chatbot__header">
                <span class="mw-chatbot__header-avatar">{{ botName.charAt(0) }}</span>
                <div>
                    <p class="mw-chatbot__header-name">{{ botName }}</p>
                    <p class="mw-chatbot__header-sub">{{ t('chat_widget.header_sub') }}</p>
                </div>

                <div class="mw-chatbot__header-actions">
                    <button type="button" class="mw-chatbot__icon-btn" :aria-label="theme === 'dark' ? t('chat_widget.theme_switch_light') : t('chat_widget.theme_switch_dark')" @click="toggleTheme">
                        <svg v-if="theme === 'dark'" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.8"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                    </button>

                    <button v-if="synthesisSupported" type="button" class="mw-chatbot__icon-btn" :aria-label="voiceEnabled ? t('chat_widget.voice_mute') : t('chat_widget.voice_read')" @click="toggleVoice">
                        <svg v-if="voiceEnabled" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9H4Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M16.5 8.5a5 5 0 0 1 0 7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9H4Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M17 9l4 6M21 9l-4 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </button>

                    <button type="button" class="mw-chatbot__icon-btn" :aria-label="t('chat_widget.clear_conversation_aria')" @click="clearConversation">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m-9 0 1 12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>

                    <button type="button" class="mw-chatbot__icon-btn" :aria-label="t('chat_widget.close_aria')" @click="isOpen = false">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </button>
                </div>
            </div>

            <div class="mw-chatbot__messages" ref="messageList">
                <template v-for="(m, i) in messages" :key="i">
                    <div class="mw-chatbot__bubble-row" :class="`mw-chatbot__bubble-row--${m.role}`">
                        <p class="mw-chatbot__text">{{ m.text }}</p>
                    </div>

                    <div v-if="m.properties && m.properties.length" class="mw-chatbot__results">
                        <article v-for="p in m.properties" :key="p.id" class="mw-chatbot__card" v-track-impression="p.id">
                            <img :src="p.image" :alt="p.name" class="mw-chatbot__card-photo">
                            <div class="mw-chatbot__card-body">
                                <h4 class="mw-chatbot__card-name">{{ p.name }}</h4>
                                <p class="mw-chatbot__card-location">
                                    <img src="/frontend/assets/images/icons/location.svg" alt="">
                                    {{ p.location }}
                                </p>
                                <p class="mw-chatbot__card-price">{{ p.price_value ? `${selectedCurrency.code} ${Math.round(p.price_value * selectedCurrency.rate).toLocaleString('en-US')}` : p.price }}</p>
                                <div class="mw-chatbot__card-stats">
                                    <span>{{ p.beds }} {{ t('chat_widget.card.bed_suffix') }}</span>
                                    <span>{{ p.baths }} {{ t('chat_widget.card.bath_suffix') }}</span>
                                    <span>{{ p.area }}</span>
                                </div>
                                <div class="mw-chatbot__card-actions">
                                    <router-link v-if="p.slug" :to="`/property-details/${p.slug}`" class="mw-chatbot__card-btn mw-chatbot__card-btn--ghost" @click="isOpen = false">{{ t('chat_widget.card.details') }}</router-link>
                                    <button type="button" class="mw-chatbot__card-btn mw-chatbot__card-btn--solid" @click="startEnquiry(p)">{{ t('chat_widget.card.enquire') }}</button>
                                </div>

                                <form v-if="enquiryFor && enquiryFor.id === p.id" class="mw-chatbot__enquiry" novalidate @submit.prevent="submitEnquiry">
                                    <p v-if="enquiryFeedback" class="mw-chatbot__enquiry-feedback" :class="`mw-chatbot__enquiry-feedback--${enquiryFeedback.type}`">{{ enquiryFeedback.text }}</p>
                                    <template v-if="enquiryFeedback?.type !== 'success'">
                                        <input v-model="enquiryForm.name" type="text" :placeholder="t('chat_widget.enquiry_form.name_placeholder')" required>
                                        <PhoneInput v-model="enquiryForm.phone" v-model:country-code="enquiryForm.phone_country_code" :placeholder="t('chat_widget.enquiry_form.phone_placeholder')" />
                                        <input v-model="enquiryForm.email" type="email" :placeholder="t('chat_widget.enquiry_form.email_placeholder')">
                                        <button type="submit" class="mw-chatbot__card-btn mw-chatbot__card-btn--solid" :disabled="enquirySubmitting">
                                            {{ enquirySubmitting ? t('chat_widget.enquiry_form.sending') : t('chat_widget.enquiry_form.submit') }}
                                        </button>
                                    </template>
                                </form>
                            </div>
                        </article>
                    </div>
                </template>

                <div v-if="loading || identified === null" class="mw-chatbot__bubble-row mw-chatbot__bubble-row--model">
                    <p class="mw-chatbot__text mw-chatbot__text--typing">
                        <span></span><span></span><span></span>
                    </p>
                </div>

                <form v-if="detailsRequired && !loading" class="mw-chatbot__details" novalidate @submit.prevent="submitDetails">
                    <p class="mw-chatbot__details-title">{{ t('chat_widget.details_form.title') }}</p>
                    <p class="mw-chatbot__details-sub">{{ t('chat_widget.details_form.subtitle') }}</p>
                    <input v-model="detailsForm.name" type="text" autocomplete="name" :placeholder="t('chat_widget.details_form.name_placeholder')" required>
                    <input v-model="detailsForm.email" type="email" autocomplete="email" :placeholder="t('chat_widget.details_form.email_placeholder')" required>
                    <PhoneInput v-model="detailsForm.phone" v-model:country-code="detailsForm.phone_country_code" :placeholder="t('chat_widget.details_form.phone_placeholder')" />
                    <p v-if="detailsError" class="mw-chatbot__enquiry-feedback mw-chatbot__enquiry-feedback--error">{{ detailsError }}</p>
                    <button type="submit" class="mw-chatbot__card-btn mw-chatbot__card-btn--solid" :disabled="detailsSubmitting">
                        {{ detailsSubmitting ? t('chat_widget.enquiry_form.sending') : t('chat_widget.details_form.submit') }}
                    </button>
                </form>
            </div>

            <div v-if="showFilters && !detailsRequired" class="mw-chatbot__filters">
                <div class="mw-chatbot__filters-row">
                    <select v-model="filters.purpose" :aria-label="t('chat_widget.filters.purpose_aria')">
                        <option value="">{{ t('chat_widget.filters.purpose_placeholder') }}</option>
                        <option value="sale">{{ t('chat_widget.filters.buy') }}</option>
                        <option value="rent">{{ t('chat_widget.filters.rent') }}</option>
                    </select>
                    <select v-model="filters.type" :aria-label="t('chat_widget.filters.type_aria')">
                        <option value="">{{ t('chat_widget.filters.type_placeholder') }}</option>
                        <option value="apartment">{{ t('chat_widget.filters.apartment') }}</option>
                        <option value="villa">{{ t('chat_widget.filters.villa') }}</option>
                        <option value="townhouse">{{ t('chat_widget.filters.townhouse') }}</option>
                        <option value="penthouse">{{ t('chat_widget.filters.penthouse') }}</option>
                    </select>
                </div>
                <div class="mw-chatbot__filters-row">
                    <select v-model="filters.bedrooms" :aria-label="t('chat_widget.filters.bedrooms_aria')">
                        <option value="">{{ t('chat_widget.filters.bedrooms_placeholder') }}</option>
                        <option value="studio">{{ t('chat_widget.filters.studio') }}</option>
                        <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
                    </select>
                    <input v-model="filters.budget" type="text" inputmode="numeric" :placeholder="t('chat_widget.filters.budget_placeholder').replace('AED', selectedCurrency.code)">
                </div>
                <button type="button" class="mw-chatbot__filters-apply" @click="applyFilters">{{ t('chat_widget.filters.apply') }}</button>
            </div>

            <form class="mw-chatbot__input-row" novalidate @submit.prevent="submit">
                <button type="button" class="mw-chatbot__icon-btn mw-chatbot__icon-btn--muted" :class="{ 'is-active': showFilters }" :aria-label="t('chat_widget.input.filters_aria')" :disabled="detailsRequired" @click="toggleFilters">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
                <button v-if="recognitionSupported" type="button" class="mw-chatbot__icon-btn mw-chatbot__icon-btn--muted" :class="{ 'is-listening': listening }" :aria-label="t('chat_widget.input.voice_aria')" :disabled="detailsRequired" @click="toggleMic">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3" stroke="currentColor" stroke-width="1.6"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                </button>
                <input v-model="draft" type="text" :placeholder="detailsRequired ? t('chat_widget.input.details_placeholder') : (listening ? t('chat_widget.input.listening_placeholder') : t('chat_widget.input.ask_placeholder'))" :disabled="loading || detailsRequired">
                <button type="submit" class="mw-chatbot__send" :aria-label="t('chat_widget.input.send_aria')" :disabled="loading || detailsRequired || !draft.trim()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="m3 11 18-8-8 18-2-8-8-2Z" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</template>

<style>
/* Details form — shown after the visitor's free first question (see submitDetails). */
.mw-chatbot__details {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 4px 0 8px;
    padding: 14px;
    background: #fff;
    border: 1px solid #e1e8ed;
    border-radius: 14px;
}
.mw-chatbot__details-title {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #1f2340;
}
.mw-chatbot__details-sub {
    margin: 0 0 4px;
    font-size: 12px;
    color: #6b7094;
}
.mw-chatbot__details > input,
.mw-chatbot__details .mw-phone-input .iti input[type='tel'] {
    box-sizing: border-box;
    width: 100%;
    height: 40px;
    padding: 0 14px;
    border: 1px solid #e1e8ed;
    border-radius: 999px;
    background: #fff;
    color: #1f2340;
    font-size: 13px;
    font-family: "Plus Jakarta Sans", sans-serif;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.mw-chatbot__details > input:focus,
.mw-chatbot__details .mw-phone-input .iti input[type='tel']:focus {
    border-color: #b9233c;
    box-shadow: 0 0 0 3px rgba(185, 35, 60, 0.1);
}
.mw-chatbot__details > input::placeholder,
.mw-chatbot__details .mw-phone-input .iti input[type='tel']::placeholder {
    color: #9aa0b8;
}
/* Phone: "🇦🇪 +971 ⌄ | number" inside the same pill as the other fields. */
.mw-chatbot__details .iti__country-container {
    padding: 0 0 0 4px;
}
.mw-chatbot__details .iti__selected-country {
    position: relative;
    height: 100%;
    background: transparent !important;
    border-radius: 999px 0 0 999px;
}
.mw-chatbot__details .iti__selected-country::after {
    content: "";
    position: absolute;
    right: 0;
    top: 10px;
    bottom: 10px;
    width: 1px;
    background: #e1e8ed;
}
.mw-chatbot__details .iti__selected-country-primary {
    padding: 0 6px 0 10px;
}
.mw-chatbot__details .mw-phone-input .iti__selected-dial-code {
    margin-left: 6px;
    font-size: 13px;
    color: #1f2340;
}
.mw-chatbot__details .mw-chatbot__card-btn {
    height: 40px;
    margin-top: 2px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
}
.mw-chatbot__icon-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.mw-chatbot__panel--dark .mw-chatbot__details {
    background: #262a33;
    border-color: #3a3f48;
}
.mw-chatbot__panel--dark .mw-chatbot__details-title,
.mw-chatbot__panel--dark .mw-chatbot__details .mw-phone-input .iti__selected-dial-code {
    color: #e8eaed;
}
.mw-chatbot__panel--dark .mw-chatbot__details-sub {
    color: #a3a8b8;
}
.mw-chatbot__panel--dark .mw-chatbot__details > input,
.mw-chatbot__panel--dark .mw-chatbot__details .mw-phone-input .iti input[type='tel'] {
    background: #1c1f26;
    border-color: #3a3f48;
    color: #e8eaed;
}
.mw-chatbot__panel--dark .mw-chatbot__details .iti__selected-country::after {
    background: #3a3f48;
}

/* Launcher — animated assistant. */
.mw-chatbot__bubble {
    position: relative;
    width: 60px;
    height: 60px;
}
.mw-chatbot__bubble.is-idle {
    animation: mwBotFloat 3.2s ease-in-out infinite;
}
.mw-chatbot__bubble.is-idle::before,
.mw-chatbot__bubble.is-idle::after {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: 50%;
    border: 2px solid rgba(185, 35, 60, 0.55);
    animation: mwBotRing 2.8s ease-out infinite;
    pointer-events: none;
}
.mw-chatbot__bubble.is-idle::after {
    border-color: rgba(36, 67, 115, 0.5);
    animation-delay: 1.4s;
}
.mw-chatbot__bot {
    overflow: visible;
    transition: transform 0.25s ease;
}
.mw-chatbot__bubble:hover .mw-chatbot__bot {
    transform: rotate(-8deg) scale(1.08);
}
.mw-chatbot__bot-eye {
    transform-box: fill-box;
    transform-origin: center;
    animation: mwBotBlink 4.5s infinite;
}
.mw-chatbot__bot-eyes {
    animation: mwBotLook 7s ease-in-out infinite;
}
.mw-chatbot__bot-antenna {
    transform-box: fill-box;
    transform-origin: center;
    animation: mwBotAntenna 1.6s ease-in-out infinite;
}
.mw-chatbot__online {
    position: absolute;
    top: 3px;
    right: 3px;
    width: 13px;
    height: 13px;
    border-radius: 50%;
    background: #22c55e;
    border: 2px solid #fff;
}
@keyframes mwBotFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}
@keyframes mwBotRing {
    0% { transform: scale(1); opacity: 0.9; }
    100% { transform: scale(1.55); opacity: 0; }
}
@keyframes mwBotBlink {
    0%, 90%, 100% { transform: scaleY(1); }
    94% { transform: scaleY(0.1); }
}
@keyframes mwBotLook {
    0%, 20%, 100% { transform: translateX(0); }
    30%, 45% { transform: translateX(-2px); }
    55%, 70% { transform: translateX(2px); }
}
@keyframes mwBotAntenna {
    0%, 100% { fill: #fff; transform: scale(1); }
    50% { fill: #7fe3ff; transform: scale(1.25); }
}
@media (prefers-reduced-motion: reduce) {
    .mw-chatbot__bubble.is-idle,
    .mw-chatbot__bot-eye,
    .mw-chatbot__bot-eyes,
    .mw-chatbot__bot-antenna {
        animation: none;
    }
    .mw-chatbot__bubble.is-idle::before,
    .mw-chatbot__bubble.is-idle::after {
        display: none;
    }
}
</style>
