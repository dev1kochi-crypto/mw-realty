@extends('emails.layout')

@section('subject', ($isResubmission ?? false) ? 'Resubmitted for Review — ' . $displayName : 'New ' . ucfirst($portalUser->type) . ' Registration — ' . $displayName)

@section('content')
<p style="margin:0 0 16px;">Hi Admin,</p>

@if($isResubmission ?? false)
<p style="margin:0 0 16px;">
    A previously rejected <strong>{{ $portalUser->type }}</strong> account has updated their profile and is asking for another review.
</p>
@else
<p style="margin:0 0 16px;">
    A new <strong>{{ $portalUser->type }}</strong> account has just registered on the partner portal and is waiting for your review before they can list properties.
</p>
@endif

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f8fc; border-radius:10px; margin:20px 0;">
    <tr><td style="padding:16px 20px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">
            <tr><td style="padding:4px 0; color:#838aa3; width:120px;">Name</td><td style="padding:4px 0; font-weight:bold; color:#1c2340;">{{ $displayName }}</td></tr>
            <tr><td style="padding:4px 0; color:#838aa3;">Type</td><td style="padding:4px 0; color:#1c2340;">{{ ucfirst($portalUser->type) }}</td></tr>
            <tr><td style="padding:4px 0; color:#838aa3;">Email</td><td style="padding:4px 0; color:#1c2340;">{{ $portalUser->email }}</td></tr>
            <tr><td style="padding:4px 0; color:#838aa3;">Phone</td><td style="padding:4px 0; color:#1c2340;">{{ $portalUser->phone ?: '-' }}</td></tr>
            <tr><td style="padding:4px 0; color:#838aa3;">Registered</td><td style="padding:4px 0; color:#1c2340;">{{ $portalUser->created_at->format('d M Y, h:i A') }}</td></tr>
        </table>
    </td></tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0">
    <tr><td style="border-radius:8px; background-color:#04a1cc;">
        <a href="{{ $reviewUrl }}" target="_blank" style="display:inline-block; padding:11px 22px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none;">Review Account</a>
    </td></tr>
</table>
@endsection
