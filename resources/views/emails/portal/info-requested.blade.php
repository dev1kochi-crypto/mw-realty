@extends('emails.layout')

@section('subject', 'MW Realty needs a bit more information from you')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $portalUser->displayName() }},</p>

<p style="margin:0 0 16px;">
    Our team is reviewing your account and needs a bit more information from you:
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f8fc; border-radius:10px; margin:0 0 20px;">
    <tr><td style="padding:16px 20px; font-size:13px; color:#1c2340; white-space:pre-line;">{{ $requestMessage }}</td></tr>
</table>

<p style="margin:0 0 16px;">Please update your profile at your earliest convenience.</p>

@section('cta_url', $profileUrl)
@section('cta_label', 'Update My Profile')
@endsection
