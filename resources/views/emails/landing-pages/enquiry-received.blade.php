@extends('emails.layout')

@section('subject', 'New Enquiry — ' . $pageTitle)

@section('content')
<p style="margin:0 0 16px;">Hi Admin,</p>

<p style="margin:0 0 16px;">
    Someone just submitted a form on the <strong>{{ $pageTitle }}</strong> landing page.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f8fc; border-radius:10px; margin:20px 0;">
    <tr><td style="padding:16px 20px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">
            @if($enquiry->name)
            <tr><td style="padding:4px 0; color:#838aa3; width:120px;">Name</td><td style="padding:4px 0; font-weight:bold; color:#1c2340;">{{ $enquiry->name }}</td></tr>
            @endif
            @if($enquiry->email)
            <tr><td style="padding:4px 0; color:#838aa3;">Email</td><td style="padding:4px 0; color:#1c2340;">{{ $enquiry->email }}</td></tr>
            @endif
            @if($enquiry->phone)
            <tr><td style="padding:4px 0; color:#838aa3;">Phone</td><td style="padding:4px 0; color:#1c2340;">{{ $enquiry->phone }}</td></tr>
            @endif
            @if($enquiry->company)
            <tr><td style="padding:4px 0; color:#838aa3;">Company</td><td style="padding:4px 0; color:#1c2340;">{{ $enquiry->company }}</td></tr>
            @endif
            @if($enquiry->country)
            <tr><td style="padding:4px 0; color:#838aa3;">Country</td><td style="padding:4px 0; color:#1c2340;">{{ $enquiry->country }}</td></tr>
            @endif
            @if($enquiry->message)
            <tr><td style="padding:4px 0; color:#838aa3; vertical-align:top;">Message</td><td style="padding:4px 0; color:#1c2340;">{{ $enquiry->message }}</td></tr>
            @endif
            @foreach($enquiry->extra_fields ?? [] as $key => $value)
            <tr><td style="padding:4px 0; color:#838aa3;">{{ \Illuminate\Support\Str::headline($key) }}</td><td style="padding:4px 0; color:#1c2340;">{{ is_array($value) ? implode(', ', $value) : $value }}</td></tr>
            @endforeach
            <tr><td style="padding:4px 0; color:#838aa3;">Received</td><td style="padding:4px 0; color:#1c2340;">{{ $enquiry->created_at->format('d M Y, h:i A') }}</td></tr>
        </table>
    </td></tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0">
    <tr><td style="border-radius:8px; background-color:#04a1cc;">
        <a href="{{ $reviewUrl }}" target="_blank" style="display:inline-block; padding:11px 22px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none;">View in Enquiries</a>
    </td></tr>
</table>
@endsection
