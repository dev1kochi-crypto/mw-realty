import { ref } from 'vue';
import { useStaticText } from './useStaticText';

// Module-level (not per-component ref()) so the conversation and open/closed
// state survive SPA route navigation, and reset on a hard reload — matching
// the stateless-server design (the backend holds no conversation state at all).
const messages = ref([]);
const loading = ref(false);
const isOpen = ref(false);

// Singleton, called once for this module (same pattern as the refs above), not per-usage —
// avoids re-registering a language-change watcher every time welcomeText()/sendMessage() runs.
const { t } = useStaticText();

const HISTORY_LIMIT = 20;

// Bumped by clearMessages() so a reply still in flight when the user hits "clear" doesn't land
// in the fresh, cleared conversation a moment later — sendMessage() captures the generation it
// was sent under and only applies its result if nothing's cleared the chat since.
let generation = 0;

function welcomeText() {
    const name = window.MW_CHATBOT_NAME || 'Remi';
    return t('chat_widget.welcome_message').replace('{name}', name);
}

function open() {
    isOpen.value = true;
    if (messages.value.length === 0) {
        messages.value.push({ role: 'model', text: welcomeText() });
    }
}

/** "Delete" button in the widget header — wipes the client-side history (the backend holds none
 *  anyway) and starts a fresh conversation, same as a hard reload would. */
function clearMessages() {
    generation += 1;
    messages.value = [{ role: 'model', text: welcomeText() }];
    loading.value = false;
}

function sendMessage(text) {
    const trimmed = text.trim();
    if (!trimmed || loading.value) return;

    const requestGeneration = generation;
    messages.value.push({ role: 'user', text: trimmed });
    loading.value = true;

    const history = messages.value
        .slice(0, -1)
        .slice(-HISTORY_LIMIT)
        .map(({ role, text }) => ({ role, text }));

    return window.axios.post('/api/chatbot/message', { message: trimmed, history })
        .then(({ data }) => {
            if (requestGeneration !== generation) return;
            messages.value.push({
                role: 'model',
                text: data.reply,
                properties: data.properties || [],
                pagination: data.pagination || null,
            });
        })
        .catch(() => {
            if (requestGeneration !== generation) return;
            messages.value.push({ role: 'model', text: t('chat_widget.generic_reply_error'), properties: [] });
        })
        .finally(() => {
            if (requestGeneration === generation) loading.value = false;
        });
}

/** POST /api/chatbot/message -> Api\ChatbotController::send -> ChatbotService::reply. */
export function useChatbot() {
    return { messages, loading, isOpen, open, sendMessage, clearMessages };
}
