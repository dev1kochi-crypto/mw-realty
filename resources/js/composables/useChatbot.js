import { ref } from 'vue';
import { useStaticText } from './useStaticText';

// Module-level (not per-component ref()) so the conversation and open/closed state survive SPA
// route navigation. The conversation itself lives on the server (Api\ChatbotController): a visitor
// asks their first question straight away, then gives their name / email / phone to keep going
// (startChat) — every message is saved, and reopening the widget (even after a reload) picks the
// latest conversation back up.
const messages = ref([]);
const loading = ref(false);
const isOpen = ref(false);
// null = not asked the server yet.
const identified = ref(null);
// The free question is used up — the details form shows and sending waits for it.
const detailsRequired = ref(false);
const conversationId = ref(null);

// Singleton, called once for this module (same pattern as the refs above), not per-usage —
// avoids re-registering a language-change watcher every time welcomeText()/sendMessage() runs.
const { t } = useStaticText();

// Bumped by clearMessages() so a reply still in flight when the visitor resets
// doesn't land in the fresh conversation a moment later — sendMessage() captures the generation
// it was sent under and only applies its result if nothing has reset the chat since.
let generation = 0;
let sessionRequest = null;

function welcomeText() {
    const name = window.MW_CHATBOT_NAME || 'Remi';
    return t('chat_widget.welcome_message').replace('{name}', name);
}

function freshConversation() {
    messages.value = [{ role: 'model', text: welcomeText() }];
}

/** GET /chatbot/session — who this browser is and the conversation to resume (once per page load). */
function loadSession() {
    sessionRequest ??= window.axios.get('/chatbot/session')
        .then(({ data }) => {
            identified.value = !!data.identified;
            detailsRequired.value = !!data.details_required;
            conversationId.value = data.conversation_id || null;
            freshConversation();
            (data.messages || []).forEach((m) => messages.value.push({ role: m.role, text: m.text, properties: m.properties || [] }));
        })
        .catch(() => {
            identified.value = false;
            freshConversation();
        })
        .finally(() => { sessionRequest = null; });

    return sessionRequest;
}

function open() {
    isOpen.value = true;
    if (identified.value === null) {
        loadSession();
    } else if (messages.value.length === 0) {
        freshConversation();
    }
}

/** The details form — carries on the same conversation. Throws the axios error for the widget to show. */
function startChat(details) {
    return window.axios.post('/chatbot/start', { ...details, conversation_id: conversationId.value }).then(({ data }) => {
        identified.value = true;
        detailsRequired.value = false;
        conversationId.value = data.conversation_id;
        const thanks = t('chat_widget.details_form.thanks', 'Thanks, {name}! What else would you like to know?');
        messages.value.push({ role: 'model', text: thanks.replace('{name}', String(data.name || '').split(' ')[0]) });
        return data;
    });
}

/** "Delete" button in the widget header — the saved transcript is kept; a new conversation starts. */
function clearMessages() {
    generation += 1;
    loading.value = false;
    freshConversation();
    conversationId.value = null;
    if (!identified.value) return;
    window.axios.post('/chatbot/reset')
        .then(({ data }) => { conversationId.value = data.conversation_id; })
        .catch(() => {});
}


function sendMessage(text) {
    const trimmed = text.trim();
    if (!trimmed || loading.value || detailsRequired.value) return Promise.resolve();

    const requestGeneration = generation;
    messages.value.push({ role: 'user', text: trimmed });
    loading.value = true;

    return window.axios.post('/chatbot/message', { message: trimmed, conversation_id: conversationId.value })
        .then(({ data }) => {
            if (requestGeneration !== generation) return;
            conversationId.value = data.conversation_id || conversationId.value;
            messages.value.push({
                role: 'model',
                text: data.reply,
                properties: data.properties || [],
                pagination: data.pagination || null,
            });
            detailsRequired.value = !!data.details_required;
        })
        .catch((error) => {
            if (requestGeneration !== generation) return;
            if (error?.response?.data?.details_required) {
                // Free question already used (e.g. in another tab) — ask for details, then they can resend.
                identified.value = false;
                detailsRequired.value = true;
                messages.value.pop();
                return;
            }
            messages.value.push({ role: 'model', text: t('chat_widget.generic_reply_error'), properties: [] });
        })
        .finally(() => {
            if (requestGeneration === generation) loading.value = false;
        });
}

/** /chatbot/* -> Api\ChatbotController (messages -> ChatbotService::reply). */
export function useChatbot() {
    return { messages, loading, isOpen, identified, detailsRequired, open, startChat, sendMessage, clearMessages };
}
