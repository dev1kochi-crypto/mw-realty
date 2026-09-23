<script setup>
import { nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useChatbot } from '../composables/useChatbot';
import { useRecaptcha } from '../composables/useRecaptcha';
import { useSpeech } from '../composables/useSpeech';

const { messages, loading, isOpen, open, sendMessage, clearMessages } = useChatbot();
const { getRecaptchaToken } = useRecaptcha();
const { recognitionSupported, synthesisSupported, listening, startListening, stopListening, speak, cancelSpeech } = useSpeech();

const botName = window.MW_CHATBOT_NAME || 'Remi';
const draft = ref('');
const messageList = ref(null);
const enquiryFor = ref(null);
const enquiryForm = reactive({ name: '', email: '', phone: '' });
const enquirySubmitting = ref(false);
const enquiryFeedback = ref(null);

function toggle() {
    isOpen.value ? (isOpen.value = false) : open();
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
    if (filters.budget) parts.push(`under AED ${filters.budget}`);

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

watch([messages, isOpen], scrollToBottom, { deep: true });

function submit() {
    const text = draft.value;
    draft.value = '';
    sendMessage(text).then(scrollToBottom);
}

function startEnquiry(property) {
    enquiryFor.value = property;
    enquiryForm.name = '';
    enquiryForm.email = '';
    enquiryForm.phone = '';
    enquiryFeedback.value = null;
}

async function submitEnquiry() {
    if (enquirySubmitting.value || !enquiryFor.value) return;
    enquirySubmitting.value = true;
    enquiryFeedback.value = null;

    try {
        const recaptcha_token = await getRecaptchaToken('ai_chatbot_enquiry');
        const { data } = await window.axios.post('/leads/capture', {
            property_id: enquiryFor.value.id,
            name: enquiryForm.name,
            email: enquiryForm.email,
            phone: enquiryForm.phone,
            message: `I'm interested in "${enquiryFor.value.name}" — please share more details.`,
            page_source: 'ai-chatbot',
            recaptcha_token,
        });
        enquiryFeedback.value = { type: 'success', text: data.message };
    } catch (error) {
        enquiryFeedback.value = { type: 'error', text: error.response?.data?.message || 'Something went wrong — please try again.' };
    } finally {
        enquirySubmitting.value = false;
    }
}
</script>

<template>
    <div class="mw-chatbot">
        <button type="button" class="mw-chatbot__bubble" :aria-label="isOpen ? 'Close chat' : 'Open chat'" @click="toggle">
            <svg v-if="!isOpen" width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M4 5h16a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H9l-4.5 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 6l12 12M18 6 6 18" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </button>

        <div v-if="isOpen" class="mw-chatbot__panel" :class="{ 'mw-chatbot__panel--dark': theme === 'dark' }" role="dialog" aria-label="AI chat assistant">
            <div class="mw-chatbot__header">
                <span class="mw-chatbot__header-avatar">{{ botName.charAt(0) }}</span>
                <div>
                    <p class="mw-chatbot__header-name">{{ botName }}</p>
                    <p class="mw-chatbot__header-sub">MW Realty AI Assistant</p>
                </div>

                <div class="mw-chatbot__header-actions">
                    <button type="button" class="mw-chatbot__icon-btn" :aria-label="theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'" @click="toggleTheme">
                        <svg v-if="theme === 'dark'" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.8"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                    </button>

                    <button v-if="synthesisSupported" type="button" class="mw-chatbot__icon-btn" :aria-label="voiceEnabled ? 'Mute spoken replies' : 'Read replies aloud'" @click="toggleVoice">
                        <svg v-if="voiceEnabled" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9H4Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M16.5 8.5a5 5 0 0 1 0 7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9H4Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M17 9l4 6M21 9l-4 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </button>

                    <button type="button" class="mw-chatbot__icon-btn" aria-label="Clear conversation" @click="clearConversation">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m-9 0 1 12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>

                    <button type="button" class="mw-chatbot__icon-btn" aria-label="Close chat" @click="isOpen = false">
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
                        <article v-for="p in m.properties" :key="p.id" class="mw-chatbot__card">
                            <img :src="p.image" :alt="p.name" class="mw-chatbot__card-photo">
                            <div class="mw-chatbot__card-body">
                                <h4 class="mw-chatbot__card-name">{{ p.name }}</h4>
                                <p class="mw-chatbot__card-location">
                                    <img src="/frontend/assets/images/icons/location.svg" alt="">
                                    {{ p.location }}
                                </p>
                                <p class="mw-chatbot__card-price">{{ p.price }}</p>
                                <div class="mw-chatbot__card-stats">
                                    <span>{{ p.beds }} bed</span>
                                    <span>{{ p.baths }} bath</span>
                                    <span>{{ p.area }}</span>
                                </div>
                                <div class="mw-chatbot__card-actions">
                                    <router-link v-if="p.slug" :to="`/property-details/${p.slug}`" class="mw-chatbot__card-btn mw-chatbot__card-btn--ghost" @click="isOpen = false">Details</router-link>
                                    <button type="button" class="mw-chatbot__card-btn mw-chatbot__card-btn--solid" @click="startEnquiry(p)">Enquire</button>
                                </div>

                                <form v-if="enquiryFor && enquiryFor.id === p.id" class="mw-chatbot__enquiry" novalidate @submit.prevent="submitEnquiry">
                                    <p v-if="enquiryFeedback" class="mw-chatbot__enquiry-feedback" :class="`mw-chatbot__enquiry-feedback--${enquiryFeedback.type}`">{{ enquiryFeedback.text }}</p>
                                    <template v-else>
                                        <input v-model="enquiryForm.name" type="text" placeholder="Your name" required>
                                        <input v-model="enquiryForm.phone" type="tel" placeholder="Phone">
                                        <input v-model="enquiryForm.email" type="email" placeholder="Email (optional)">
                                        <button type="submit" class="mw-chatbot__card-btn mw-chatbot__card-btn--solid" :disabled="enquirySubmitting">
                                            {{ enquirySubmitting ? 'Sending…' : 'Send enquiry' }}
                                        </button>
                                    </template>
                                </form>
                            </div>
                        </article>
                    </div>
                </template>

                <div v-if="loading" class="mw-chatbot__bubble-row mw-chatbot__bubble-row--model">
                    <p class="mw-chatbot__text mw-chatbot__text--typing">
                        <span></span><span></span><span></span>
                    </p>
                </div>
            </div>

            <div v-if="showFilters" class="mw-chatbot__filters">
                <div class="mw-chatbot__filters-row">
                    <select v-model="filters.purpose" aria-label="Purpose">
                        <option value="">Buy or rent?</option>
                        <option value="sale">Buy</option>
                        <option value="rent">Rent</option>
                    </select>
                    <select v-model="filters.type" aria-label="Property type">
                        <option value="">Property type</option>
                        <option value="apartment">Apartment</option>
                        <option value="villa">Villa</option>
                        <option value="townhouse">Townhouse</option>
                        <option value="penthouse">Penthouse</option>
                    </select>
                </div>
                <div class="mw-chatbot__filters-row">
                    <select v-model="filters.bedrooms" aria-label="Bedrooms">
                        <option value="">Bedrooms</option>
                        <option value="studio">Studio</option>
                        <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
                    </select>
                    <input v-model="filters.budget" type="text" inputmode="numeric" placeholder="Max budget (AED)">
                </div>
                <button type="button" class="mw-chatbot__filters-apply" @click="applyFilters">Ask Remi</button>
            </div>

            <form class="mw-chatbot__input-row" novalidate @submit.prevent="submit">
                <button type="button" class="mw-chatbot__icon-btn mw-chatbot__icon-btn--muted" :class="{ 'is-active': showFilters }" aria-label="Search filters" @click="toggleFilters">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
                <button v-if="recognitionSupported" type="button" class="mw-chatbot__icon-btn mw-chatbot__icon-btn--muted" :class="{ 'is-listening': listening }" aria-label="Ask by voice" @click="toggleMic">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3" stroke="currentColor" stroke-width="1.6"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                </button>
                <input v-model="draft" type="text" :placeholder="listening ? 'Listening…' : 'Ask about properties or buying process…'" :disabled="loading">
                <button type="submit" class="mw-chatbot__send" aria-label="Send" :disabled="loading || !draft.trim()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="m3 11 18-8-8 18-2-8-8-2Z" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</template>
