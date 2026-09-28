<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f3f5f8;font-family:Arial,sans-serif;color:#17243a">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f5f8;padding:32px 12px">
        <tr><td align="center">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border-radius:12px;overflow:hidden">
                <tr><td style="padding:24px 32px;background:#203b64;color:#fff;font-size:20px;font-weight:bold">MW REALTY</td></tr>
                @if($imageUrl)
                    <tr><td><img src="{{ $imageUrl }}" alt="" width="600" style="display:block;width:100%;max-width:600px;height:auto"></td></tr>
                @endif
                <tr><td style="padding:32px">
                    <p style="margin:0 0 10px;color:#bd2545;font-size:12px;font-weight:bold;text-transform:uppercase">{{ $contentType }}</p>
                    <h1 style="margin:0 0 16px;font-size:26px;line-height:1.3">{{ $title }}</h1>
                    @if($summary)<p style="margin:0 0 24px;color:#5e6878;font-size:15px;line-height:1.7">{{ $summary }}</p>@endif
                    <a href="{{ $articleUrl }}" style="display:inline-block;padding:13px 22px;border-radius:6px;background:#bd2545;color:#fff;text-decoration:none;font-weight:bold">Read the story</a>
                </td></tr>
                <tr><td style="padding:20px 32px;background:#f8f9fb;color:#707989;font-size:12px;line-height:1.6">
                    You’re receiving this email because you subscribed to MW Realty updates.<br>
                    <a href="{{ $unsubscribeUrl }}" style="color:#244373">Unsubscribe from future updates</a>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
