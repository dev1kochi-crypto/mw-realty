<script setup>
import { computed } from 'vue';
import { useStaticText } from '../composables/useStaticText';
import { useSiteInformation } from '../composables/useSiteInformation';

/**
 * Landing page after a public form is sent — Contact page (type contact), home page "Get in touch"
 * (type enquiry) and the agent/agency Custom Property Request (type request, to agent|agency).
 * These arrive as browser history state (router.push({ state: { thankYou } })), not in the URL, so
 * no form data is ever visible in the address bar; the state survives a refresh too.
 * `name` (first name only) personalises the heading. Every string has a translation key with an
 * English fallback, so a language file that hasn't got the new keys yet still reads correctly.
 */
const { t } = useStaticText();
const { siteInformation } = useSiteInformation();

const sent = (window.history.state && window.history.state.thankYou) || {};
const type = computed(() => (['contact', 'enquiry', 'request', 'property'].includes(sent.type) ? sent.type : 'default'));
const firstName = computed(() => String(sent.name || '').trim().slice(0, 30));
const toAgency = computed(() => sent.to === 'agency');
// Property detail enquiry (type property): the listing and who now has the lead in their CRM —
// the listing's agent, or its agency when no agent is available.
const enquired = computed(() => (type.value === 'property' ? sent.property || null : null));
const handler = computed(() => (type.value === 'property' ? sent.contact || null : null));
const handlerIsAgency = computed(() => handler.value?.type === 'company');
const handlerRole = computed(() => (handlerIsAgency.value ? t('thank_you.property.role_agency', 'Agency') : t('thank_you.property.role_agent', 'Listing Agent')));
const handlerPhoneHref = computed(() => (handler.value?.phone ? 'tel:' + handler.value.phone.replace(/[^\d+]/g, '') : null));
const handlerWhatsapp = computed(() => {
    const n = String(handler.value?.whatsapp_number || '').replace(/\D/g, '');
    return n ? 'https://wa.me/' + n : null;
});
const fill = (text, vars) => Object.entries(vars).reduce((out, [k, v]) => out.replace('{' + k + '}', v), text);

const copy = computed(() => {
    const key = `thank_you.${type.value}`;
    const defaults = {
        default: ['Your submission has been received.', 'We will be in touch and contact you soon!'],
        contact: ['Your message is on its way to our team.', 'A member of MW Realty will get back to you within one business day.'],
        enquiry: ['Thanks for your interest — your enquiry is in.', 'A property advisor will call you shortly to understand exactly what you need.'],
        request: ['Your property request has been received.', toAgency.value
            ? 'The agency is already on it and will share properties that match your brief.'
            : 'Your agent is already on it and will share properties that match your brief.'],
        property: ['Your enquiry about “{property}” has been sent.', '{handler} will get in touch with you shortly.'],
    }[type.value];

    if (type.value === 'property') {
        const who = handler.value?.name || t('thank_you.property.our_team', 'Our team');
        return {
            line1: fill(t('thank_you.property.line1', defaults[0]), { property: enquired.value?.name || t('thank_you.property.this_property', 'this property') }),
            line2: fill(t('thank_you.property.line2', defaults[1]), { handler: who }),
        };
    }

    return {
        line1: t(`${key}.line1`, defaults[0]),
        line2: type.value === 'request'
            ? t(`thank_you.request.line2_${toAgency.value ? 'agency' : 'agent'}`, defaults[1])
            : t(`${key}.line2`, defaults[1]),
    };
});

const title = computed(() => (firstName.value
    ? t('thank_you.title_named', 'Thank you, {name}!').replace('{name}', firstName.value)
    : t('thank_you.title', 'Thank You!')));

