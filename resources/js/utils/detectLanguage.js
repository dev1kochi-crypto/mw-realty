/**
 * Best guess at the language a chat message is written in, limited to `codes` (the assistant's
 * languages) — or null when unsure. Used only to OFFER switching the chat language, so it errs on
 * the side of null: non-Latin scripts are told apart by their letters (Arabic / Urdu / Persian by
 * the letters only one of them uses), Latin-script languages by counting common words.
 */
const COMMON_WORDS = {
    en: ['the', 'and', 'is', 'are', 'with', 'for', 'want', 'looking', 'near', 'bedroom', 'bedrooms', 'show', 'me', 'my', 'what', 'how', 'can', 'you', 'buy', 'rent', 'apartment', 'under', 'please', 'of', 'to'],
    fr: ['le', 'la', 'les', 'des', 'une', 'je', 'est', 'avec', 'pour', 'dans', 'cherche', 'appartement', 'chambres', 'près', 'vous', 'pouvez', 'acheter', 'louer', 'combien', 'quel', 'quelle', 'moins', 'du', 'et'],
    de: ['der', 'die', 'das', 'und', 'ich', 'mit', 'ist', 'eine', 'ein', 'für', 'suche', 'wohnung', 'zimmer', 'nicht', 'kaufen', 'mieten', 'unter', 'wie', 'viel', 'können', 'sie', 'mir', 'bitte', 'haus'],
    es: ['el', 'los', 'las', 'una', 'que', 'con', 'para', 'busco', 'quiero', 'habitaciones', 'cerca', 'piso', 'comprar', 'alquilar', 'cuánto', 'cuanto', 'menos', 'del', 'por', 'favor', 'casa', 'y', 'muéstrame'],
};

export function detectLanguage(text, codes = []) {
    const s = String(text || '').trim();
    const letters = (s.match(/\p{L}/gu) || []).length;
    if (letters < 4) return null;
    const share = (re) => (s.match(re) || []).length / letters;
    const allowed = (code) => (codes.includes(code) ? code : null);

    if (share(/\p{Script=Arabic}/gu) > 0.5) {
        if (/[ٹڈڑںےۓ]/u.test(s)) return allowed('ur');
        // Persian uses پ چ ژ گ and the Persian ی / ک; Arabic has ة and the Arabic ي / ك.
        if (/[پچژگیک]/u.test(s) && !/[ةيكى]/u.test(s)) return allowed('fa');
        return allowed('ar');
    }
    if (share(/\p{Script=Devanagari}/gu) > 0.5) return allowed('hi');
    if (share(/\p{Script=Cyrillic}/gu) > 0.5) return allowed('ru');
    if (share(/\p{Script=Han}/gu) > 0.3) return allowed('zh');
    if (share(/\p{Script=Latin}/gu) < 0.8) return null;

    const words = s.toLowerCase().split(/[^\p{L}]+/u).filter(Boolean);
    const scores = Object.entries(COMMON_WORDS)
        .filter(([code]) => codes.includes(code))
        .map(([code, list]) => [code, words.filter((w) => list.includes(w)).length])
        .sort((a, b) => b[1] - a[1]);
    // A clear winner only: at least two common words, and more than the runner-up.
    if (!scores.length || scores[0][1] < 2 || scores[0][1] === scores[1]?.[1]) return null;

    return scores[0][0];
}
