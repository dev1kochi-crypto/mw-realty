import gsap from 'gsap';
import { prefersReducedMotion } from './useReducedMotion';

/** Border/glow pulse when an OTP box receives focus. */
export function animateOtpFocus(boxEl) {
    if (!boxEl || prefersReducedMotion()) return;
    gsap.fromTo(boxEl, { scale: 1 }, { scale: 1.06, duration: 0.15, ease: 'power2.out', yoyo: true, repeat: 1 });
}

/** The digit "popping" into place once typed. */
export function animateDigitEntry(boxEl) {
    if (!boxEl) return;
    if (prefersReducedMotion()) {
        gsap.set(boxEl, { opacity: 1, scale: 1, y: 0 });
        return;
    }
    gsap.fromTo(boxEl, { opacity: 0, scale: 0.7, y: -5 }, { opacity: 1, scale: 1, y: 0, duration: 0.25, ease: 'back.out(2)' });
}

/**
 * Once the 4th digit lands, the row of boxes reflows into a 2x2 node cluster (top pair / bottom
 * pair) with thin lines connecting each pair, instead of just sitting there disabled — this is
 * the "verifying" visual while the real request is in flight. Uses a manual FLIP: measure each
 * box's screen position before the layout changes, toggle the CSS class that switches the
 * container from a flex row to `display: grid` (a plain synchronous classList change, not a Vue
 * :class binding, so the "before" and "after" rects can both be read in this same call), then
 * tween each box from its old position back to 0 so the reflow animates instead of snapping.
 * The connecting lines are positioned in plain pixels computed from the boxes' settled rects
 * (their length/angle can't be hard-coded in CSS since the boxes use clamp() sizing).
 */
export function morphBoxesToGrid(wrapEl, boxEls, linkEls) {
    if (!wrapEl) return;
    const boxes = (boxEls || []).filter(Boolean);
    const links = (linkEls || []).filter(Boolean);
    const reduced = prefersReducedMotion();

    const first = boxes.map((el) => el.getBoundingClientRect());
    wrapEl.classList.add('is-grid');
    const wrapRect = wrapEl.getBoundingClientRect();
    const last = boxes.map((el) => el.getBoundingClientRect());

    if (!reduced) {
        boxes.forEach((el, i) => {
            const dx = first[i].left - last[i].left;
            const dy = first[i].top - last[i].top;
            gsap.fromTo(el, { x: dx, y: dy }, { x: 0, y: 0, duration: 0.5, ease: 'power2.inOut' });
        });
    }

    const centers = last.map((r) => ({
        x: r.left + r.width / 2 - wrapRect.left,
        y: r.top + r.height / 2 - wrapRect.top,
    }));
    // Edges of the 2x2 cluster: top pair, bottom pair, left pair, right pair.
    const EDGES = [[0, 1], [2, 3], [0, 2], [1, 3]];
    EDGES.forEach(([a, b], i) => {
        const line = links[i];
        if (!line || !centers[a] || !centers[b]) return;
        const dx = centers[b].x - centers[a].x;
        const dy = centers[b].y - centers[a].y;
        const length = Math.hypot(dx, dy);
        gsap.set(line, {
            width: length,
            left: centers[a].x,
            top: centers[a].y,
            rotate: (Math.atan2(dy, dx) * 180) / Math.PI,
            transformOrigin: '0 50%',
        });

        if (reduced) {
            gsap.set(line, { opacity: 1, scaleX: 1 });
        } else {
            // Boxes take 0.5s to settle into place (the FLIP tween above) — the line only starts
            // drawing itself once they're most of the way there, growing outward from the first
            // box to the second, instead of snapping to full length while the boxes are still
            // sliding (which read as the line being disconnected from the boxes it's supposed to
            // be tracking).
            gsap.fromTo(line, { opacity: 1, scaleX: 0 }, { scaleX: 1, duration: 0.3, delay: 0.35, ease: 'power2.out' });
        }
    });
}

/**
 * The 4-box row shrinking down to a point once the server has responded (success or failure) —
 * run and awaited BEFORE the caller flips its v-if to swap in the result template, so the boxes
 * are still actually mounted while they animate out (swapping the v-if first would unmount them
 * instantly and skip the animation entirely). The result icon that appears next then grows in
 * from the same small point, so the two halves read as one continuous "row collapses into a
 * square" morph rather than an abrupt cut. Returns a Promise (GSAP tweens are thenable) so the
 * caller can `await` it.
 */
export function collapseOtpBoxes(boxesWrapEl) {
    if (!boxesWrapEl) return Promise.resolve();
    if (prefersReducedMotion()) {
        gsap.set(boxesWrapEl, { opacity: 0 });
        return Promise.resolve();
    }
    return gsap.to(boxesWrapEl, { scale: 0.3, opacity: 0, duration: 0.35, ease: 'power2.in' });
}

const PARTICLE_COUNT = 20;
const PARTICLE_SHAPES = ['circle', 'square', 'diamond'];

