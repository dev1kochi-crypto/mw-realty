<template>
    <section v-if="faqs.length" class="mc-faqs" :aria-labelledby="`${uid}-title`">
        <h2 :id="`${uid}-title`" class="mc-faqs__title">{{ title }}</h2>
        <div class="mc-faqs__list">
            <div v-for="(faq, i) in faqs" :key="i" class="mc-faqs__item" :class="{ 'is-open': open === i }">
                <h3 class="mc-faqs__q">
                    <button type="button" :id="`${uid}-q${i}`" :aria-expanded="open === i ? 'true' : 'false'" :aria-controls="`${uid}-a${i}`" @click="open = open === i ? null : i">
                        <span>{{ faq.question }}</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                </h3>
                <div v-show="open === i" :id="`${uid}-a${i}`" class="mc-faqs__a" role="region" :aria-labelledby="`${uid}-q${i}`">
                    <p v-for="(para, p) in paragraphs(faq.answer)" :key="p">{{ para }}</p>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref } from 'vue';

/** FAQs under a calculator (Admin › Calculators), one open at a time. */
defineProps({
    title: { type: String, required: true },
    faqs: { type: Array, default: () => [] }, // [{ question, answer }]
});

const uid = `calc-faq-${Math.random().toString(36).slice(2, 8)}`;
const open = ref(0);
const paragraphs = (text) => String(text || '').split(/\n\s*\n|\n/).map((p) => p.trim()).filter(Boolean);
</script>

<style scoped>
.mc-faqs { margin-top: clamp(36px, 4vw, 72px); font-family: "Plus Jakarta Sans", sans-serif; }
.mc-faqs__title { margin: 0 0 clamp(16px, 1.6vw, 28px); color: #244373; font-family: "Playfair Display", Georgia, serif; font-size: clamp(26px, 2.2vw, 40px); font-weight: 600; line-height: 1.2; }
.mc-faqs__list { display: flex; flex-direction: column; gap: 12px; }
.mc-faqs__item { border: 1px solid #e3e7f0; border-radius: 14px; background: #fff; transition: box-shadow 0.2s ease, border-color 0.2s ease; }
.mc-faqs__item.is-open { border-color: #d6dce8; box-shadow: 0 10px 26px rgba(36, 67, 115, 0.08); }
.mc-faqs__q { margin: 0; }
.mc-faqs__q button { display: flex; align-items: center; justify-content: space-between; gap: 16px; width: 100%; padding: 18px clamp(16px, 1.8vw, 24px); border: 0; background: none; color: #244373; font: inherit; font-size: clamp(15px, 1vw, 17px); font-weight: 700; line-height: 1.4; text-align: start; cursor: pointer; }
.mc-faqs__q svg { flex-shrink: 0; color: #c92844; transition: transform 0.2s ease; }
.mc-faqs__item.is-open .mc-faqs__q svg { transform: rotate(180deg); }
.mc-faqs__a { padding: 0 clamp(16px, 1.8vw, 24px) 18px; }
.mc-faqs__a p { margin: 0 0 10px; color: #35373c; font-size: 15px; line-height: 1.7; }
.mc-faqs__a p:last-child { margin-bottom: 0; }
</style>
