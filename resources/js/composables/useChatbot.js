import { computed, ref } from 'vue';
import { useStaticText } from './useStaticText';
import { useLanguages } from './useLanguages';

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
// The free question is used up — the next message needs their details first.
const detailsRequired = ref(false);
// The details form is on screen: only once they try to send while detailsRequired (never just
// for opening the chat). Their message waits in pendingMessage and goes out after the form.
const detailsPrompt = ref(false);
let pendingMessage = null;
const conversationId = ref(null);

// Chat language — separate from the website language: the assistant can chat in more languages
// than the site has (GET /chatbot/session → languages, from config('chatbot.languages')). Picked
// in the widget ("Choose your language"), remembered in this browser, and sent with every message
// so the AI replies in it. null = not picked yet → the widget shows the picker first.
const LANGUAGE_KEY = 'mw-chat-language';
const languages = ref([]);
const defaultLanguage = ref('en');
const language = ref(readLanguage());

function readLanguage() {
    try { return window.localStorage.getItem(LANGUAGE_KEY); } catch { return null; }
}

const currentLanguage = computed(() => languages.value.find((l) => l.code === language.value)
    || languages.value.find((l) => l.code === defaultLanguage.value)
    || null);

// Singleton, called once for this module (same pattern as the refs above), not per-usage —
// avoids re-registering a language-change watcher every time welcomeText()/sendMessage() runs.
const { t } = useStaticText();
const { selectedLanguage } = useLanguages();

// Bumped by clearMessages() so a reply still in flight when the visitor resets
// doesn't land in the fresh conversation a moment later — sendMessage() captures the generation
// it was sent under and only applies its result if nothing has reset the chat since.
let generation = 0;
let sessionRequest = null;

function welcomeText() {
    if (currentLanguage.value?.strings?.welcome) return currentLanguage.value.strings.welcome;
    const name = window.MW_CHATBOT_NAME || 'Noor';
    return t('chat_widget.welcome_message').replace('{name}', name);
}

/**
 * Picks the chat language. Before anything's been said the welcome is simply re-shown in it;
 * mid-conversation the assistant confirms the switch in the new language (the next reply follows).
 */
function chooseLanguage(code) {
    if (!languages.value.some((l) => l.code === code)) return;
    const changed = language.value !== code;
    language.value = code;
    try { window.localStorage.setItem(LANGUAGE_KEY, code); } catch { /* private mode etc. */ }
    if (!changed) return;
    if (messages.value.length <= 1) { // just the welcome
        freshConversation();
    } else {
        messages.value.push({ role: 'model', text: currentLanguage.value.strings.switched });
    }
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
            languages.value = data.languages || [];
            defaultLanguage.value = data.default_language || 'en';
            // This browser's last pick wins; else the language the resumed conversation was in.
            if (!languages.value.some((l) => l.code === language.value)) language.value = data.language || null;
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

/**
 * The details form — carries on the same conversation, then sends the message they were trying to
 * send (or, with none waiting, says thanks). Throws the axios error for the widget to show.
 */
function startChat(details) {
    return window.axios.post('/chatbot/start', { ...details, conversation_id: conversationId.value }).then(({ data }) => {
        identified.value = true;
        detailsRequired.value = false;
        detailsPrompt.value = false;
        conversationId.value = data.conversation_id;
        if (pendingMessage) {
            const text = pendingMessage;
            pendingMessage = null;
            post(text);
        } else {
            const thanks = t('chat_widget.details_form.thanks', 'Thanks, {name}! What else would you like to know?');
            messages.value.push({ role: 'model', text: thanks.replace('{name}', String(data.name || '').split(' ')[0]) });
        }
        return data;
    });
}

/** The form's close button — the waiting message comes off the chat and is handed back for the input box. */
function dismissDetails() {
    detailsPrompt.value = false;
    const text = pendingMessage;
    pendingMessage = null;
    const last = messages.value[messages.value.length - 1];
    if (text && last?.role === 'user' && last.text === text) messages.value.pop();
    return text || '';
}

/** Shows their message and the details form; the message is sent once the form is done. */
function askForDetails(text) {
    pendingMessage = text;
    detailsPrompt.value = true;
}

/** "Delete" button in the widget header — the saved transcript is kept; a new conversation starts. */
function clearMessages() {
    generation += 1;
    loading.value = false;
    detailsPrompt.value = false;
    pendingMessage = null;
    freshConversation();
    conversationId.value = null;
    if (!identified.value) return;
    window.axios.post('/chatbot/reset')
        .then(({ data }) => { conversationId.value = data.conversation_id; })
        .catch(() => {});
}


function sendMessage(text) {
    const trimmed = text.trim();
    if (!trimmed || loading.value || detailsPrompt.value) return Promise.resolve();

    messages.value.push({ role: 'user', text: trimmed });
    if (detailsRequired.value) {
        askForDetails(trimmed);
        return Promise.resolve();
    }
    return post(trimmed);
}

/** POST /chatbot/message for a message already shown in the chat. */
function post(trimmed) {
    const requestGeneration = generation;
    loading.value = true;

    return window.axios.post('/chatbot/message', {
        message: trimmed,
        conversation_id: conversationId.value,
        language: currentLanguage.value?.code,
        lang: selectedLanguage.value?.code,
    })
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
                // Free question already used (e.g. in another tab) — ask for details; it's sent after.
                identified.value = false;
                detailsRequired.value = true;
                askForDetails(trimmed);
                return;
            }
            messages.value.push({ role: 'model', text: currentLanguage.value?.strings?.error || t('chat_widget.generic_reply_error'), properties: [] });
        })
        .finally(() => {
            if (requestGeneration === generation) loading.value = false;
        });
}

/** /chatbot/* -> Api\ChatbotController (messages -> ChatbotService::reply). */
export function useChatbot() {
    return {
        messages, loading, isOpen, identified, detailsRequired, detailsPrompt, open, startChat, dismissDetails, sendMessage, clearMessages,
        languages, language, currentLanguage, chooseLanguage,
    };
}
