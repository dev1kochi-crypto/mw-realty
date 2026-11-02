import { ref } from 'vue';

/**
 * Browser-native voice I/O for the chat widget — Web Speech API (SpeechRecognition for mic
 * input, SpeechSynthesis for reading replies aloud). Both are free and run entirely client-side,
 * no server round-trip and no effect on the Gemini token cost of a conversation. Not universally
 * supported (notably Firefox lacks SpeechRecognition), so every export is feature-detected and
 * the caller decides whether to show the corresponding button at all.
 */
export function useSpeech() {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const recognitionSupported = !!SpeechRecognition;
    const synthesisSupported = 'speechSynthesis' in window;

    const listening = ref(false);
    let recognition = null;

    /** `lang`: BCP-47 locale to listen for (the chat language's), else the page's language. */
    function startListening(onResult, lang = null) {
        if (!recognitionSupported || listening.value) return;

        recognition = new SpeechRecognition();
        recognition.lang = lang || document.documentElement.lang || 'en-US';
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;

        recognition.onresult = (event) => {
            const transcript = event.results[0]?.[0]?.transcript;
            if (transcript) onResult(transcript);
        };
        recognition.onerror = () => { listening.value = false; };
        recognition.onend = () => { listening.value = false; };

        listening.value = true;
        recognition.start();
    }

    function stopListening() {
        recognition?.stop();
        listening.value = false;
    }

    function speak(text, lang = null) {
        if (!synthesisSupported || !text) return;
        window.speechSynthesis.cancel(); // don't let replies queue up and read out of order
        const utterance = new SpeechSynthesisUtterance(text);
        if (lang) utterance.lang = lang; // picks a voice for the chat language, not the browser's default
        utterance.rate = 1;
        window.speechSynthesis.speak(utterance);
    }

    function cancelSpeech() {
        if (synthesisSupported) window.speechSynthesis.cancel();
    }

    return {
        recognitionSupported,
        synthesisSupported,
        listening,
        startListening,
        stopListening,
        speak,
        cancelSpeech,
    };
}