const steps = computed(() => (type.value === 'property'
    ? [
        { icon: 'brief', title: t('thank_you.steps.property_1_title', 'Enquiry delivered'), text: handlerIsAgency.value ? t('thank_you.steps.property_1_text_agency', 'Sent straight to the agency handling this listing.') : t('thank_you.steps.property_1_text', 'Sent straight to the listing agent.') },
        { icon: 'phone', title: t('thank_you.steps.property_2_title', "They'll reach out"), text: t('thank_you.steps.property_2_text', 'Expect a call, email or WhatsApp to answer your questions.') },
        { icon: 'home', title: t('thank_you.steps.property_3_title', 'Book a viewing'), text: t('thank_you.steps.property_3_text', 'See the property in person at a time that suits you.') },
    ]
    : type.value === 'request'
    ? [
        { icon: 'brief', title: t('thank_you.steps.request_1_title', 'Brief shared'), text: t('thank_you.steps.request_1_text', 'Your requirements are with the right specialist.') },
        { icon: 'search', title: t('thank_you.steps.request_2_title', 'Shortlisting'), text: t('thank_you.steps.request_2_text', 'Matching listings are hand-picked for you.') },
        { icon: 'home', title: t('thank_you.steps.request_3_title', 'Options in your inbox'), text: t('thank_you.steps.request_3_text', 'Review, shortlist and book your viewings.') },
    ]
    : [
        { icon: 'brief', title: t('thank_you.steps.1_title', 'Received'), text: t('thank_you.steps.1_text', 'Your details reached our team securely.') },
        { icon: 'phone', title: t('thank_you.steps.2_title', "We'll reach out"), text: t('thank_you.steps.2_text', 'An advisor contacts you by phone, email or WhatsApp.') },
        { icon: 'home', title: t('thank_you.steps.3_title', 'Find your place'), text: t('thank_you.steps.3_text', 'Get tailored options and expert guidance.') },
    ]));

const phone = computed(() => siteInformation.value?.phone || null);
const phoneHref = computed(() => (phone.value ? 'tel:' + phone.value.replace(/[^\d+]/g, '') : null));
const email = computed(() => siteInformation.value?.email || null);

// Brand-coloured confetti: each piece gets its own burst direction, spin and delay.
const confetti = Array.from({ length: 22 }, (_, i) => {
    const angle = (i / 22) * Math.PI * 2 + (i % 2 ? 0.2 : -0.1);
    const distance = 110 + (i * 37) % 90;
    return {
        '--x': `${Math.round(Math.cos(angle) * distance)}px`,
        '--y': `${Math.round(Math.sin(angle) * distance)}px`,
        '--r': `${(i * 73) % 360}deg`,
        '--d': `${0.55 + (i % 5) * 0.04}s`,
        '--c': ['#c92844', '#244373', '#f5a623', '#04a1cc', '#e0526f'][i % 5],
        '--w': `${i % 3 === 0 ? 10 : 7}px`,
        '--h': `${i % 3 === 0 ? 4 : 7}px`,
    };
});
</script>

