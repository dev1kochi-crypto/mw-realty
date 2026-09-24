@extends('emails.layout')

@section('subject', ($isResubmission ?? false) ? 'Resubmitted for Review — ' . $displayName : 'New ' . ucfirst($portalUser->type) . ' Registration — ' . $displayName)

@section('content')
<p style="margin:0 0 16px;">Hi Admin,</p>

@if($isResubmission ?? false)
<p style="margin:0 0 16px;">
    <strong>{{ $portalUser->type }}</strong> {{ $displayName }} has updated their KYC profile and resubmitted it for review.
</p>
@else
<p style="margin:0 0 16px;">
    <strong>{{ $portalUser->type }}</strong> {{ $displayName }} has submitted their KYC profile for approval.
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

<p style="margin:0 0 16px; font-size:13px;">
    <strong>KYC documents: {{ $documentsUploadedCount }} of {{ $documentsTotalCount }} uploaded.</strong>
    @if($missingDocuments->isNotEmpty())
        <br>Still missing: {{ $missingDocuments->implode(', ') }}.
    @endif
</p>

@section('cta_url', $reviewUrl)
@section('cta_label', 'Review Account')
@endsection
