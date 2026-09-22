<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <script>window.MW_RECAPTCHA_SITE_KEY = @json(config('services.recaptcha.site_key'));</script>
        {{-- The portal/customer login+signup forms are real <form> POSTs (session redirect flow,
             not axios) — Laravel flashes validation errors and old() input to the session on
             failure and redirects back here, but a fresh SPA boot has no way to read that
             automatically. Login.vue/Signup.vue read these once on mount instead. --}}
        <script>
            window.MW_FORM_ERRORS = @json($errors->any() ? $errors->getMessages() : null);
            window.MW_OLD_INPUT = @json(old() ?: null);
        </script>

        <title>{{ config('app.name', 'MW Realty') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="icon" type="image/png" sizes="32x32" href="/frontend/assets/images/fav.png">

        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="/frontend/assets/scss/style.css?v={{ file_exists(public_path('frontend/assets/scss/style.css')) ? filemtime(public_path('frontend/assets/scss/style.css')) : 1 }}">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div id="app"></div>

        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="/frontend/assets/js/script.js"></script>
    </body>
</html>
