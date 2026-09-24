@extends('emails.layout')

@section('subject', 'Your ' . $documentLabel . ' has expired — MW Realty')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $portalUser->displayName() }},</p>

<p style="margin:0 0 16px;">
    Your <strong>{{ $documentLabel }}</strong> on file with MW Realty
    {{ $expiryDate ? 'expired on ' . $expiryDate->format('d M Y') : 'has expired' }}.
</p>

<p style="margin:0 0 16px;">
    Please update it from your CRM profile as soon as possible to avoid any disruption to your account.
</p>

<table role="presentation" cellpadding="0" cellspacing="0">
    <tr><td style="border-radius:8px; background-color:#04a1cc;">
        <a href="{{ $profileUrl }}" target="_blank" style="display:inline-block; padding:11px 22px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none;">Update Profile</a>
    </td></tr>
</table>
@endsection
