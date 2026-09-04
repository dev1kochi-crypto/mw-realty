<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $siteName }} Password Reset</title>
</head>
<body style="margin:0; padding:32px 16px; background-color:#f5f5f5; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px; margin:0 auto;">
        <tr>
            <td style="text-align:center; padding-bottom:24px;">
                @if(!empty($logoUrl))
                    <img src="{{ $logoUrl }}" alt="{{ $siteName }}" style="max-width:180px; max-height:72px; height:auto;">
                @else
                    <div style="font-size:28px; font-weight:700; color:#111827;">{{ $siteName }}</div>
                @endif
            </td>
        </tr>
        <tr>
            <td style="background:#ffffff; border-radius:14px; padding:32px; box-shadow:0 4px 18px rgba(15, 23, 42, 0.08);">
                <p style="margin:0 0 16px; font-size:24px; font-weight:700; color:#111827;">Hello!</p>
                <p style="margin:0 0 16px; font-size:16px; line-height:1.65;">You are receiving this email because we received a password reset request for your account.</p>

                <div style="padding:12px 0 20px;">
                    <a href="{{ $resetUrl }}" style="display:inline-block; background:#111827; color:#ffffff; text-decoration:none; padding:12px 22px; border-radius:8px; font-weight:600;">Reset Password</a>
                </div>

                <p style="margin:0 0 16px; font-size:16px; line-height:1.65;">This password reset link will expire in {{ $expireMinutes }} minutes.</p>
                <p style="margin:0 0 24px; font-size:16px; line-height:1.65;">If you did not request a password reset, no further action is required.</p>

                <p style="margin:0; font-size:16px; line-height:1.65;">Regards,<br>{{ $siteName }}</p>

                <hr style="border:none; border-top:1px solid #e5e7eb; margin:28px 0;">

                <p style="margin:0 0 8px; font-size:13px; line-height:1.6; color:#4b5563;">
                    If you're having trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser:
                </p>
                <p style="margin:0; font-size:13px; line-height:1.6; word-break:break-all;">
                    <a href="{{ $resetUrl }}" style="color:#2563eb;">{{ $resetUrl }}</a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
