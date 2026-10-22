<!doctype html>
{{-- Facebook data deletion status (Api\FacebookDataDeletionController::status) — the URL Facebook shows the user. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Data deletion status — {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; display: grid; place-items: center; min-height: 100vh; margin: 0; padding: 16px; box-sizing: border-box; color: #444; background: #f7f7f7; }
        .card { max-width: 520px; width: 100%; background: #fff; border-radius: 8px; padding: 28px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        h1 { font-size: 1.25rem; margin: 0 0 12px; color: #222; }
        p { line-height: 1.55; margin: 0 0 10px; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Facebook data deletion</h1>
        @if ($deletion)
            <p>Confirmation code: <code>{{ $deletion->confirmation_code }}</code></p>
            <p><strong>Status: Completed</strong> on {{ $deletion->completed_at?->timezone('Asia/Dubai')->format('d M Y, H:i') }} (Dubai time).</p>
            <p>The Facebook Page connections and access tokens linked to your Facebook account have been removed from {{ config('app.name') }}. We no longer receive data from those Pages.</p>
        @elseif ($code !== '')
            <p>No deletion request was found for confirmation code <code>{{ $code }}</code>.</p>
        @else
            <p>To delete the data {{ config('app.name') }} holds from Facebook, remove the app in your Facebook settings (Settings &amp; privacy › Settings › Apps and websites) and choose to send a deletion request. You will receive a confirmation code you can check here.</p>
        @endif
        <p>Questions? See our <a href="{{ url('/privacy-policy') }}">privacy policy</a>.</p>
    </div>
</body>
</html>
