<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('subject', config('cms-kit.common.name', 'MW Realty'))</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6fb; font-family: Arial, Helvetica, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6fb; padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:14px; overflow:hidden;">
                <tr>
                    <td style="background-color:#264373; padding:22px 32px;">
                        <span style="color:#ffffff; font-size:18px; font-weight:bold; font-family: Arial, Helvetica, sans-serif;">{{ config('cms-kit.common.name', 'MW Realty') }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px; color:#1c2340; font-size:14px; line-height:1.6;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 32px; background-color:#f7f8fc; font-size:12px; color:#838aa3; border-top:1px solid #eceff5;">
                        This is an automated message from {{ config('cms-kit.common.name', 'MW Realty') }}. Please do not reply directly to this email.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
