@extends('emails.layout')

@section('subject', 'Your MW Realty verification code')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $name }},</p>

<p style="margin:0 0 20px;">
    Use this code to verify your email and finish creating your MW Realty account.
    It expires in 10 minutes.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
    <tr><td style="padding:16px 32px; background-color:#f7f8fc; border-radius:10px; font-size:32px; font-weight:bold; letter-spacing:10px; color:#1c2340; text-align:center;">
        {{ $code }}
    </td></tr>
</table>

<p style="margin:0; font-size:13px; color:#838aa3;">
    If you didn't request this, you can safely ignore this email.
</p>
@endsection
