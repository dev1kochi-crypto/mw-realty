@extends('emails.layout')

@section('subject', 'Your Account Has Been Approved')

@section('content')
<table role="presentation" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
    <tr><td style="background-color:rgba(15,157,88,0.1); border-radius:50%; width:48px; height:48px; text-align:center; vertical-align:middle; font-size:22px;">&#9989;</td></tr>
</table>

<p style="margin:0 0 16px;">Hi {{ $portalUser->name }},</p>

<p style="margin:0 0 16px;">
    Great news — your <strong>{{ $portalUser->type }}</strong> account on {{ config('cms-kit.common.name', 'MW Realty') }}'s partner portal has been <strong style="color:#0f9d58;">approved</strong>.
    You can now log in, manage your properties, and track your CRM leads.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:12px 0 20px;">
    <tr><td style="border-radius:8px; background-color:#0f9d58;">
        <a href="{{ $loginUrl }}" target="_blank" style="display:inline-block; padding:11px 22px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none;">Log In to Your Portal</a>
    </td></tr>
</table>

<p style="margin:0; color:#838aa3; font-size:13px;">If you weren't expecting this email, you can safely ignore it.</p>
@endsection
