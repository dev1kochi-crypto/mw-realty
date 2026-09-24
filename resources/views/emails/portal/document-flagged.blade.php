@extends('emails.layout')

@section('subject', 'Action Needed: ' . $documentLabel)

@section('content')
<p style="margin:0 0 16px;">Hi {{ $portalUser->displayName() }},</p>

<p style="margin:0 0 16px;">
    Your <strong>{{ $documentLabel }}</strong> needs another look. Please re-upload it from your profile.
</p>

@if($note)
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f8fc; border-radius:10px; margin:0 0 20px;">
    <tr><td style="padding:16px 20px; font-size:13px;">
        <div style="color:#838aa3; margin-bottom:4px;">Admin's note</div>
        <div style="color:#1c2340;">{{ $note }}</div>
    </td></tr>
</table>
@endif

@section('cta_url', $profileUrl)
@section('cta_label', 'Update My Profile')
@endsection