<template>
    <main>
        <section class="ty">
            <div class="ty__orb ty__orb--red" aria-hidden="true"></div>
            <div class="ty__orb ty__orb--navy" aria-hidden="true"></div>

            <div class="container-ctn ty__inner">
                <div class="ty__badge" aria-hidden="true">
                    <span class="ty__ripple"></span>
                    <span class="ty__ripple ty__ripple--late"></span>
                    <span v-for="(style, i) in confetti" :key="i" class="ty__confetti" :style="style"></span>
                    <svg class="ty__check" viewBox="0 0 120 120" width="120" height="120">
                        <defs>
                            <linearGradient id="tyGradient" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0%" stop-color="#c92844" />
                                <stop offset="100%" stop-color="#244373" />
                            </linearGradient>
                        </defs>
                        <circle class="ty__check-fill" cx="60" cy="60" r="52" />
                        <circle class="ty__check-ring" cx="60" cy="60" r="52" />
                        <path class="ty__check-mark" d="M38 62 L53 77 L83 45" />
                    </svg>
                </div>

                <span class="ty__eyebrow">{{ t('thank_you.eyebrow', 'Submission received') }}</span>
                <h1 class="ty__title">{{ title }}</h1>
                <p class="ty__text">
                    {{ copy.line1 }}<br>
                    <span>{{ copy.line2 }}</span>
                </p>

                <div v-if="enquired" class="ty__summary">
                    <router-link :to="'/property-details/' + enquired.slug" class="ty__summary-property">
                        <img v-if="enquired.image" :src="enquired.image" :alt="enquired.name" class="ty__summary-img">
                        <span class="ty__summary-body">
                            <span class="ty__summary-label">{{ t('thank_you.property.enquired_about', 'You enquired about') }}</span>
                            <strong class="ty__summary-name">{{ enquired.name }}</strong>
                            <span v-if="enquired.location && enquired.location !== '—'" class="ty__summary-meta">{{ enquired.location }}</span>
                        </span>
                    </router-link>
                    <div v-if="handler" class="ty__summary-handler">
                        <img :src="handler.avatar_url || (handlerIsAgency ? '/frontend/assets/images/icons/building.svg' : '/frontend/assets/images/agents/ahmed.png')" :alt="handler.name" class="ty__summary-avatar" :class="{ 'ty__summary-avatar--icon': !handler.avatar_url && handlerIsAgency }">
                        <span class="ty__summary-body">
                            <span class="ty__summary-label">{{ t('thank_you.property.sent_to', 'Sent to') }} &middot; {{ handlerRole }}</span>
                            <router-link v-if="handler.detail_url" :to="handler.detail_url" class="ty__summary-name">{{ handler.name }}</router-link>
                            <strong v-else class="ty__summary-name">{{ handler.name }}</strong>
                        </span>
                        <span class="ty__summary-actions">
                            <a v-if="handlerPhoneHref" :href="handlerPhoneHref" class="ty__summary-btn" :aria-label="t('thank_you.property.call', 'Call')" :title="t('thank_you.property.call', 'Call')">
                                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z" /></svg>
                            </a>
                            <a v-if="handlerWhatsapp" :href="handlerWhatsapp" target="_blank" rel="noopener" class="ty__summary-btn ty__summary-btn--wa" :aria-label="t('thank_you.property.whatsapp', 'WhatsApp')" :title="t('thank_you.property.whatsapp', 'WhatsApp')">
                                <svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2c-1.5 0-3-.4-4.3-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.1 5.1 0 0 0 1.1 2.7 11.6 11.6 0 0 0 4.4 3.9c1.6.7 2.3.8 3.1.6.5-.1 1.5-.6 1.7-1.2s.2-1.1.1-1.2l-.5-.3z" /></svg>
                            </a>
                        </span>
                    </div>
                </div>

                <ol class="ty__steps">
                    <li v-for="(step, i) in steps" :key="i" class="ty__step" :style="{ '--i': i }">
                        <span class="ty__step-icon">
                            <svg v-if="step.icon === 'brief'" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13" /><path d="M22 2 15 22l-4-9-9-4 20-7z" /></svg>
                            <svg v-else-if="step.icon === 'phone'" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z" /></svg>
                            <svg v-else-if="step.icon === 'search'" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
                            <svg v-else viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><path d="M9 22V12h6v10" /></svg>
                        </span>
                        <span class="ty__step-num">{{ String(i + 1).padStart(2, '0') }}</span>
                        <strong class="ty__step-title">{{ step.title }}</strong>
                        <span class="ty__step-text">{{ step.text }}</span>
                    </li>
                </ol>

                <div class="ty__actions">
                    <router-link v-if="enquired" :to="'/property-details/' + enquired.slug" class="ty__btn ty__btn--solid">{{ t('thank_you.property.back', 'Back to Property') }}</router-link>
                    <router-link to="/" class="ty__btn" :class="enquired ? 'ty__btn--ghost' : 'ty__btn--solid'">{{ t('thank_you.cta', 'Back to Homepage') }}</router-link>
                    <router-link to="/properties" class="ty__btn ty__btn--ghost">{{ t('thank_you.cta_browse', 'Browse Properties') }}</router-link>
                </div>

                <p v-if="phone || email" class="ty__help">
                    {{ t('thank_you.urgent', 'Need something urgently?') }}
                    <a v-if="phone" :href="phoneHref">{{ phone }}</a>
                    <template v-if="phone && email"> &middot; </template>
                    <a v-if="email" :href="'mailto:' + email">{{ email }}</a>
                </p>
            </div>
        </section>
    </main>
