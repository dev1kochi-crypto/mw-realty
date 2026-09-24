@php
    // Shared by every transactional email in the app — resolved once here so no individual
    // mailable/view has to fetch it itself. Gracefully falls back to a plain text brand name
    // when no logo is configured (or SiteInformation has no row yet), same fallback pattern
    // already used by resources/views/vendor/cms-kit/auth/login.blade.php.
    $emailSiteInfo = \App\Models\CmsKit\SiteInformation::first();
    $emailBrandName = $emailSiteInfo->company_name ?? config('cms-kit.common.name', 'MW Realty');
    $emailLogoPath = $emailSiteInfo->logo ?? null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('subject', $emailBrandName)</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6fb; font-family: Arial, Helvetica, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6fb; padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:14px; overflow:hidden;">
                <tr>
                    <td style="background-color:#264373; padding:22px 32px;">
                        @if($emailLogoPath)
                            <img src="{{ media_url($emailLogoPath) }}" alt="{{ $emailSiteInfo->logo_alt ?? $emailBrandName }}" style="max-height:36px; display:block; border:0;">
                        @else
                            <span style="color:#ffffff; font-size:18px; font-weight:bold; font-family: Arial, Helvetica, sans-serif;">{{ $emailBrandName }}</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px; color:#1c2340; font-size:14px; line-height:1.6;">
                        @yield('content')

                        @hasSection('cta_url')
                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:24px;">
                            <tr><td style="border-radius:8px; background-color:#04a1cc;">
                                <a href="@yield('cta_url')" target="_blank" style="display:inline-block; padding:11px 22px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none;">@yield('cta_label', 'View Details')</a>
                            </td></tr>
                        </table>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 32px; background-color:#f7f8fc; font-size:12px; color:#838aa3; border-top:1px solid #eceff5;">
                        This is an automated message from {{ $emailBrandName }}. Please do not reply directly to this email.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
