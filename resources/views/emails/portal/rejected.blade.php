@extends('emails.layout')

@section('subject', 'Update on Your Account Application')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $portalUser->name }},</p>

<p style="margin:0 0 16px;">
    Thank you for registering as a <strong>{{ $portalUser->type }}</strong> on {{ config('cms-kit.common.name', 'MW Realty') }}'s partner portal.
    After review, we're unable to approve your account at this time.
</p>

@if($reason)
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fdf2f2; border-left:3px solid #dc3545; border-radius:6px; margin:16px 0;">
    <tr><td style="padding:14px 18px;">
        <div style="font-size:11px; font-weight:bold; text-transform:uppercase; letter-spacing:0.03em; color:#dc3545; margin-bottom:4px;">Reason</div>
        <div style="font-size:13px; color:#1c2340;">{{ $reason }}</div>
    </td></tr>
</table>
@endif

<p style="margin:16px 0 0;">
    If you believe this was a mistake or would like to provide more information, please contact our support team and we'll be happy to help.
</p>
@endsection
