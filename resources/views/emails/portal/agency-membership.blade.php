@extends('emails.layout')

@section('subject', $subjectLine)

@section('content')
<p style="margin:0 0 16px;">Hi {{ $recipientName }},</p>

<p style="margin:0 0 16px;">{{ $body }}</p>

@if($actionUrl)
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:12px 0 20px;">
    <tr><td style="border-radius:8px; background-color:#04a1cc;">
        <a href="{{ $actionUrl }}" target="_blank" style="display:inline-block; padding:11px 22px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none;">{{ $actionLabel ?? 'Open Portal' }}</a>
    </td></tr>
</table>
@endif

<p style="margin:0; color:#838aa3; font-size:13px;">If you weren't expecting this email, you can safely ignore it.</p>
@endsection