/**
 * Checkmark draws itself (SVG stroke-dashoffset, not a fade) -> a small burst of green
 * particles -> heading/subtitle/badge/button reveal. Called after collapseOtpBoxes() has
 * already finished and the caller has swapped in the success template.
 *
 * @param {object} els
 * @param {HTMLElement} els.checkmarkContainer
 * @param {SVGPathElement} els.checkmarkPath
 * @param {HTMLElement} els.particlesContainer  empty element particles get appended into
 * @param {HTMLElement[]} els.revealEls  heading/subtitle/badge/button, staggered in after the checkmark
 */
export function animateOtpSuccess({ checkmarkContainer, checkmarkPath, particlesContainer, revealEls = [] }) {
    if (prefersReducedMotion()) {
        gsap.set([checkmarkContainer, ...revealEls].filter(Boolean), { opacity: 1, scale: 1, clearProps: 'transform' });
        if (checkmarkPath) gsap.set(checkmarkPath, { strokeDashoffset: 0 });
        return;
    }

    const tl = gsap.timeline();

    if (checkmarkContainer) {
        tl.fromTo(checkmarkContainer,
            { scale: 0.5, opacity: 0 },
            { scale: 1.1, opacity: 1, duration: 0.35, ease: 'power2.out' },
            0)
            .to(checkmarkContainer, { scale: 1, duration: 0.2, ease: 'power2.inOut' });
    }

    if (checkmarkPath) {
        const length = checkmarkPath.getTotalLength ? checkmarkPath.getTotalLength() : 40;
        gsap.set(checkmarkPath, { strokeDasharray: length, strokeDashoffset: length });
        tl.to(checkmarkPath, { strokeDashoffset: 0, duration: 0.4, ease: 'power2.out' }, 0.15);
    }

    if (particlesContainer) {
        tl.add(() => spawnParticles(particlesContainer), 0.25);
    }

    if (revealEls.length) {
        tl.fromTo(revealEls, { opacity: 0, y: 12 }, { opacity: 1, y: 0, duration: 0.4, stagger: 0.1, ease: 'power3.out' }, 0.4);
    }
}

/**
 * Same shape as animateOtpSuccess but for the invalid/expired/rate-limited path — the cross
 * icon draws in (two diagonal strokes instead of the check's single one), gets a small settling
 * shake once it lands, then the heading/subtitle reveal. The caller auto-returns to the entry
 * form after a short hold — no button here, no particles either; this is a "something went
 * wrong" beat, not a celebration.
 *
 * @param {object} els
 * @param {HTMLElement} els.crossmarkContainer
 * @param {SVGPathElement} els.crossPath1
 * @param {SVGPathElement} els.crossPath2
 * @param {HTMLElement[]} els.revealEls
 */
export function animateOtpFailure({ crossmarkContainer, crossPath1, crossPath2, revealEls = [] }) {
    if (prefersReducedMotion()) {
        gsap.set([crossmarkContainer, ...revealEls].filter(Boolean), { opacity: 1, scale: 1, clearProps: 'transform' });
        [crossPath1, crossPath2].forEach((pathEl) => pathEl && gsap.set(pathEl, { strokeDashoffset: 0 }));
        return;
    }

    const tl = gsap.timeline();

    if (crossmarkContainer) {
        tl.fromTo(crossmarkContainer,
            { scale: 0.5, opacity: 0 },
            { scale: 1.1, opacity: 1, duration: 0.35, ease: 'power2.out' },
            0)
            .to(crossmarkContainer, { scale: 1, duration: 0.2, ease: 'power2.inOut' })
            .to(crossmarkContainer, { x: -5, duration: 0.05, ease: 'power1.inOut' })
            .to(crossmarkContainer, { x: 5, duration: 0.05, ease: 'power1.inOut' })
            .to(crossmarkContainer, { x: 0, duration: 0.05, ease: 'power1.inOut' });
    }

    [crossPath1, crossPath2].forEach((pathEl) => {
        if (!pathEl) return;
        const length = pathEl.getTotalLength ? pathEl.getTotalLength() : 24;
        gsap.set(pathEl, { strokeDasharray: length, strokeDashoffset: length });
        tl.to(pathEl, { strokeDashoffset: 0, duration: 0.3, ease: 'power2.out' }, 0.15);
    });

    if (revealEls.length) {
        tl.fromTo(revealEls, { opacity: 0, y: 12 }, { opacity: 1, y: 0, duration: 0.4, stagger: 0.1, ease: 'power3.out' }, 0.55);
    }
}

function spawnParticles(container) {
    for (let i = 0; i < PARTICLE_COUNT; i++) {
        const shape = PARTICLE_SHAPES[i % PARTICLE_SHAPES.length];
        const el = document.createElement('span');
        el.className = `mw-otp-particle mw-otp-particle--${shape}`;
        container.appendChild(el);

        const angle = (Math.PI * 2 * i) / PARTICLE_COUNT + Math.random() * 0.4;
        const distance = 40 + Math.random() * 50;
        const x = Math.cos(angle) * distance;
        const y = Math.sin(angle) * distance;

        gsap.fromTo(el,
            { opacity: 1, scale: 1, x: 0, y: 0, rotation: 0 },
            {
                opacity: 0,
                scale: 0.4,
                x,
                y,
                rotation: Math.random() * 180 - 90,
                duration: 0.8 + Math.random() * 0.4,
                ease: 'power2.out',
                onComplete: () => el.remove(),
            });
    }
}
