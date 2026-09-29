@php
    // Shared by every transactional email in the app — resolved once here so no individual
    // mailable/view has to fetch it itself. Gracefully falls back to a plain text brand name
    // when no logo is configured (or SiteInformation has no row yet), same fallback pattern
    // already used by resources/views/vendor/cms-kit/auth/login.blade.php.
    $emailSiteInfo = \App\Models\CmsKit\SiteInformation::first();
    $emailBrandName = $emailSiteInfo->company_name ?? config('cms-kit.common.name', 'MW Realty');
    $emailLogoPath = $emailSiteInfo->logo ?? null;
    $emailPhone = $emailSiteInfo->phone_1 ?? null;
    $emailContact = $emailSiteInfo->email_1 ?? null;
    $emailAddress = $emailSiteInfo->address ?? null;
    $emailSocials = collect(['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'twitter' => 'X'])
        ->filter(fn ($label, $field) => filled($emailSiteInfo->{$field} ?? null))
        ->map(fn ($label, $field) => ['label' => $label, 'url' => $emailSiteInfo->{$field}]);

    // Hero tones for the optional icon badge above the heading.
    $emailTones = [
        'teal' => ['#e6f6fb', '#04a1cc'],
        'green' => ['#e7f6ee', '#0f9d58'],
        'amber' => ['#fef4e3', '#d9860a'],
        'red' => ['#fdecef', '#bd2545'],
        'navy' => ['#e9eef6', '#264373'],
    ];
    [$emailToneBg, $emailToneFg] = $emailTones[trim($__env->yieldContent('tone', 'teal'))] ?? $emailTones['teal'];
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>@yield('subject', $emailBrandName)</title>
<style>
    @media only screen and (max-width: 600px) {
        .em-shell { padding: 16px 8px !important; }
        .em-pad { padding-left: 22px !important; padding-right: 22px !important; }
        .em-heading { font-size: 21px !important; }
        .em-label { width: 96px !important; }
    }
    a { color: #04a1cc; }
</style>
</head>
<body style="margin:0; padding:0; background-color:#eef1f7; font-family: Arial, Helvetica, sans-serif; -webkit-text-size-adjust:100%;">
{{-- Preheader: the grey preview line inbox lists show next to the subject. --}}
<div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#eef1f7;">
    @yield('preheader')&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="em-shell" style="background-color:#eef1f7; padding:36px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">
                {{-- Header --}}
                <tr>
                    <td style="background-color:#bd2545; height:4px; line-height:4px; font-size:0; border-radius:14px 14px 0 0;">&nbsp;</td>
                </tr>
                <tr>
                    <td class="em-pad" style="background-color:#264373; padding:22px 36px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>
                                    <a href="{{ url('/') }}" target="_blank" style="text-decoration:none;">
                                        @if($emailLogoPath)
                                            <img src="{{ media_url($emailLogoPath) }}" alt="{{ $emailSiteInfo->logo_alt ?? $emailBrandName }}" style="max-height:38px; display:block; border:0;">
                                        @else
                                            <span style="color:#ffffff; font-size:19px; font-weight:bold; letter-spacing:0.04em;">{{ $emailBrandName }}</span>
                                        @endif
                                    </a>
                                </td>
                                @hasSection('eyebrow')
                                <td align="right" style="font-size:11px; font-weight:bold; letter-spacing:0.08em; text-transform:uppercase; color:#b9c6dd;">@yield('eyebrow')</td>
                                @endif
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td class="em-pad" style="background-color:#ffffff; padding:36px 36px 32px; color:#1c2340; font-size:14px; line-height:1.65;">
                        @hasSection('icon')
                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
                            <tr><td width="52" height="52" align="center" valign="middle" style="width:52px; height:52px; border-radius:26px; background-color:{{ $emailToneBg }}; color:{{ $emailToneFg }}; font-size:24px; line-height:52px;">@yield('icon')</td></tr>
                        </table>
                        @endif

                        @hasSection('heading')
                        <h1 class="em-heading" style="margin:0 0 18px; font-size:23px; line-height:1.3; font-weight:bold; color:#1c2340;">@yield('heading')</h1>
                        @endif

                        @yield('content')

                        @hasSection('cta_url')
                        {{-- Inline @section values arrive HTML-escaped; decode so the button escapes them exactly once. --}}
                        <x-email.button :url="html_entity_decode(trim($__env->yieldContent('cta_url')))" :label="html_entity_decode(trim($__env->yieldContent('cta_label', 'View Details')))" style="margin-top:26px;" />
                        @endif

                        @hasSection('signoff')
                        @yield('signoff')
                        @else
                        <p style="margin:28px 0 0; color:#5b6478;">Regards,<br><strong style="color:#1c2340;">The {{ $emailBrandName }} Team</strong></p>
                        @endif
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td class="em-pad" style="background-color:#f7f8fc; padding:24px 36px; border-top:1px solid #e4e8f0; border-radius:0 0 14px 14px; font-size:12px; line-height:1.7; color:#838aa3;">
                        @if($emailPhone || $emailContact)
                        <p style="margin:0 0 6px; color:#5b6478;">
                            <strong style="color:#264373;">{{ $emailBrandName }}</strong>
                            @if($emailPhone) &nbsp;·&nbsp; <a href="tel:{{ preg_replace('/[^0-9+]/', '', $emailPhone) }}" style="color:#5b6478; text-decoration:none;">{{ $emailPhone }}</a>@endif
                            @if($emailContact) &nbsp;·&nbsp; <a href="mailto:{{ $emailContact }}" style="color:#5b6478; text-decoration:none;">{{ $emailContact }}</a>@endif
                        </p>
                        @endif
                        @if($emailAddress)
                        <p style="margin:0 0 6px;">{{ $emailAddress }}</p>
                        @endif
                        @if($emailSocials->isNotEmpty())
                        <p style="margin:0 0 10px;">
                            @foreach($emailSocials as $social)
                                <a href="{{ $social['url'] }}" target="_blank" style="color:#264373; font-weight:bold; text-decoration:none;">{{ $social['label'] }}</a>@if(!$loop->last) &nbsp;·&nbsp; @endif
                            @endforeach
                        </p>
                        @endif
                        <p style="margin:0;">
                            @hasSection('footer_note')
                                @yield('footer_note')
                            @else
                                This is an automated message from {{ $emailBrandName }}. Please do not reply directly to this email.
                            @endif
                        </p>
                        <p style="margin:8px 0 0;">&copy; {{ now()->year }} {{ $emailBrandName }}. All rights reserved.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
