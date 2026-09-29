{{-- Browser page shown after the unsubscribe link is clicked (not an email) — styled to match the email layout. --}}
@php
    $siteInfo = \App\Models\CmsKit\SiteInformation::first();
    $brandName = $siteInfo->company_name ?? config('cms-kit.common.name', 'MW Realty');
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Unsubscribed — {{ $brandName }}</title>
</head>
<body style="margin:0; padding:48px 16px; background:#eef1f7; font-family:Arial, Helvetica, sans-serif; color:#1c2340;">
    <main style="max-width:520px; margin:0 auto; background:#fff; border-radius:14px; overflow:hidden; border-top:4px solid #bd2545;">
        <div style="background:#264373; padding:20px 32px;">
            @if($siteInfo?->logo)
                <img src="{{ media_url($siteInfo->logo) }}" alt="{{ $siteInfo->logo_alt ?? $brandName }}" style="max-height:36px; display:block;">
            @else
                <span style="color:#fff; font-size:19px; font-weight:bold;">{{ $brandName }}</span>
            @endif
        </div>
        <div style="padding:40px 32px; text-align:center;">
            <div style="width:56px; height:56px; line-height:56px; margin:0 auto 18px; border-radius:28px; background:#e7f6ee; color:#0f9d58; font-size:26px;">&#10003;</div>
            <h1 style="margin:0 0 12px; font-size:23px;">You’re unsubscribed</h1>
            <p style="margin:0 0 26px; color:#5b6478; line-height:1.6;">You won’t receive any more {{ $brandName }} newsletter updates at this address.</p>
            <a href="{{ url('/') }}" style="display:inline-block; padding:12px 26px; border-radius:8px; background:#04a1cc; color:#fff; font-weight:bold; text-decoration:none;">Return to {{ $brandName }}</a>
        </div>
    </main>
</body>
</html>
