@extends('emails.layout')

@section('subject', 'New Career Application')

@section('content')
<p style="margin:0 0 16px;">Hi Admin,</p>

<p style="margin:0 0 16px;">
    A new job application was just submitted on the Careers page.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f8fc; border-radius:10px; margin:20px 0;">
    <tr><td style="padding:16px 20px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">
            @if($candidate->apply_for)
            <tr><td style="padding:4px 0; color:#838aa3; width:120px;">Position</td><td style="padding:4px 0; font-weight:bold; color:#1c2340;">{{ $candidate->apply_for }}</td></tr>
            @endif
            <tr><td style="padding:4px 0; color:#838aa3; width:120px;">Name</td><td style="padding:4px 0; font-weight:bold; color:#1c2340;">{{ $candidate->name }}</td></tr>
            <tr><td style="padding:4px 0; color:#838aa3;">Email</td><td style="padding:4px 0; color:#1c2340;">{{ $candidate->email }}</td></tr>
            @if($candidate->phone)
            <tr><td style="padding:4px 0; color:#838aa3;">Phone</td><td style="padding:4px 0; color:#1c2340;">{{ $candidate->phone }}</td></tr>
            @endif
            @if($candidate->experience)
            <tr><td style="padding:4px 0; color:#838aa3;">Experience</td><td style="padding:4px 0; color:#1c2340;">{{ $candidate->experience }}</td></tr>
            @endif
            @if($candidate->designation)
            <tr><td style="padding:4px 0; color:#838aa3;">Current Role</td><td style="padding:4px 0; color:#1c2340;">{{ $candidate->designation }}</td></tr>
            @endif
            @if($candidate->country)
            <tr><td style="padding:4px 0; color:#838aa3;">Country</td><td style="padding:4px 0; color:#1c2340;">{{ $candidate->country }}</td></tr>
            @endif
            @if($candidate->additional_information)
            <tr><td style="padding:4px 0; color:#838aa3; vertical-align:top;">Message</td><td style="padding:4px 0; color:#1c2340;">{{ $candidate->additional_information }}</td></tr>
            @endif
            <tr><td style="padding:4px 0; color:#838aa3;">Received</td><td style="padding:4px 0; color:#1c2340;">{{ $candidate->submitted_at?->format('d M Y, h:i A') }}</td></tr>
        </table>
    </td></tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0">
    <tr><td style="border-radius:8px; background-color:#04a1cc;">
        <a href="{{ $reviewUrl }}" target="_blank" style="display:inline-block; padding:11px 22px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none;">View Application</a>
    </td></tr>
</table>
@endsection
