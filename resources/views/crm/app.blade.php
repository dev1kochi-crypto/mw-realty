<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM - Partner Portal</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ !empty($siteInfo->favicon) ? media_url($siteInfo->favicon) : asset('frontend/assets/images/fav.png') }}">
    {{-- Same look as the /portal screens it replaces (portal/layouts/app.blade.php). --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('portal/css/portal.css') }}?v={{ filemtime(public_path('portal/css/portal.css')) }}">
    {{-- The property form's searchable dropdowns (LangSelect.vue) render select2's markup, so its look carries over. --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css">
    <script>
        window.CrmConfig = {
            apiBase: '/api/crm',
            basePath: '/crm',
            loginUrl: @json(route('portal.login')),
            logoUrl: @json(asset('frontend/assets/images/logo.png')),
        };
    </script>
    {{-- Dashboard charts — the same Chart.js build the portal dashboard used (window.Chart). --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js" defer></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    @vite('resources/js/crm/main.js')
</head>
<body class="portal-body">
    <div id="crm-app"></div>
</body>
</html>
