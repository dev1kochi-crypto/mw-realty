@extends('emails.layout')

@section('subject', 'New Newsletter Signup')

@section('content')
<p style="margin:0 0 16px;">Hi Admin,</p>

<p style="margin:0 0 16px;">
    A new visitor just subscribed to the newsletter.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f8fc; border-radius:10px; margin:20px 0;">
    <tr><td style="padding:16px 20px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">
            <tr><td style="padding:4px 0; color:#838aa3; width:120px;">Email</td><td style="padding:4px 0; font-weight:bold; color:#1c2340;">{{ $signup->email }}</td></tr>
            <tr><td style="padding:4px 0; color:#838aa3;">Received</td><td style="padding:4px 0; color:#1c2340;">{{ $signup->created_at->format('d M Y, h:i A') }}</td></tr>
        </table>
    </td></tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0">
    <tr><td style="border-radius:8px; background-color:#04a1cc;">
        <a href="{{ $reviewUrl }}" target="_blank" style="display:inline-block; padding:11px 22px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none;">View Subscribers</a>
    </td></tr>
</table>
@endsection