</template>

<style scoped>
.ty {
    --ty-red: #c92844;
    --ty-navy: #244373;
    position: relative;
    overflow: hidden;
    /* Clears the fixed site header (same offset the grey title banner uses on other pages). */
    margin-top: clamp(64px, 4.85vw, 74px);
    padding: clamp(40px, 5vw, 96px) 0 clamp(64px, 7vw, 130px);
    background:
        radial-gradient(circle at 1px 1px, rgba(36, 67, 115, 0.07) 1px, transparent 0) 0 0 / 26px 26px,
        #fff;
    text-align: center;
}
.ty__inner { position: relative; z-index: 1; }

/* Drifting brand-colour glows behind the content */
.ty__orb { position: absolute; width: clamp(260px, 32vw, 520px); aspect-ratio: 1; border-radius: 50%; filter: blur(70px); opacity: 0.18; pointer-events: none; }
.ty__orb--red { background: var(--ty-red); top: -12%; inset-inline-start: -8%; animation: ty-drift 14s ease-in-out infinite alternate; }
.ty__orb--navy { background: var(--ty-navy); bottom: -18%; inset-inline-end: -10%; animation: ty-drift 17s ease-in-out infinite alternate-reverse; }

/* Animated check badge */
.ty__badge { position: relative; width: 120px; height: 120px; margin: 0 auto; }
.ty__check { position: relative; z-index: 2; display: block; overflow: visible; animation: ty-pop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.9s both; }
.ty__check-fill { fill: rgba(201, 40, 68, 0.06); }
.ty__check-ring { fill: none; stroke: url(#tyGradient); stroke-width: 5; stroke-linecap: round; stroke-dasharray: 327; stroke-dashoffset: 327; transform: rotate(-90deg); transform-origin: center; animation: ty-draw 0.8s ease-out 0.15s forwards; }
.ty__check-mark { fill: none; stroke: url(#tyGradient); stroke-width: 7; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 70; stroke-dashoffset: 70; animation: ty-draw 0.45s ease-out 0.75s forwards; }
.ty__ripple { position: absolute; inset: 0; border-radius: 50%; border: 2px solid rgba(201, 40, 68, 0.35); opacity: 0; animation: ty-ripple 1.8s ease-out 1s 2; }
.ty__ripple--late { border-color: rgba(36, 67, 115, 0.3); animation-delay: 1.4s; }
.ty__confetti { position: absolute; top: 50%; left: 50%; width: var(--w); height: var(--h); margin: calc(var(--h) / -2) 0 0 calc(var(--w) / -2); border-radius: 2px; background: var(--c); opacity: 0; animation: ty-burst 1.1s cubic-bezier(0.15, 0.7, 0.3, 1) var(--d) forwards; }

/* Text */
.ty__eyebrow { display: inline-flex; align-items: center; gap: 8px; margin-top: clamp(22px, 2vw, 34px); padding: 6px 14px; border-radius: 50px; background: rgba(201, 40, 68, 0.08); color: var(--ty-red); font-family: "Plus Jakarta Sans", sans-serif; font-size: 12px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; animation: ty-rise 0.6s ease-out 1.05s both; }
.ty__eyebrow::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: currentColor; animation: ty-blink 1.6s ease-in-out 1.6s infinite; }
.ty__title { margin: 14px 0 0; color: var(--ty-navy); font-family: "Playfair Display", Georgia, serif; font-size: clamp(34px, 3.85vw, 70px); font-weight: 600; line-height: 1.12; animation: ty-rise 0.7s ease-out 1.15s both; }
.ty__text { max-width: 620px; margin: clamp(12px, 1vw, 18px) auto 0; color: #35373c; font-family: "Plus Jakarta Sans", sans-serif; font-size: clamp(15px, 1vw, 18px); line-height: 1.65; animation: ty-rise 0.7s ease-out 1.25s both; }
.ty__text span { color: #6b7080; }

/* Property enquiry summary: the listing + who received the lead */
.ty__summary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; max-width: 760px; margin: clamp(26px, 2.4vw, 40px) auto 0; text-align: start; animation: ty-rise 0.6s ease-out 1.35s both; }
.ty__summary-property, .ty__summary-handler { display: flex; align-items: center; gap: 14px; min-width: 0; padding: 12px; border: 1px solid #e3e7f0; border-radius: 14px; background: #fff; box-shadow: 0 10px 26px rgba(36, 67, 115, 0.08); color: inherit; text-decoration: none; }
.ty__summary-property { transition: transform 0.2s ease, box-shadow 0.2s ease; }
.ty__summary-property:hover { transform: translateY(-2px); box-shadow: 0 16px 32px rgba(36, 67, 115, 0.14); }
.ty__summary-img { width: 76px; height: 60px; flex-shrink: 0; border-radius: 10px; object-fit: cover; background: #eef1f6; }
.ty__summary-avatar { width: 52px; height: 52px; flex-shrink: 0; border-radius: 50%; object-fit: cover; background: #eef1f6; }
.ty__summary-avatar--icon { object-fit: contain; padding: 11px; }
.ty__summary-body { display: flex; flex-direction: column; min-width: 0; flex: 1; font-family: "Plus Jakarta Sans", sans-serif; }
.ty__summary-label { color: var(--ty-red); font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; }
.ty__summary-name { overflow: hidden; color: var(--ty-navy); font-size: 15px; font-weight: 700; white-space: nowrap; text-overflow: ellipsis; text-decoration: none; }
a.ty__summary-name:hover { color: var(--ty-red); }
.ty__summary-meta { overflow: hidden; color: #6b7080; font-size: 13px; white-space: nowrap; text-overflow: ellipsis; }
.ty__summary-actions { display: flex; gap: 8px; flex-shrink: 0; }
.ty__summary-btn { display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 50%; background: rgba(36, 67, 115, 0.08); color: var(--ty-navy); transition: background 0.2s ease, color 0.2s ease; }
.ty__summary-btn:hover { background: var(--ty-navy); color: #fff; }
.ty__summary-btn--wa { background: rgba(37, 211, 102, 0.12); color: #1faa55; }
.ty__summary-btn--wa:hover { background: #25d366; color: #fff; }

/* What happens next */
.ty__steps { position: relative; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: clamp(14px, 1.6vw, 28px); max-width: 920px; margin: clamp(36px, 3.4vw, 60px) auto 0; padding: 0; list-style: none; }
.ty__steps::before { content: ''; position: absolute; top: 34px; inset-inline: 16%; height: 2px; background: linear-gradient(90deg, var(--ty-red), var(--ty-navy)); transform: scaleX(0); transform-origin: inline-start; opacity: 0.35; animation: ty-line 0.9s ease-out 1.55s forwards; }
.ty__step { position: relative; display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 0 8px 22px; border-radius: 16px; animation: ty-rise 0.6s ease-out calc(1.45s + var(--i) * 0.18s) both; transition: transform 0.25s ease, box-shadow 0.25s ease; }
.ty__step:hover { transform: translateY(-4px); }
.ty__step-icon { position: relative; z-index: 1; display: flex; align-items: center; justify-content: center; width: 68px; height: 68px; margin-bottom: 8px; border-radius: 50%; background: #fff; color: var(--ty-navy); border: 1px solid rgba(36, 67, 115, 0.12); box-shadow: 0 10px 24px rgba(36, 67, 115, 0.12); transition: background 0.25s ease, color 0.25s ease; }
.ty__step:hover .ty__step-icon { background: linear-gradient(135deg, var(--ty-red), var(--ty-navy)); color: #fff; }
.ty__step-num { color: var(--ty-red); font-family: "Plus Jakarta Sans", sans-serif; font-size: 12px; font-weight: 800; letter-spacing: 0.1em; }
.ty__step-title { color: var(--ty-navy); font-family: "Plus Jakarta Sans", sans-serif; font-size: 17px; font-weight: 700; }
.ty__step-text { max-width: 240px; color: #6b7080; font-family: "Plus Jakarta Sans", sans-serif; font-size: 14px; line-height: 1.5; }

/* Actions */
.ty__actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; margin-top: clamp(30px, 2.6vw, 46px); animation: ty-rise 0.6s ease-out 2s both; }
.ty__btn { position: relative; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; padding: clamp(14px, 1vw, 18px) clamp(26px, 1.9vw, 36px); border-radius: 5px; font-family: "Plus Jakarta Sans", sans-serif; font-size: 16px; font-weight: 700; text-decoration: none; white-space: nowrap; transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease; }
.ty__btn:hover { transform: translateY(-2px); }
.ty__btn--solid { background: linear-gradient(90deg, var(--ty-red) 0%, var(--ty-navy) 100%); color: #fff; box-shadow: 0 12px 26px rgba(201, 40, 68, 0.25); }
.ty__btn--solid::after { content: ''; position: absolute; inset: 0; background: linear-gradient(110deg, transparent 30%, rgba(255, 255, 255, 0.35) 50%, transparent 70%); transform: translateX(-120%); animation: ty-shine 2.8s ease-in-out 2.6s infinite; }
.ty__btn--ghost { border: 1.5px solid var(--ty-navy); color: var(--ty-navy); background: #fff; }
.ty__btn--ghost:hover { background: var(--ty-navy); color: #fff; }
.ty__help { margin: 22px 0 0; color: #6b7080; font-family: "Plus Jakarta Sans", sans-serif; font-size: 14px; animation: ty-rise 0.6s ease-out 2.15s both; }
.ty__help a { color: var(--ty-navy); font-weight: 700; text-decoration: none; }
.ty__help a:hover { color: var(--ty-red); }

@keyframes ty-draw { to { stroke-dashoffset: 0; } }
@keyframes ty-pop { 0% { transform: scale(1); } 45% { transform: scale(1.12); } 100% { transform: scale(1); } }
@keyframes ty-ripple { 0% { transform: scale(0.9); opacity: 0.9; } 100% { transform: scale(1.9); opacity: 0; } }
@keyframes ty-burst {
    0% { transform: translate(0, 0) rotate(0) scale(0.4); opacity: 0; }
    15% { opacity: 1; }
    100% { transform: translate(var(--x), var(--y)) rotate(var(--r)) scale(1); opacity: 0; }
}
@keyframes ty-rise { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } }
@keyframes ty-line { to { transform: scaleX(1); } }
@keyframes ty-blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.25; } }
@keyframes ty-shine { 0% { transform: translateX(-120%); } 45%, 100% { transform: translateX(120%); } }
@keyframes ty-drift { from { transform: translate(0, 0) scale(1); } to { transform: translate(6%, 8%) scale(1.12); } }

@media (max-width: 767.98px) {
    .ty__steps { grid-template-columns: 1fr; max-width: 360px; }
    .ty__summary { grid-template-columns: 1fr; }
    .ty__steps::before { display: none; }
    .ty__step { padding-bottom: 14px; }
    .ty__btn { flex: 1 1 100%; }
}

@media (prefers-reduced-motion: reduce) {
    .ty *, .ty *::before, .ty *::after { animation-duration: 0.01ms !important; animation-delay: 0s !important; animation-iteration-count: 1 !important; }
    .ty__confetti, .ty__ripple { display: none; }
}
</style>
