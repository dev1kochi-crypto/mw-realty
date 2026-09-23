<script setup>
// Purely decorative and invisible by default (see .mw-auth-glass in _auth-glass.scss:
// opacity: 0) — the orb is only ever made visible momentarily by App.vue's onAuthLeave/
// onAuthEnter transition hooks, for the login<->signup click transition specifically. It does
// nothing at all on a normal page load, so it can never sit on top of / overlap the real form.
</script>

<template>
    <div class="mw-auth-glass" aria-hidden="true">
        <div class="mw-auth-glass__orb"></div>
    </div>
</template>
