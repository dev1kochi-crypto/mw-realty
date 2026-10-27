<!doctype html>
{{-- End of Facebook Login in the popup (Crm\Integrations\FacebookController::finishLogin): hand the login's one-time id
     (selection) to the CRM screen that opened it, and close. Only that screen knows the id, so only it shows the Pages. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Facebook connected</title>
    <style>body { font-family: system-ui, sans-serif; display: grid; place-items: center; min-height: 100vh; margin: 0; color: #444; text-align: center; }</style>
</head>
<body>
    @php($targetUrl = $selection ? $indexUrl . '?selection=' . urlencode($selection) : $indexUrl)
    <p>Done — returning to Integrations…<br><a href="{{ $targetUrl }}">Continue</a></p>
    <script>
        (function () {
            var target = @json($targetUrl);
            var selection = @json($selection);
            // Tell the Integrations screen (it listens on this channel) — works even if Facebook cut window.opener.
            // Other open Integrations tabs ignore it: only the one that started this login has this selection id.
            if ('BroadcastChannel' in window) {
                new BroadcastChannel('fb-connect').postMessage({ selection: selection });
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
