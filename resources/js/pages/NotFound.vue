<template>
    <main>
        <section class="mw-about-hero">
            <div class="mw-about-hero__band"></div>
        </section>

        <section class="mw-not-found">
            <div class="container-ctn">
                <img src="/frontend/assets/images/404/illustration-404.svg" alt="" class="mw-not-found__illustration" width="667" height="385" data-reveal>
                <h1 class="mw-not-found__title" data-reveal>Page Not Found</h1>
                <p class="mw-not-found__text" data-reveal>Sorry, We can&rsquo;t find the page you&rsquo;re looking for.</p>
                <router-link to="/" class="mw-not-found__cta" data-reveal>Back to Homepage</router-link>
            </div>
        </section>
    </main>
</template>
