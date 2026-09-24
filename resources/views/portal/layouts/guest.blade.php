<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Partner Portal') - {{ config('cms-kit.common.name', 'MW Realty') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ !empty($siteInfo->favicon) ? media_url($siteInfo->favicon) : asset('frontend/assets/images/fav.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('portal/css/portal.css') }}?v={{ filemtime(public_path('portal/css/portal.css')) }}">
    @stack('styles')
</head>
<body class="portal-body">
    <div class="portal-auth-wrapper">
        <div class="portal-auth-card">
            <div class="portal-brand">
                <img src="{{ asset('frontend/assets/images/logo-dark.png') }}" alt="MW Realty" class="portal-brand__logo">
                <span>Partner Portal</span>
            </div>
            @yield('content')
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    (function () {
        // Show a "Processing..." spinner on the submit button the moment a form is actually
        // submitted (the browser only fires `submit` once native validation has passed),
        // and disable every submit button in it to prevent double-submits.
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (!(form instanceof HTMLFormElement) || form.dataset.noSpinner) return;

            const submitBtns = form.querySelectorAll('button[type="submit"]');
            const clicked = (e.submitter && e.submitter.tagName === 'BUTTON') ? e.submitter : submitBtns[0];

            submitBtns.forEach(function (btn) { btn.disabled = true; });

            if (clicked) {
                if (clicked.dataset.originalHtml === undefined) {
                    clicked.dataset.originalHtml = clicked.innerHTML;
                }
                clicked.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';
            }
        }, true);

        function restoreSubmitButtons(form) {
            form.querySelectorAll('button[type="submit"][data-original-html]').forEach(function (btn) {
                btn.innerHTML = btn.dataset.originalHtml;
                btn.disabled = false;
            });
        }
        window.restoreSubmitButtons = restoreSubmitButtons;

        // Guard against a stuck spinner if the page is restored from the back/forward cache.
        window.addEventListener('pageshow', function () {
            document.querySelectorAll('form').forEach(restoreSubmitButtons);
        });

        // After a failed server-side validation round trip, jump straight to the first
        // invalid field instead of leaving the user to hunt for it in a long form.
        document.addEventListener('DOMContentLoaded', function () {
            const firstInvalid = document.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInvalid.focus({ preventScroll: true });
            }
        });
    })();
    </script>

    @stack('scripts')
</body>
</html>
