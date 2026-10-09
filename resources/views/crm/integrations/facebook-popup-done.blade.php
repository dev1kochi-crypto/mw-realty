<!doctype html>
{{-- End of Facebook Login in the popup (Crm\Integrations\FacebookController::finishLogin): tell the CRM screen behind it to reload, and close. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Facebook connected</title>
    <style>body { font-family: system-ui, sans-serif; display: grid; place-items: center; min-height: 100vh; margin: 0; color: #444; text-align: center; }</style>
</head>
<body>
    <p>Done — returning to Integrations…<br><a href="{{ $indexUrl }}">Continue</a></p>
    <script>
        (function () {
            var target = @json($indexUrl);
            // Tell the Integrations page (it listens on this channel) — works even if Facebook cut window.opener.
            // One reload only, so the flashed message isn't used up by a second one.
            if ('BroadcastChannel' in window) {
                new BroadcastChannel('fb-connect').postMessage('done');
            } else {
                try { if (window.opener && !window.opener.closed) window.opener.location.href = target; } catch (e) {}
            }
            window.close();
            // Still open (not a popup, e.g. the popup was blocked): continue here instead.
            setTimeout(function () { window.location.replace(target); }, 400);
        })();
    </script>
</body>
</html>
