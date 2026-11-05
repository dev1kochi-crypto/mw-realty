<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        {{-- Google's own recommendation is to place the GTM snippet as high in <head> as possible,
             before anything else, for its own performance/tracking-accuracy reasons — this block
             (plus the matching <noscript> tag right after <body>, below) applies site-wide since
             this is the one shared layout. Configured in Site Information > Extra SEO & Tracking. --}}
        {!! $siteInfo?->gtmHeadScripts() !!}

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <script>window.MW_RECAPTCHA_SITE_KEY = @json(config('services.recaptcha.site_key'));</script>
        <script>window.MW_MAP_TILES = @json(config('services.map_tiles'));</script>
        <script>window.MW_CHATBOT_NAME = @json(config('chatbot.persona_name'));</script>
        {{-- The portal/customer login+signup forms are real <form> POSTs (session redirect flow,
             not axios) — Laravel flashes validation errors and old() input to the session on
             failure and redirects back here, but a fresh SPA boot has no way to read that
             automatically. Login.vue/Signup.vue read these once on mount instead. --}}
        <script>
            window.MW_FORM_ERRORS = @json($errors->any() ? $errors->getMessages() : null);
            window.MW_OLD_INPUT = @json(old() ?: null);
        </script>

        {{-- Resolved server-side by SpaController (falls back to the site default for pages —
             login/signup/profile/thank-you — that don't route through it). --}}
        {!! $seoTags ?? '<title>' . e(config('app.name', 'MW Realty')) . '</title>' !!}
        {{-- Every page gets a canonical tag one way or another: SpaController-resolved pages
             already have one baked into $seoTags (admin-entered, or that page's own URL as the
             fallback — see SeoMeta::resolve); anything else (login/signup/profile/thank-you)
             still gets one here, self-referencing the current URL. --}}
        @if(!isset($seoTags) || !str_contains($seoTags, 'rel="canonical"'))
        <link rel="canonical" href="{{ url()->current() }}">
        @endif

        {{-- Admin-authored, trusted the same way custom_head_script/custom_body_script always
             have been — see Site Information > Extra SEO & Tracking. --}}
        {!! $siteInfo?->custom_head_script !!}

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="icon" type="image/png" sizes="32x32" href="/frontend/assets/images/fav.png">

        <link rel="preconnect" href="https://res.cloudinary.com" crossorigin>
        <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>

        {{-- Home hero poster is the page's largest paint — fetch it with the CSS instead of after the
             JS has built the page (Home.vue picks the same file by the same 768px breakpoint). --}}
        @if(request()->is('/'))
        <link rel="preload" as="image" href="/frontend/assets/images/home/hero-bg-mobile.jpg" media="(max-width: 767.98px)" fetchpriority="high">
        <link rel="preload" as="image" href="/frontend/assets/images/home/hero-bg.jpg" media="(min-width: 768px)" fetchpriority="high">
        {{-- The home page's own JS chunks, which the router would only request after app.js has run
             (the entry's own chunks are already preloaded by @vite). --}}
        @php
            $viteManifest = is_file(public_path('build/manifest.json')) ? json_decode(file_get_contents(public_path('build/manifest.json')), true) : [];
            $homeChunk = $viteManifest['resources/js/pages/Home.vue'] ?? null;
            $homeChunkFiles = $homeChunk ? array_merge([$homeChunk['file']], array_map(
                fn ($key) => $viteManifest[$key]['file'] ?? null,
                array_diff($homeChunk['imports'] ?? [], ['resources/js/app.js'], $viteManifest['resources/js/app.js']['imports'] ?? [])
            )) : [];
        @endphp
        @foreach(array_filter($homeChunkFiles) as $chunkFile)
        <link rel="modulepreload" href="{{ asset('build/' . $chunkFile) }}">
        @endforeach
        @endif

        {{-- Fonts (font-display: swap), slick and fancybox aren't needed for the first paint — the page
             is drawn by the JS bundle anyway — so they load without blocking rendering. --}}
        <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" onload="this.onload=null;this.rel='stylesheet'">
        <link rel="stylesheet" href="/frontend/assets/scss/style.css?v={{ file_exists(public_path('frontend/assets/scss/style.css')) ? filemtime(public_path('frontend/assets/scss/style.css')) : 1 }}">
        <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css" onload="this.onload=null;this.rel='stylesheet'">
        <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@6.1.15/dist/fancybox/fancybox.css" onload="this.onload=null;this.rel='stylesheet'">
        <noscript>
            <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@6.1.15/dist/fancybox/fancybox.css">
        </noscript>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        {!! $siteInfo?->gtmNoscriptTags() !!}
        {!! $siteInfo?->custom_body_script !!}

        <div id="app"></div>

        {{-- defer (not async) — script.js depends on jQuery/slick/bootstrap already being loaded,
             and defer preserves this relative execution order while letting the browser fetch
             all four in parallel instead of one at a time. --}}
        <script src="https://code.jquery.com/jquery-3.7.1.min.js" defer></script>
        <script src="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js" defer></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
        {{-- Fancybox: the lightbox for [data-fancybox] links (property photo gallery, floor plans). --}}
        <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@6.1.15/dist/fancybox/fancybox.umd.js" defer></script>
        <script src="/frontend/assets/js/script.js?v={{ file_exists(public_path('frontend/assets/js/script.js')) ? filemtime(public_path('frontend/assets/js/script.js')) : 1 }}" defer></script>
    </body>
</html>
